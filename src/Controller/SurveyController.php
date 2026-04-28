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

final class SurveyController extends AbstractController
{
    #[Route('/survey', name: 'survey')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $activeSurveys = $entityManager->getRepository(Survey::class)->findBy([
            'status' => 'ACTIVE'
        ], ['end_date' => 'DESC']);

        $surveyIds = array_map(fn($s) => $s->getId(), $activeSurveys);

        $questions = $entityManager->getRepository(Question::class)->findBy([
            'survey_id' => $surveyIds
        ]);

        $questionIds = array_map(fn($q) => $q->getId(), $questions);

        $options = $entityManager->getRepository(Option::class)->findBy([
            'question_id' => $questionIds
        ]);

        return $this->render('page/content/survey.html.twig', [
            'surveys' => $activeSurveys,
            'questions' => $questions,
            'options' => $options,
        ]);
    }
}
