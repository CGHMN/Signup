<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\UserRepository;
use App\Entity\Requests;
use App\Form\RequestCollectionType;

final class AdminController extends AbstractController
{
    #[Route('/admin', name: 'admin')]
    public function index(Request $request, UserRepository $userRepository, EntityManagerInterface $manager): Response
    {
        // Print out the list of requests.
        $requests = new Requests($userRepository);
        $form = $this->createForm(RequestCollectionType::class, $requests);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Process the requests.
            foreach($form->get('requests') as $request) {
                // TODO: Actually process the requests
                $user = $request->getData();
                if ($request->get('decision')->getData() === 1) {
                    $user->setRoles(['ROLE_USER_APPROVED']);
                } else if ($request->get('decision')->getData() === 2) {
                    $user->setRoles(['ROLE_USER_REJECTED']);
                }
                $manager->flush();
            }
            return $this->redirectToRoute('admin');
        }

        return $this->render('admin/index.html.twig', [
            'form' => $form,
        ]);
    }
}
