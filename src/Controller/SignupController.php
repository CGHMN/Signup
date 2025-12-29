<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\RequestRepository;
use App\Entity\EmailCode;
use App\Form\UserType;
use App\Form\EmailCodeType;
use App\Entity\User;

final class SignupController extends AbstractController
{
    #[Route('/', name: 'app.signup')]
    public function index(Request $request, UserPasswordHasherInterface $passwordHasher, MailerInterface $mailer): Response {
        $user = new User;

        // Create & handle the signup form
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Hash the user's password ASAP
            $user->setPassword($passwordHasher->hashPassword($user, $form->get('plainPassword')->getData()));
            // Set the user's role to pending
            $user->setRoles(['ROLE_USER_PENDING']);
            // Store the user's request to the session.
            $session = $request->getSession();
            $session->set('pendingRequest', $user);
            $session->set('emailCode', bin2hex(random_bytes(5)));
            $session->set('expires', time() + 1200);

            // Send them an email with their verification code.
            $email = (new Email())
                ->to($user->getEmail())
                ->subject("CGHMN Email Verification")
                ->text(
                    "Dear {$user->getUsername()},\r\n" .
                    "Your verification code is:\r\n" . 
                    $session->get('emailCode') . "\r\n" .
                    "This code expires in 20 minutes.\r\n" .
                    "If you do not recognize this email, please email {$this->getParameter('app.contactEmail')}.", 
                );
            $mailer->send($email);

            // Redirect them to the verification page.
            return $this->redirectToRoute('app.signup.verify');
        }

        return $this->render('signup/index.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/verify', name: 'app.signup.verify')]
    public function verify(Request $request, EntityManagerInterface $manager): Response {
        // Check that there is a pending request in the session.
        $session = $request->getSession();

        if ($session->get('pendingRequest') === null || 
            $session->get('emailCode') === null || 
            $session->get('expires') === null ||
            $session->get('expires') < time()) {
            $session->invalidate();
            $this->addFlash('notice', 'Sorry, we could not find your request. Please try again.');
            return $this->render('signup/verify.html.twig', [
                'form' => null,
            ]);
        }
        $emailCode = new EmailCode;

        // Create & handle the email code form
        $form = $this->createForm(EmailCodeType::class, $emailCode);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Check the submitted email code against the one in the session.
            if ($emailCode->getCode() === $session->get('emailCode')) {
                // Submit the request.
                $user = $session->get('pendingRequest');
                $manager->persist($user);
                $manager->flush();
                // Kill the session
                $session->invalidate();
                $this->addFlash('notice', 'Your request was sucessfully submitted. It will be reviewed by an admin shortly.');
            } else {
                $this->addFlash('notice', 'Your email could not be verified. Please try again.');
            }
            
            // Hack to reload the page since I can't get the messages to display properly.
            return $this->redirectToRoute('app.signup.verify');
        }

        return $this->render('signup/verify.html.twig', [
            'form' => $form,
        ]);
    }
}
