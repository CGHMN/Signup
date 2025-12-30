<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class LoginController extends AbstractController
{
    // Allow users to log in.
    #[Route(path: '/login', name: 'app.login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('login/index.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }
    
    #[Route(path: '/next', name: 'app.next')]
    public function next(): Response {
        // Redirect admins to the admin page.
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app.admin');
        }
        if ($this->isGranted('ROLE_USER_APPROVED')) {
            return $this->redirectToRoute('app.user.profile');
        }
        // TODO: Some sort of banned/access denied page?
        return $this->redirectToRoute('app.logout');
    }

    #[Route(path: '/logout', name: 'app.logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
