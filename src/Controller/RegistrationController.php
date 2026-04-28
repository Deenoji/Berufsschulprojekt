<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Question;
use App\Entity\Category;
use App\Entity\Option;
use App\Entity\Survey;

final class RegistrationController extends AbstractController
{
    #[Route('/registration', name: 'registration_index')]
    public function index(): Response
    {
        return $this->render('page/content/registration.html.twig', [
            'controller_name' => 'RegistrationController',
        ]);
    }

    #[Route('/registration', name: 'login', methods: ['POST'])]
    public function login(): Response
    {
        return $this->render('page/content/registration.html.twig', [
            'controller_name' => 'RegistrationController',
        ]);
    }

    #[Route('/registration', name: 'registration', methods: ['POST'])]
    public function registration(): Response
    {
        return $this->render('page/content/registration.html.twig', [
            'controller_name' => 'RegistrationController',
        ]);
    }
}
