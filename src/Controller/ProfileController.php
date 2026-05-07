<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use App\Entity\WireguardPeer;
use App\Form\UserType;
use App\Form\WireguardPeerType;
use App\Form\AccountDeleteFormType;

final class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app.profile')]
    public function index(): Response
    {
        return $this->render('profile/index.html.twig');
    }

    // Allows users to update their password.
    #[Route('/profile/password', name: 'app.profile.changePassword')]
    public function password(Request $request, EntityManagerInterface $manager,
        UserPasswordHasherInterface $passwordHasher, Security $security) {

        // Create the password update form.
        $user = $this->getUser();
        $form = $this->createForm(UserType::class, $user, [
            'update' => 'password',
        ]);
        $form->handleRequest($request);

        // Update the user's password
        if ($form->isSubmitted() && $form->isValid()) {
            // Make sure that they typed their current password correctly.
            if (!$passwordHasher->isPasswordValid($user, $form->get('password')->getData())) {
                $this->addFlash('notice',
                    'Sorry, we could not verify your password. ' .
                    'Please check that you typed it correctly and try again.'
                );
                return $this->redirect($request->getUri());
            }

            // Go!
            $user->setPassword($passwordHasher->hashPassword($user, $form->get('plainPassword')->getData()));
            $manager->flush();

            // Force the user to log in again after changing their password.
            $response = $security->logout(false);
            $this->addFlash('notice', 'Your password has been successfully updated. Please sign in again.');
            return $this->redirectToRoute('app.login');
        }

        return $this->render('profile/password.html.twig', [
            'form' => $form,
        ]);
    }

    // Allows users to update their profile.
    // TODO: Add a new update page just for updating emails.
    #[Route('/profile/update', name: 'app.profile.update')]
    public function update(Request $request, EntityManagerInterface $manager,
        HttpClientInterface $httpClient): Response {
        // Create the update form.
        $user = $this->getUser();
        $form = $this->createForm(UserType::class, $user, [
            'update' => 'profile',
            'type' => $this->isGranted('ROLE_ADMIN') ? 'admin' : 'user',
        ]);

        $newPeer = null;
        $peerForm = null;

        // Limit users to 4 Wireguard Peers
        if ($user->getWireguardPeers()->count() < 4 && $this->isGranted('ROLE_USER_APPROVED')) {
            $newPeer = new WireguardPeer();
            $peerForm = $this->createForm(WireguardPeerType::class, $newPeer);
        }

        // Handle the update form
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // Update their Wireguard peers on the router.
            foreach ($form->get('wireguardPeers') as $peer) {
                $response = $httpClient->request('PUT',
                    "{$this->getParameter('app.router')}servers/{$this->getParameter('app.routerID')}/peers/{$peer->getData()->getPeerID()}", [
                        'json' => [
                            'public_key' => $peer->get('pubKey')->getData(),
                        ],
                        'headers' => [
                            'X-API-Key' => $this->getParameter('app.rtrApiKey'),
                        ],
                        'timeout' => 5,
                    ]
                );

                // Check for errors.
                if ($response->getStatusCode() < 200 || $response->getStatusCode() > 299) {
                    // If we encounter an error, give up.
                    $this->addFlash('notice', 'Sorry, an error occured while ' .
                    'trying to update your Wireguard peers. Please try again later.');
                    return $this->redirect($request->getUri());
                }
            }

            // Commit the changes.
            $manager->flush();
            $this->addFlash('notice', 'Profile updated successfully!');
            return $this->redirect($request->getUri());
        }

        // Handle the new peer form.
        if ($peerForm) {
            $peerForm->handleRequest($request);
            if ($peerForm->isSubmitted() && $peerForm->isValid()) {
                // Create the peer on the router
                $tunnelNum = strval($user->getWireguardPeers()->count() + 1);
                $response = $httpClient->request('POST',
                    "{$this->getParameter('app.router')}servers/{$this->getParameter('app.routerID')}/gen_new_peer", [
                        'body' => [
                            'name' => "Tunnel $tunnelNum for member {$user->getUsername()}",
                            'public_key' => $newPeer->getPubKey(),
                        ],
                        'headers' => [
                            'X-API-Key' => $this->getParameter('app.rtrApiKey'),
                        ],
                        'timeout' => 5,
                    ]
                );

                // Check for errors.
                if ($response->getStatusCode() < 200 || $response->getStatusCode() > 299) {
                    // If we encounter an error, give up.
                    dd($response);
                    $this->addFlash('notice', 'Sorry, an error occured while ' .
                    'trying to update your Wireguard peers. Please try again later.');
                    return $this->redirect($request->getUri());
                }

                // Decode the response from the server.
                $res = $response->toArray();
                if (isset($res['message'])) {
                    dd($response);
                    // If we encounter an error, give up.
                    $this->addFlash('notice', 'Sorry, an error occured while ' .
                    'trying to update your Wireguard peers. Please try again later.');
                    return $this->redirect($request->getUri());
                }

                // Set up the new WG peer
                $newPeer->setPeerID($res['id']);
                $newPeer->setTunnelIP($res['tunnel_ip']);
                $newPeer->setAllowedIPs($res['allowed_ips']);
                $newPeer->setPubKey($res['public_key']);
                $newPeer->setPresharedKey($res['preshared_key']);
                $newPeer->setUser($user);

                // Persist the WG peer and commit the changes.
                $manager->persist($newPeer);
                $manager->flush();
                $this->addFlash('notice', 'Wireguard peer created successfully!');
                return $this->redirect($request->getUri());
            }
        }

        return $this->render('profile/update.html.twig', [
            'form' => $form,
            'peerForm' => $peerForm
        ]);
    }

    // Allows users to delete their own account.
    #[Route('/profile/delete', name: 'app.profile.delete')]
    public function delete(Request $request, EntityManagerInterface $manager,
        UserPasswordHasherInterface $passwordHasher, HttpClientInterface $httpClient,
        Security $security, TokenStorageInterface $tokenStorage) {

        $form = $this->createForm(AccountDeleteFormType::class);

        // Handle the deletion form.
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Make sure that they typed their current password correctly.
            if (!$passwordHasher->isPasswordValid($this->getUser(), $form->get('password')->getData())) {
                $this->addFlash('notice',
                    'Sorry, we could not verify your password. ' .
                    'Please check that you typed it correctly and try again.'
                );
                return $this->redirect($request->getUri());
            }

            // Well, they asked for it. Start by cleanin their Wireguard peers.
            $errors = $this->getUser()->clean($httpClient, $manager,
                $this->getParameter('app.router'), $this->getParameter('app.rtrApiKey'), 
                $this->getParameter('app.routerID'));

            if (count($errors) > 0) {
                $this->addFlash('notice',
                    'Sorry, an error occured while trying to delete your account. ' .
                    'Please try again later.'
                );
                return $this->redirect($request->getUri());
            }

            // All's gone well. Time to delete the user for real. Goodbye!
            $user = $this->getUser();
            // Workaround based on https://stackoverflow.com/questions/44740129/symfony3-how-to-delete-current-user-and-redirect-to-home
            $request->getSession()->invalidate();
            $tokenStorage->setToken(null);
            $manager->remove($user);
            $manager->flush();

            $this->addFlash('notice', 'Your account was successfully deleted.');
            $this->addFlash('notice', 'We hope to see you again soon!');
            $this->addFlash('notice', '-The CGHMN Team.');

            return $this->redirectToRoute('app.signup');
        }

        return $this->render('profile/delete.html.twig', [
            'form' => $form,
        ]);
    }
}
