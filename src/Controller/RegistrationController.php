<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RegistrationController extends AbstractController
{
    #[Route('/registration', name: 'registration')]
    public function index(): Response
    {
        return $this->render('page/content/registration.html.twig', [
            'controller_name' => 'RegistrationController',
        ]);
    }
}
