<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\UserRepository;
use App\Entity\Requests;
use App\Entity\WireguardPeer;
use App\Form\RequestCollectionType;

final class AdminController extends AbstractController
{
    #[Route('/admin', name: 'app.admin')]
    public function index(Request $request, UserRepository $userRepository, 
        EntityManagerInterface $manager, HttpClientInterface $httpClient,
        MailerInterface $mailer): Response {
        // Print out the list of requests.
        $requests = new Requests($userRepository);
        $form = $this->createForm(RequestCollectionType::class, $requests);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Process the requests.
            $usersApproved = 0;
            $usersRejected = 0;
            $errors = [];
            foreach($form->get('requests') as $request) {
                // Get the user
                $user = $request->getData();

                // Handle the action
                if ($request->get('decision')->getData() === 1) {
                    // Create the new Wireguard Peer
                    // TODO: Wrap this in a try-catch
                    try {
                        $response = $client->request('POST', 
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
                        // Decode the response from the server.
                        $res = $response->toArray();
                    } catch (Exception $e) {
                        // Log the message and continue.
                        array_push($errors, $e->getMessage());
                        continue;
                    }
                    if (isset($res['message'])) {
                        // Log the message and continue.
                        array_push($errors, $res['message']);
                        continue;
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
                    try {
                        $response = $client->request('GET',
                            "{$this->getParameter('app.router')}servers/1/{$res['id']}/config", [
                                'headers' => [
                                    'X-API-Key' => $this->getParameter('app.rtrApiKey'),
                                ],
                                'timeout' => 5,
                            ]
                        );
                        $config = $response->getContent();
                    } catch (Exception $e) {
                        // This is bad. One router API request succeeded but not the other.
                        // Delete the orphaned WG peer and continue.
                        $response = $client->request('DELETE',
                            "{$this->getParameter('app.router')}servers/1/peers/{$peer->getRouterID()}", [
                                'headers' => [
                                    'X-API-Key' => $this->getParameter('app.rtrApiKey'),
                                ],
                                'timeout' => 5,
                            ]
                        );

                        // Log the message and continue.
                        array_push($errors, $e->getMessage());
                        continue;
                    }

                    // Everything has succeeded. Persist the peer, approve the user, 
                    // and let them know they were approved.
                    $manager->persist($peer);
                    $user->setRoles(['ROLE_USER_APPROVED']);
                    $usersApproved++;
                    sendConfirmationEmail($user, $peer, $config, $mailer);
                } else if ($request->get('decision')->getData() === 2) {
                    $user->setRoles(['ROLE_USER_REJECTED']);
                    $usersRejected++;
                    // TODO: Send user an email if they've been rejected?
                    // I fear that if we let bad actors know when they were rejected,
                    // they'll immediately turn around and flood us with requests,
                    // vs assuming we're just taking a while to get around to approving it.
                }
            }
            $manager->flush();
            $this->addFlash('notice', "Successfully approved $usersApproved requests and rejected $usersRejected requests.");
            if (count($errors) > 0) {
                $this->addFlash('notice', 'However, the following errors were encountered while attempting to process requests:');
                foreach($errors as $error) {
                    $this->addFlash('error', $error);
                }
            }
            return $this->redirectToRoute('app.admin');
        }

        return $this->render('admin/index.html.twig', [
            'form' => $form,
        ]);
    }

    private function sendConfirmationEmail(User $user, WireguardPeer $peer, 
        string $exampleConfig, MailerInterface $mailer) {
        // Create the email contents
        $body =
            "Dear {$user->getUsername()},\r\n" .
            "Welcome to CGHMN!\r\n" .
            "Your tunnel IP is {$peer->getTunnelIP()},\r\n" .
            "Your WireGuard Preshared Key is {$peer->getPresharedKey()},\r\n" . 
            "And your routed subnet is {$peer->getAllowedIPs()[0]}.\r\n" .
            "Here's an example config you can use:\r\n---\r\n" .
            "$exampleConfig\r\n---\r\n" .
            "If you're not sure how to set up your CGHMN Router,\r\n" .
            "you can find some beginner-friendly instructions at:\r\n" .
            "https://wiki.cursedsilicon.net/wiki/Signup\r\n" .
            "If you need help with anything,\r\n" .
            "feel free to reach out at\r\n" .
            $this->getParameter('app.contactEmail');
        $email = (new Email())
            ->to($user->getEmail())
            ->subject("Welcome to CGHMN!")
            ->text($body);
        $mailer->send($email);
    }
}
