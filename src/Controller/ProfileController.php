<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\SecurityBundle\Security;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use App\Form\UserType;

final class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app.profile')]
    public function index(): Response
    {
        return $this->render('profile/index.html.twig', [
            'controller_name' => 'ProfileController',
        ]);
    }

    // Allows users to update their password.
    #[Route('/profile/password', 'app.profile.changePassword')]
    public function password(Request $request, EntityManagerInterface $manager,
        UserPasswordHasherInterface $passwordHasher, Security $security) {
        $user = $this->getUser();
        $form = $this->createForm(UserType::class, $user, [
            'update' => 'password',
        ]);
        $form->handleRequest($request);

        // Update the users password
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
}
