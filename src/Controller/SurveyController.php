<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SurveyController extends AbstractController
{
    #[Route('/survey', name: 'survey')]
    public function index(): Response
    {
        $string = "Hello, world!";
        return $this->render('page/content/survey.html.twig', [
            'FUCKYOU' => $string,
        ]);
    }
}
