<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Question;
use App\Entity\Category;
use App\Entity\Option;
use App\Entity\Survey;

final class AdministrationController extends AbstractController
{
    #[Route('/admin', name: 'admin')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $categories = $entityManager->getRepository(Category::class)->findAll();

        return $this->render('admin/index.html.twig', [
            'categories' => $categories,
        ]);
    }
    #[Route('/admin/add', name: 'addSurvey', methods: ['POST'])]
    public function addSurvey(Request $request, EntityManagerInterface $entityManager): Response
    {
        // 1. Daten holen (Achte auf die Namen aus dem HTML!)
        $categoryId = $request->request->get('category');
        $startDateStr = $request->request->get('start_date');
        $endDateStr = $request->request->get('end_date');

        // 2. Datums-Berechnung
        $startDate = new \DateTime($startDateStr);
        $endDate = new \DateTime($endDateStr);

        // 3. Survey erstellen
        $survey = new Survey();

        $survey->setTitle($request->request->get('survey_title'));
        $survey->setDescription($request->request->get('survey_description'));
        $survey->setStartDate($startDate);
        $survey->setEndDate($endDate);
        $survey->setCategoryId($categoryId);
        $survey->setStatus('ACTIVE');

        $entityManager->persist($survey);
        $entityManager->flush();

        $question = new Question();
        $question->setSurveyId($survey->getId());
        $question->setQuestionText($request->request->get('question'));

        $entityManager->persist($question);
        $entityManager->flush();

        $optionsData = $request->request->all('option');

        foreach ($optionsData as $optionText) {
            if (empty($optionText)) continue;

            $option = new Option();
            $option->setOptionText($optionText);
            $option->setQuestionId($question->getId());

            $entityManager->persist($option);
        }

        $entityManager->flush();

        $this->addFlash('success', 'Umfrage gespeichert!');
        return $this->redirectToRoute('admin');
    }
}
