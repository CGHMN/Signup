<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\UserRepository;
use App\Entity\Requests;
use App\Entity\WireguardPeer;
use App\Entity\User;
use App\Form\RequestCollectionType;
use App\Form\UserType;
use App\Form\WireguardPeerType;
use App\Form\MassEmailFormType;

final class AdminController extends AbstractController
{
    #[Route('/admin', name: 'app.admin')]
    public function index(Request $request, UserRepository $userRepository, 
        EntityManagerInterface $manager, HttpClientInterface $httpClient,
        MailerInterface $mailer): Response {
        // Print out the list of requests.
        $requests = new Requests($userRepository, 'ROLE_USER_PENDING');
        
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
                            "{$this->getParameter('app.router')}servers/1/gen_new_peer", [
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
                        $peer->setRouterID($res['id']);
                        $peer->setTunnelIP($res['tunnel_ip']);
                        $peer->setAllowedIPs($res['allowed_ips']);
                        $peer->setPubKey($res['public_key']);
                        $peer->setPresharedKey($res['preshared_key']);
                        $peer->setUser($user);

                        // Get the example config
                        $config = null;
                        $response = $httpClient->request('GET',
                            "{$this->getParameter('app.router')}servers/1/peers/{$res['id']}/config", [
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
                                "{$this->getParameter('app.router')}servers/1/peers/{$peer->getRouterID()}", [
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
    public function newAdmin(Request $request, EntityManagerInterface $manager,
        UserPasswordHasherInterface $passwordHasher) {
        $this->denyAccessUnlessGranted('ROLE_CREATE_ADMINS');
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
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
    public function users(Request $request, UserRepository $userRepository, 
        EntityManagerInterface $manager, HttpClientInterface $httpClient,
        MailerInterface $mailer): Response {
        // Print out the list of users.
        $requests = new Requests($userRepository, 'ROLE_USER_APPROVED');
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
                        $config = null;
                        $response = $httpClient->request('GET',
                            "{$this->getParameter('app.router')}servers/1/peers/{$peer->getRouterID()}/config", [
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
                        array_merge($errors, $this->cleanUser($user, $httpClient, $manager));
                        // Give the user the ROLE_USER_BANNED role.
                        // We don't delete their info to prevent them from ever signing up again.
                        $user->setRoles(['ROLE_USER_BANNED']);
                        $usersBanned++;
                        break;
                    case 3:
                        // This is the same as banning a user except they can sign up again.
                        array_merge($errors, $this->cleanUser($user, $httpClient, $manager));
                        $manager->remove($user);
                        $usersDeleted++;
                        break;
                }
            }
            $manager->flush();

            // Reload the Wireguard interface (but only if we need to)
            if ($usersBanned > 0 || $usersDeleted > 0) {
                // Don't care about the response.
                $response = $httpClient('POST', 
                    "{$this->getParameter('app.router')}servers/1/reload", [
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
    public function editUser(User $user) {
        // This page is only for normal users.
        if (!in_array('ROLE_USER_APPROVED', $user->getRoles())) {
            return $this->redirectToRoute('app.admin');
        }

        return $this->render('admin/view.html.twig', [
            'user' => $user,
        ]);
    }

    // Allow admins to mass send emails.
    #[Route('/admin/email', name: 'app.admin.email')]
    public function email(Request $request, UserRepository $userRepository, 
        MailerInterface $mailer) {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $form = $this->createForm(MassEmailFormType::class);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // Let's send an email to every approved user on CGHMN!
            // First, get the list of users.
            $users = $userRepository->findAll();

            // Next, get the details of the email we're supposed to send.
            $subject = $form->get('subject')->getData();

            // Add the admin's name to the end of the body for accountability.
            $body = preg_replace("/\n(?<!\r)/", "\r\n", $form->get('body')->getData() . "\nSent by {$this->getUser()->getUsername()}");

            // Now, SEND!
            $emailsSent = 0;
            foreach ($users as $user) {
                if (in_array("ROLE_USER_APPROVED", $user->getRoles(), true)) {
                    $email = (new Email())
                        ->from(new Address($this->getParameter('app.email'), 'CGHMN User Services'))
                        ->replyTo($this->getParameter('app.contactEmail'))
                        ->to($user->getEmail())
                        ->subject($subject)
                        ->text($body);
                    $mailer->send($email);
                    $emailsSent++;
                }
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
    public function admins(Request $request, UserRepository $userRepository, 
        EntityManagerInterface $manager, HttpClientInterface $httpClient,
        MailerInterface $mailer): Response {
        $this->denyAccessUnlessGranted('ROLE_CREATE_ADMINS');
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        // Print out the list of admins.
        $requests = new Requests($userRepository, 'ROLE_ADMIN');
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
            ->replyTo($this->getParameter('app.contactEmail'))
            ->to($user->getEmail())
            ->subject("Welcome to CGHMN!")
            ->text($body);
        $mailer->send($email);
    }

    // Helper function to clean a user (delete their Wireguard peers)
    private function cleanUser(User $user, HttpClientInterface $httpClient,
        EntityManagerInterface $manager): array {
        // Log any errors we experience.
        $errors = [];
        // Get their Wireguard peers
        $peers = $user->getWireguardPeers();

        // Delete them.
        foreach ($peers as $peer) {
            // Delete the Wireguard peer from the router.
            $response = $httpClient->request('DELETE',
                "{$this->getParameter('app.router')}servers/1/peers/{$peer->getRouterID()}", [
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
                continue;
            }

            // If we didn't encounter any errors, delete the peer on our end.
            $manager->remove($peer);
        }

        // We'll give them the courtesy of deleting the info we don't use
        // to identify users.
        // (ie: passwords, personal info)
        // May change this later, depending on how we want to handle unbans.
        $user->setPassword("none");
        $user->setPlan("");
        $user->setNeedsHosting(false);
        $user->setHasExperience(false);
        $user->setContactMethod("none");
        $user->setContactDetails("");

        // Flush the entity manager.
        $manager->flush();
        return $errors;
    }
}
