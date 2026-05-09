<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\UserRepository;
use App\Repository\WireguardPeerRepository;
use App\Entity\Requests;
use App\Entity\WireguardPeer;
use App\Entity\User;
use App\Form\RequestCollectionType;
use App\Form\UserType;
use App\Form\WireguardPeerType;
use App\Form\MassEmailFormType;
use App\Form\PeerMigrateFormType;

final class AdminController extends AbstractController
{
    #[Route('/admin', name: 'app.admin')]
    public function index(Request $request, UserRepository $userRepository,
        EntityManagerInterface $manager, HttpClientInterface $httpClient,
        MailerInterface $mailer, Security $security): Response {
        // Print out the list of requests.
        $requests = new Requests($userRepository, 'ROLE_USER_PENDING', $security);

        // If there are no pending requests, say so and return immediately.
        $form = $this->createForm(RequestCollectionType::class, $requests);
        if ($requests->getRequests()->count() === 0) {
            $this->addFlash('notice', 'No requests were found! :D');
            return $this->render('admin/index.html.twig', [
                'form' => $form,
            ]);
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Process the requests.
            $usersApproved = 0;
            $usersRejected = 0;
            $usersDeleted = 0;
            $errors = [];
            foreach($form->get('requests') as $signupRequest) {
                // Get the user
                $user = $signupRequest->getData();

                // Handle the action
                switch ($signupRequest->get('decision')->getData()) {
                    case 0:
                        break;
                    case 1:
                        // Create the new Wireguard Peer
                        $response = $httpClient->request('POST',
                            "{$this->getParameter('app.router')}servers/{$this->getParameter('app.routerID')}/gen_new_peer", [
                                'body' => [
                                    'name' => "Tunnel for member {$user->getUsername()}",
                                    'public_key' => $user->getPubKey(),
                                ],
                                'headers' => [
                                    'X-API-Key' => $this->getParameter('app.rtrApiKey'),
                                ],
                                'timeout' => 5,
                            ]
                        );
                        // Check for errors.
                        if ($response->getStatusCode() < 200 || $response->getStatusCode() > 299) {
                            // Log the message and continue.
                            array_push($errors, $response->getHeaders(false)['status'][0]);
                            continue 2;
                        }
                        // Decode the response from the server.
                        $res = $response->toArray();
                        if (isset($res['message'])) {
                            // Log the message and continue.
                            array_push($errors, $res['message']);
                            continue 2;
                        }

                        // Set up the new WG peer but don't persist it yet.
                        $peer = new WireguardPeer();
                        $peer->setPeerID($res['id']);
                        $peer->setTunnelIP($res['tunnel_ip']);
                        $peer->setAllowedIPs($res['allowed_ips']);
                        $peer->setPubKey($res['public_key']);
                        $peer->setPresharedKey($res['preshared_key']);
                        $peer->setUser($user);

                        // Get the example config
                        $config = null;
                        $response = $httpClient->request('GET',
                            "{$this->getParameter('app.router')}servers/{$this->getParameter('app.routerID')}/peers/{$res['id']}/config", [
                                'headers' => [
                                    'X-API-Key' => $this->getParameter('app.rtrApiKey'),
                                ],
                                'timeout' => 5,
                            ]
                        );
                        if ($response->getStatusCode() < 200 || $response->getStatusCode() > 299) {
                            // This is bad. One router API request succeeded but not the other.
                            // Delete the orphaned WG peer and continue.
                            $message = $response->getHeaders(false)['status'][0];
                            $response = $httpClient->request('DELETE',
                                "{$this->getParameter('app.router')}servers/{$this->getParameter('app.routerID')}/peers/{$peer->getPeerID()}", [
                                    'headers' => [
                                        'X-API-Key' => $this->getParameter('app.rtrApiKey'),
                                    ],
                                    'timeout' => 5,
                                ]
                            );

                            // If this fails... we in deeeep trouble.
                            if ($response->getStatusCode() < 200 || $response->getStatusCode() > 299) {
                                dd($response);
                            }

                            // Log the message and continue.
                            array_push($errors, $message);
                            continue 2;
                        }
                        $config = $response->getContent();

                        // Everything has succeeded. Persist the peer, approve the user,
                        // and let them know they were approved.
                        $manager->persist($peer);
                        $user->setRoles(['ROLE_USER_APPROVED']);
                        $usersApproved++;
                        $this->sendConfirmationEmail($user, $peer, $config, $mailer);
                        break;
                    case 2:
                        $user->setRoles(['ROLE_USER_REJECTED']);
                        $usersRejected++;
                        // TODO: Send user an email if they've been rejected?
                        // I fear that if we let bad actors know when they were rejected,
                        // they'll immediately turn around and flood us with requests,
                        // vs assuming we're just taking a while to get around to approving it.
                        break;
                    case 3:
                        $manager->remove($user);
                        $usersDeleted++;
                        break;
                    default:
                        throw new \Exception("How'd we get here?!");
                }
            }
            $manager->flush();
            $this->addFlash('notice',
                "Successfully approved $usersApproved, requests, " .
                "rejected $usersRejected requests, " .
                "and deleted $usersDeleted requests."
            );
            if (count($errors) > 0) {
                $this->addFlash('notice',
                    'However, the following errors were encountered ' .
                    'while attempting to process requests:'
                );
                foreach($errors as $error) {
                    $this->addFlash('error', $error);
                }
            }
            return $this->redirect($request->getUri());
        }

        return $this->render('admin/index.html.twig', [
            'form' => $form,
        ]);
    }

    // Admin management page (Create/Delete admins, change your password, delete your account.)
    #[Route('/admin/manage', name: 'app.admin.manage')]
    public function adminMgmt() {
        return $this->render('admin/manage.html.twig');
    }

    // Creation page for new admins
    #[Route('/admin/new', name: 'app.admin.new')]
    #[IsGranted('ROLE_CREATE_ADMINS')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function newAdmin(Request $request, EntityManagerInterface $manager,
        UserPasswordHasherInterface $passwordHasher) {
        // This user will become the new admin.
        $newAdmin = new User();
        $form = $this->createForm(UserType::class, $newAdmin, [
            'type' => 'admin',
            'methodLabel' => 'What is their preferred contact method?',
            'detailsLabel' => 'How can we reach them?',
        ]);
        $form->handleRequest($request);

        // Create the admin.
        if ($form->isSubmitted() && $form->isValid()) {
            // Set whether the admin can create new users or not.
            if ($form->get('super')->getData()) {
                $newAdmin->setRoles(['ROLE_SUPER_ADMIN']);
            } else {
                $newAdmin->setRoles(['ROLE_ADMIN']);
            }

            $newAdmin->setPassword($passwordHasher->hashPassword($newAdmin, $form->get('plainPassword')->getData()));
            $manager->persist($newAdmin);
            $manager->flush();
            $this->addFlash('notice', "Admin {$newAdmin->getUsername()} created successfully.");
            $this->addFlash('notice', 'Please tell them to change their password as soon as possible!');
            return $this->redirect($request->getUri());
        }

        return $this->render('admin/new.html.twig', [
            'form' => $form,
        ]);
    }

    // Page for managing users. (Banning/Deleting/Etc)
    #[Route('/admin/users', name: 'app.admin.users')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function users(Request $request, UserRepository $userRepository,
        EntityManagerInterface $manager, HttpClientInterface $httpClient,
        MailerInterface $mailer, Security $security): Response {
        // Print out the list of users.
        $requests = new Requests($userRepository, 'ROLE_USER_APPROVED', $security);
        $form = $this->createForm(RequestCollectionType::class, $requests, [
            'actions' => [
                'Do Nothing' => 0,
                'Resend Confirmation Email' => 1,
                'Ban' => 2,
                'Delete' => 3
            ],
        ]);
        $form->handleRequest($request);

        // Execute the requested actions.
        if ($form->isSubmitted() && $form->isValid()) {
            // Process the requests.
            $emailsSent = 0;
            $usersBanned = 0;
            $usersDeleted = 0;
            $errors = [];
            foreach($form->get('requests') as $userRaw) {
                // Get the user
                $user = $userRaw->getData();
                switch ($userRaw->get('decision')->getData()) {
                    case 0:
                        break;
                    case 1:
                        // Resend the user's confirmation email.
                        $peer = $user->getWireguardPeers()[0];
                        // Get the example config
                        $response = $httpClient->request('GET',
                            "{$this->getParameter('app.router')}servers/{$this->getParameter('app.routerID')}/peers/{$peer->getPeerID()}/config", [
                                'headers' => [
                                    'X-API-Key' => $this->getParameter('app.rtrApiKey'),
                                ],
                                'timeout' => 5,
                            ]
                        );
                        // Make sure the request succeeded.
                        if ($response->getStatusCode() < 200 || $response->getStatusCode() > 299) {
                            // Log the errors and continue.
                            array_push($errors, $response->getHeaders(false)['status'][0]);
                            continue 2;
                        }
                        $config = $response->getContent();

                        // Send the confirmation email.
                        $this->sendConfirmationEmail($user, $peer, $config, $mailer);
                        $emailsSent++;
                        break;
                    case 2:
                        // Delete the users Wireguard peers
                        array_merge($errors, $user->clean($httpClient, $manager,
                            $this->getParameter('app.router'), $this->getParameter('app.rtrApiKey'),
                            $this->getParameter('app.routerID')));
                        // Give the user the ROLE_USER_BANNED role.
                        // We don't delete their info to prevent them from ever signing up again.
                        $user->setRoles(['ROLE_USER_BANNED']);
                        $usersBanned++;
                        break;
                    case 3:
                        // This is the same as banning a user except they can sign up again.
                        array_merge($errors, $user->clean($httpClient, $manager,
                            $this->getParameter('app.router'), $this->getParameter('app.rtrApiKey'),
                            $this->getParameter('app.routerID')));
                        $manager->remove($user);
                        $usersDeleted++;
                        break;
                }
            }
            $manager->flush();

            // Reload the Wireguard interface (but only if we need to)
            if ($usersBanned > 0 || $usersDeleted > 0) {
                // Don't care about the response.
                $response = $httpClient->request('POST',
                    "{$this->getParameter('app.router')}servers/{$this->getParameter('app.routerID')}/reload", [
                        'headers' => [
                            'X-API-Key' => $this->getParameter('app.rtrApiKey'),
                        ],
                        'timeout' => 5,
                    ]
                );
            }

            $this->addFlash('notice',
                "Successfully resent $emailsSent confirmation emails, " .
                "banned $usersBanned users, " .
                "and deleted $usersDeleted users."
            );
            if (count($errors) > 0) {
                $this->addFlash('notice',
                    'However, the following errors were encountered ' .
                    'while attempting to execute the requested actions:'
                );
                foreach($errors as $error) {
                    $this->addFlash('error', $error);
                }
            }
            return $this->redirect($request->getUri());
        }

        return $this->render('admin/users.html.twig', [
            'form' => $form,
        ]);
    }

    // More in depth user viewing.
    #[Route('/admin/users/{id<\d+>}', name: 'app.admin.users.view')]
    public function editUser(User $user, Security $security) {
        // This page is only for normal users.
        if (!$security->isGrantedForUser($user, 'ROLE_USER_APPROVED')) {
            return $this->redirectToRoute('app.admin');
        }

        return $this->render('admin/view.html.twig', [
            'user' => $user,
        ]);
    }

    // Allow admins to mass send emails.
    #[Route('/admin/email', name: 'app.admin.email')]
    public function email(Request $request, UserRepository $userRepository,
        MailerInterface $mailer, Security $security) {
        $form = $this->createForm(MassEmailFormType::class);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // Let's send an email to every approved user on CGHMN!
            // First, get the list of users.
            $users = (new Requests($userRepository, 'ROLE_USER_APPROVED', $security))->getRequests();

            // Next, get the details of the email we're supposed to send.
            $subject = $form->get('subject')->getData();

            // Add the admin's name to the end of the body for accountability.
            $body = preg_replace("/\n(?<!\r)/", "\r\n", $form->get('body')->getData() . "\n\n(Sent by {$this->getUser()->getUsername()})");

            // Now, SEND!
            $emailsSent = 0;
            foreach ($users as $user) {
                $email = (new Email())
                    ->from(new Address($this->getParameter('app.email'), 'CGHMN User Services'))
                    ->to($user->getEmail())
                    ->subject($subject)
                    ->text($body);
                $mailer->send($email);
                $emailsSent++;
            }
            $this->addFlash('notice',
                "Successfully sent emails to $emailsSent users.",
            );
            return $this->redirect($request->getUri());
        }

        return $this->render('admin/email.html.twig', [
            'form' => $form,
        ]);
    }

    // Page for managing admins (Ban/Delete/etc)
    #[Route('/admin/admins', name: 'app.admin.admins')]
    #[IsGranted('ROLE_CREATE_ADMINS')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function admins(Request $request, UserRepository $userRepository,
        EntityManagerInterface $manager, HttpClientInterface $httpClient,
        MailerInterface $mailer, Security $security): Response {
        // Print out the list of admins.
        $requests = new Requests($userRepository, 'ROLE_ADMIN', $security);
        $form = $this->createForm(RequestCollectionType::class, $requests, [
            'actions' => [
                'Do Nothing' => 0,
                'Ban' => 1,
                'Delete' => 2
            ],
        ]);
        $form->handleRequest($request);

        // Execute the requested actions.
        if ($form->isSubmitted() && $form->isValid()) {
            // Process the requests.
            $usersBanned = 0;
            $usersDeleted = 0;
            $errors = [];
            foreach($form->get('requests') as $userRaw) {
                // Get the user
                $user = $userRaw->getData();
                if ($user === $this->getUser()) {
                        array_push($errors, 'You can\'t delete or ban yourself through this page!');
                } else {
                    switch ($userRaw->get('decision')->getData()) {
                        case 0:
                            break;
                        case 1:
                            // Give the user the ROLE_USER_BANNED role.
                            // We don't delete their info so we can prevent them from ever signing up again.
                            $user->setRoles(['ROLE_USER_BANNED']);
                            $usersBanned++;
                            break;
                        case 2:
                            $manager->remove($user);
                            $usersDeleted++;
                            break;
                    }
                }
            }
            $manager->flush();

            $this->addFlash('notice',
                "Successfully banned $usersBanned admins, " .
                "and deleted $usersDeleted admins."
            );
            if (count($errors) > 0) {
                $this->addFlash('notice',
                    'However, the following errors were encountered ' .
                    'while attempting to execute the requested actions:'
                );
                foreach($errors as $error) {
                    $this->addFlash('error', $error);
                }
            }
            return $this->redirect($request->getUri());
        }

        return $this->render('admin/admins.html.twig', [
            'form' => $form,
            'security' => $security
        ]);
    }

    // Page for assigning pre-signup page Wireguard peers to existing users.
    #[Route('/admin/migrate', name: 'app.admin.migrate')]
    public function migrate(Request $request, UserRepository $userRepository, 
        WireguardPeerRepository $peerRepository, EntityManagerInterface $manager,
        HttpClientInterface $httpClient): Response {
        // Create and handle the peer migration form.
        $form = $this->createForm(PeerMigrateFormType::class);
        $form->handleRequest($request);
        
        // Execute the requested migration
        if ($form->isSubmitted() && $form->isValid()) {
            $errors = [];
            $id = null;
            // Find the user to assign the peer to.
            $username = $form->get('username')->getData();
            $user = $userRepository->findOneByUsername($username);
            if (!$user) {
                // Can't migrate a peer to a user who doesn't exist.
                array_push($errors, "User $username doesn't exist!");
            } else {
                // Check that the peer we're supposed to migrate isn't already assigned.
                $id = $form->get('peer')->getData();
                $peer = $peerRepository->findOneByPeerId($id);
                if ($peer) {
                    $this->addFlash('error',
                        "Peer $id is already assigned to a user!"
                    );
                } else {
                    // Query the Wireguard API to get the peer details.
                    $response = $httpClient->request('GET',
                        "{$this->getParameter('app.router')}servers/{$this->getParameter('app.routerID')}/peers/$id", [
                            'headers' => [
                                'X-API-Key' => $this->getParameter('app.rtrApiKey'),
                            ],
                            'timeout' => 5,
                        ]
                    );

                    // Make sure the request succeeded.
                    if ($response->getStatusCode() < 200 || $response->getStatusCode() > 299) {
                        // Log the errors and continue.
                        array_push($errors, $response->getHeaders(false)['status'][0]);
                    }
                    // Decode the response from the server and create a Wireguard peer accordingly.
                    $res = $response->toArray();
                    if (isset($res['message'])) {
                        // If there's an error message, log it.
                        array_push($errors, $res['message']);
                    } else {
                        // Set up the WG peer object and assign it to the user.
                        $peer = new WireguardPeer();
                        $peer->setPeerID($res['id']);
                        $peer->setTunnelIP($res['tunnel_ip']);
                        $peer->setAllowedIPs($res['allowed_ips']);
                        $peer->setPubKey($res['public_key']);
                        // Most pre-signup page users have NULL preshared keys.
                        // In this context, as far as I know, null is equivalent to
                        // all zeros.
                        if (!$res['preshared_key']) {
                            $res['preshared_key'] = '0000000000000000000000000000000000000000000=';
                        }
                        $peer->setPresharedKey($res['preshared_key']);
                        $peer->setUser($user);
                        $manager->persist($peer);
                        $manager->flush();
                    }
                }
            }

            if (count($errors) > 0) {
                $this->addFlash('notice',
                    'The following errors were encountered while attempting to ' .
                    'migrate the Wireguard peer:'
                );
                foreach($errors as $error) {
                    $this->addFlash('error', $error);
                }
            } else {
                $this->addFlash('notice', "Peer $id migrated successfully!");
            }

            return $this->redirect($request->getUri());
        }

        return $this->render('admin/migrate.html.twig', [
            'form' => $form
        ]);
    }

    // Helper function to send confirmation emails
    private function sendConfirmationEmail(User $user, WireguardPeer $peer,
        string $exampleConfig, MailerInterface $mailer): void {
        // Create the email contents
        $body =
            "Dear {$user->getUsername()},\r\n" .
            "Welcome to CGHMN!\r\n" .
            "Here are your connection details:\r\n" .
            "Tunnel IP: {$peer->getTunnelIP()}\r\n" .
            "WireGuard Preshared Key: {$peer->getPresharedKey()}\r\n" .
            "Routed Subnet: {$peer->getAllowedIPs()[0]['cidr']}\r\n" .
            "Here's an example config you can use:\r\n---\r\n" .
            "$exampleConfig\r\n---\r\n" .
            "If you're not sure how to set up your CGHMN Router,\r\n" .
            "you can find some beginner-friendly instructions at:\r\n" .
            "https://wiki.cursedsilicon.net/wiki/Signup\r\n" .
            "If you need help with anything,\r\n" .
            "feel free to reach out at\r\n" .
            $this->getParameter('app.contactEmail') .
            "Have fun!\r\n" .
            "-The CGHMN Team";
        $email = (new Email())
            ->from(new Address($this->getParameter('app.email'), 'CGHMN User Services'))
            ->to($user->getEmail())
            ->subject("Welcome to CGHMN!")
            ->text($body);
        $mailer->send($email);
    }
}
