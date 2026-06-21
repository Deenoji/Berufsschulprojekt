<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Option;
use App\Entity\Question;
use App\Entity\Survey;
use App\Entity\SurveyCategory;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AdministrationController extends AbstractController
{
    /* =========================================================
     *  DASHBOARD (Uebersicht)
     * ========================================================= */
    #[Route('/admin', name: 'admin')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $categories = $entityManager->getRepository(Category::class)->findAll();
        $surveys = $entityManager->getRepository(Survey::class)->findBy([], ['id' => 'DESC']);

        $categoryMap = $this->buildCategoryMap($categories);
        $surveyCategories = $this->buildSurveyCategoryMap($entityManager, $categoryMap);

        $activeCount = 0;
        foreach ($surveys as $survey) {
            if ('ACTIVE' === $survey->getStatus()) {
                ++$activeCount;
            }
        }

        $pendingUpgrades = $entityManager->getRepository(User::class)->findBy(['upgradeRequested' => true]);

        return $this->render('admin/index.html.twig', [
            'surveys' => $surveys,
            'category_map' => $categoryMap,
            'survey_categories' => $surveyCategories,
            'pending_upgrades' => $pendingUpgrades,
            'stats' => [
                'active' => $activeCount,
                'total' => count($surveys),
                'categories' => count($categories),
            ],
        ]);
    }

    /* =========================================================
     *  UMFRAGE-MANAGEMENT (Surveys + Kategorien anlegen)
     * ========================================================= */
    #[Route('/admin/surveys', name: 'surveyManagement')]
    public function surveyManagement(EntityManagerInterface $entityManager): Response
    {
        $categories = $entityManager->getRepository(Category::class)->findAll();
        $surveys = $entityManager->getRepository(Survey::class)->findBy([], ['id' => 'DESC']);

        $categoryMap = $this->buildCategoryMap($categories);
        $surveyCategories = $this->buildSurveyCategoryMap($entityManager, $categoryMap);

        // Distinct-Zaehlung je Kategorie ueber Haupt- UND Verknuepfungskategorien.
        $idsByCategory = [];
        foreach ($surveys as $survey) {
            $idsByCategory[$survey->getCategoryId()][$survey->getId()] = true;
        }
        foreach ($entityManager->getRepository(SurveyCategory::class)->findAll() as $link) {
            $idsByCategory[$link->getCategoryId()][$link->getSurveyId()] = true;
        }
        $surveysPerCategory = [];
        foreach ($idsByCategory as $cid => $ids) {
            $surveysPerCategory[$cid] = count($ids);
        }

        return $this->render('admin/includes/survey_management.html.twig', [
            'categories' => $categories,
            'surveys' => $surveys,
            'category_map' => $categoryMap,
            'survey_categories' => $surveyCategories,
            'surveys_per_category' => $surveysPerCategory,
        ]);
    }

    #[Route('/admin/add', name: 'addSurvey', methods: ['POST'])]
    public function addSurvey(Request $request, EntityManagerInterface $entityManager): Response
    {
        $title = trim((string) $request->request->get('survey_title'));
        $categoryIds = $request->request->all('category'); // jetzt Mehrfachauswahl (category[])
        $startDateStr = $request->request->get('start_date');
        $endDateStr = $request->request->get('end_date');

        // Nur gültige (numerische) Kategorie-IDs behalten.
        $categoryIds = array_values(array_filter(array_map('intval', (array) $categoryIds)));

        if ('' === $title || empty($categoryIds) || !$startDateStr || !$endDateStr) {
            $this->addFlash('danger', 'Bitte Titel, mindestens eine Kategorie sowie Start- und Enddatum ausfüllen.');

            return $this->redirectToRoute('surveyManagement');
        }

        $survey = new Survey();
        $survey->setTitle($title);
        $survey->setDescription($request->request->get('survey_description'));
        $survey->setStartDate(new \DateTime($startDateStr));
        $survey->setEndDate(new \DateTime($endDateStr));
        // Erste Kategorie als "Haupt"-Kategorie (Spalte category_id bleibt befüllt).
        $survey->setCategoryId($categoryIds[0]);
        $survey->setStatus('ACTIVE');

        $entityManager->persist($survey);
        $entityManager->flush();

        // Alle ausgewählten Kategorien als Verknüpfung speichern.
        foreach (array_unique($categoryIds) as $categoryId) {
            $link = new SurveyCategory();
            $link->setSurveyId($survey->getId());
            $link->setCategoryId($categoryId);
            $entityManager->persist($link);
        }

        $question = new Question();
        $question->setSurveyId($survey->getId());
        $question->setQuestionText((string) $request->request->get('question'));

        $entityManager->persist($question);
        $entityManager->flush();

        $optionsData = $request->request->all('option');
        foreach ($optionsData as $optionText) {
            if ('' === trim((string) $optionText)) {
                continue;
            }

            $option = new Option();
            $option->setOptionText($optionText);
            $option->setQuestionId($question->getId());

            $entityManager->persist($option);
        }

        $entityManager->flush();

        $this->addFlash('success', 'Umfrage gespeichert!');

        return $this->redirectToRoute('surveyManagement');
    }

    #[Route('/admin/survey/{id}/delete', name: 'deleteSurvey', methods: ['POST'])]
    public function deleteSurvey(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $survey = $entityManager->getRepository(Survey::class)->find($id);

        if (null === $survey) {
            throw $this->createNotFoundException('Umfrage nicht gefunden.');
        }

        if (!$this->isCsrfTokenValid('delete_survey_'.$survey->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Ungueltiges Sicherheits-Token.');

            return $this->redirectToRoute('surveyManagement');
        }

        // Fragen + Optionen entfernen.
        $questions = $entityManager->getRepository(Question::class)->findBy(['survey_id' => $survey->getId()]);
        foreach ($questions as $question) {
            $options = $entityManager->getRepository(Option::class)->findBy(['question_id' => $question->getId()]);
            foreach ($options as $option) {
                $entityManager->remove($option);
            }
            $entityManager->remove($question);
        }

        // Kategorie-Verknüpfungen entfernen.
        $links = $entityManager->getRepository(SurveyCategory::class)->findBy(['survey_id' => $survey->getId()]);
        foreach ($links as $link) {
            $entityManager->remove($link);
        }

        $entityManager->remove($survey);
        $entityManager->flush();

        $this->addFlash('success', 'Umfrage geloescht.');

        return $this->redirectToRoute('surveyManagement');
    }

    #[Route('/admin/survey/{id}/edit', name: 'editSurvey', methods: ['GET'])]
    public function editSurvey(int $id, EntityManagerInterface $entityManager): Response
    {
        $survey = $entityManager->getRepository(Survey::class)->find($id);
        if (null === $survey) {
            throw $this->createNotFoundException('Umfrage nicht gefunden.');
        }

        $categories = $entityManager->getRepository(Category::class)->findAll();

        // Erste Frage + ihre Optionen.
        $question = $entityManager->getRepository(Question::class)->findOneBy(['survey_id' => $survey->getId()]);
        $options = [];
        if (null !== $question) {
            $options = $entityManager->getRepository(Option::class)->findBy(['question_id' => $question->getId()], ['id' => 'ASC']);
        }

        // Bereits zugeordnete Kategorien (Fallback: Hauptkategorie).
        $selected = [];
        foreach ($entityManager->getRepository(SurveyCategory::class)->findBy(['survey_id' => $survey->getId()]) as $link) {
            $selected[] = $link->getCategoryId();
        }
        if (empty($selected) && null !== $survey->getCategoryId()) {
            $selected[] = $survey->getCategoryId();
        }

        return $this->render('admin/survey_edit.html.twig', [
            'survey' => $survey,
            'categories' => $categories,
            'question' => $question,
            'options' => $options,
            'selected_categories' => $selected,
        ]);
    }

    #[Route('/admin/survey/{id}/edit', name: 'updateSurvey', methods: ['POST'])]
    public function updateSurvey(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $survey = $entityManager->getRepository(Survey::class)->find($id);
        if (null === $survey) {
            throw $this->createNotFoundException('Umfrage nicht gefunden.');
        }

        if (!$this->isCsrfTokenValid('edit_survey_'.$survey->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Ungueltiges Sicherheits-Token.');

            return $this->redirectToRoute('surveyManagement');
        }

        $title = trim((string) $request->request->get('survey_title'));
        $categoryIds = array_values(array_filter(array_map('intval', (array) $request->request->all('category'))));
        $startDateStr = $request->request->get('start_date');
        $endDateStr = $request->request->get('end_date');
        $status = (string) $request->request->get('status');

        if ('' === $title || empty($categoryIds) || !$startDateStr || !$endDateStr) {
            $this->addFlash('danger', 'Bitte Titel, mindestens eine Kategorie sowie Start- und Enddatum ausfuellen.');

            return $this->redirectToRoute('editSurvey', ['id' => $survey->getId()]);
        }

        $survey->setTitle($title);
        $survey->setDescription($request->request->get('survey_description'));
        $survey->setStartDate(new \DateTime($startDateStr));
        $survey->setEndDate(new \DateTime($endDateStr));
        $survey->setStatus(in_array($status, ['ACTIVE', 'CLOSED'], true) ? $status : $survey->getStatus());
        $survey->setCategoryId($categoryIds[0]);

        // Kategorie-Verknüpfungen neu setzen.
        foreach ($entityManager->getRepository(SurveyCategory::class)->findBy(['survey_id' => $survey->getId()]) as $link) {
            $entityManager->remove($link);
        }
        foreach (array_unique($categoryIds) as $categoryId) {
            $link = new SurveyCategory();
            $link->setSurveyId($survey->getId());
            $link->setCategoryId($categoryId);
            $entityManager->persist($link);
        }

        // Frage aktualisieren oder anlegen.
        $question = $entityManager->getRepository(Question::class)->findOneBy(['survey_id' => $survey->getId()]);
        if (null === $question) {
            $question = new Question();
            $question->setSurveyId($survey->getId());
            $entityManager->persist($question);
        }
        $question->setQuestionText((string) $request->request->get('question'));
        $entityManager->flush(); // Frage-ID fuer neue Optionen sicherstellen.

        // Optionen abgleichen: bestehende per ID aktualisieren, neue anlegen, entfernte löschen.
        $existing = [];
        foreach ($entityManager->getRepository(Option::class)->findBy(['question_id' => $question->getId()]) as $option) {
            $existing[$option->getId()] = $option;
        }

        $optionIds = $request->request->all('option_id');
        $optionTexts = $request->request->all('option_text');

        $keep = [];
        $newTexts = [];
        foreach ($optionTexts as $i => $text) {
            $text = trim((string) $text);
            $oid = (int) ($optionIds[$i] ?? 0);

            if ($oid > 0) {
                if ('' !== $text) {
                    $keep[$oid] = $text;
                }
            } elseif ('' !== $text) {
                $newTexts[] = $text;
            }
        }

        foreach ($existing as $oid => $option) {
            if (isset($keep[$oid])) {
                $option->setOptionText($keep[$oid]);
            } else {
                // Im Formular entfernt oder geleert -> löschen.
                $entityManager->remove($option);
            }
        }

        foreach ($newTexts as $text) {
            $option = new Option();
            $option->setQuestionId($question->getId());
            $option->setOptionText($text);
            $entityManager->persist($option);
        }

        $entityManager->flush();

        $this->addFlash('success', 'Umfrage aktualisiert.');

        return $this->redirectToRoute('surveyManagement');
    }

    #[Route('/admin/category/add', name: 'addCategory', methods: ['POST'])]
    public function addCategory(Request $request, EntityManagerInterface $entityManager): Response
    {
        $title = trim((string) $request->request->get('category_title'));

        if ('' === $title) {
            $this->addFlash('danger', 'Bitte einen Kategorie-Namen angeben.');

            return $this->redirectToRoute('surveyManagement');
        }

        $category = new Category();
        $category->setTitle($title);

        $entityManager->persist($category);
        $entityManager->flush();

        $this->addFlash('success', 'Kategorie angelegt.');

        return $this->redirectToRoute('surveyManagement');
    }

    #[Route('/admin/category/{id}/delete', name: 'deleteCategory', methods: ['POST'])]
    public function deleteCategory(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $category = $entityManager->getRepository(Category::class)->find($id);

        if (null === $category) {
            throw $this->createNotFoundException('Kategorie nicht gefunden.');
        }

        if (!$this->isCsrfTokenValid('delete_category_'.$category->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Ungueltiges Sicherheits-Token.');

            return $this->redirectToRoute('surveyManagement');
        }

        // Nicht löschen, solange Umfragen die Kategorie nutzen (Haupt- oder Verknüpfung).
        $inUse = $entityManager->getRepository(Survey::class)->count(['category_id' => $category->getId()])
            + $entityManager->getRepository(SurveyCategory::class)->count(['category_id' => $category->getId()]);
        if ($inUse > 0) {
            $this->addFlash('danger', 'Kategorie wird noch von Umfragen genutzt und kann nicht geloescht werden.');

            return $this->redirectToRoute('surveyManagement');
        }

        $entityManager->remove($category);
        $entityManager->flush();

        $this->addFlash('success', 'Kategorie geloescht.');

        return $this->redirectToRoute('surveyManagement');
    }

    /* =========================================================
     *  BENUTZER-MANAGEMENT
     * ========================================================= */
    #[Route('/admin/users', name: 'userManagement')]
    public function userManagement(EntityManagerInterface $entityManager): Response
    {
        $users = $entityManager->getRepository(User::class)->findBy([], ['id' => 'DESC']);

        return $this->render('admin/includes/user_management.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/admin/user/{id}/role', name: 'updateUserRole', methods: ['POST'])]
    public function updateUserRole(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $entityManager->getRepository(User::class)->find($id);

        if (null === $user) {
            throw $this->createNotFoundException('Benutzer nicht gefunden.');
        }

        if (!$this->isCsrfTokenValid('update_role_'.$user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Ungueltiges Sicherheits-Token.');

            return $this->redirectToRoute('userManagement');
        }

        $current = $this->getUser();
        if ($current instanceof User && $current->getId() === $user->getId()) {
            $this->addFlash('danger', 'Du kannst deine eigene Rolle hier nicht aendern.');

            return $this->redirectToRoute('userManagement');
        }

        $newRole = (string) $request->request->get('role');
        $allowed = ['ROLE_USER', 'ROLE_CUSTOMER', 'ROLE_ADMIN'];

        if (!in_array($newRole, $allowed, true)) {
            $this->addFlash('danger', 'Unbekannte Rolle.');

            return $this->redirectToRoute('userManagement');
        }

        $user->setRole($newRole);
        if ('ROLE_USER' !== $newRole) {
            $user->setUpgradeRequested(false);
        }

        $entityManager->flush();

        $this->addFlash('success', 'Rolle aktualisiert.');

        return $this->redirectToRoute('userManagement');
    }

    /* =========================================================
     *  AUFSTUFUNGSANFRAGEN
     * ========================================================= */
    #[Route('/admin/user/{id}/upgrade/accept', name: 'acceptUpgrade', methods: ['POST'])]
    public function acceptUpgrade(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $entityManager->getRepository(User::class)->find($id);

        if (null === $user) {
            throw $this->createNotFoundException('Benutzer nicht gefunden.');
        }

        if (!$this->isCsrfTokenValid('upgrade_'.$user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Ungueltiges Sicherheits-Token.');

            return $this->redirectBack($request);
        }

        $user->setRole('ROLE_CUSTOMER');
        $user->setUpgradeRequested(false);
        $entityManager->flush();

        $this->addFlash('success', sprintf('%s wurde zum Kunden hochgestuft.', $user->getEmail()));

        return $this->redirectBack($request);
    }

    #[Route('/admin/user/{id}/upgrade/reject', name: 'rejectUpgrade', methods: ['POST'])]
    public function rejectUpgrade(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $entityManager->getRepository(User::class)->find($id);

        if (null === $user) {
            throw $this->createNotFoundException('Benutzer nicht gefunden.');
        }

        if (!$this->isCsrfTokenValid('upgrade_'.$user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Ungueltiges Sicherheits-Token.');

            return $this->redirectBack($request);
        }

        $user->setUpgradeRequested(false);
        $entityManager->flush();

        $this->addFlash('success', 'Aufstufungsanfrage abgelehnt.');

        return $this->redirectBack($request);
    }

    /* =========================================================
     *  Helfer
     * ========================================================= */

    private function redirectBack(Request $request): Response
    {
        $referer = $request->headers->get('referer');

        return $referer
            ? $this->redirect($referer)
            : $this->redirectToRoute('userManagement');
    }

    /**
     * @param Category[] $categories
     *
     * @return array<int, string>
     */
    private function buildCategoryMap(array $categories): array
    {
        $map = [];
        foreach ($categories as $category) {
            $map[$category->getId()] = $category->getTitle();
        }

        return $map;
    }

    /**
     * Baut survey_id => [ ['id' => int, 'title' => string], ... ].
     *
     * @param array<int, string> $categoryMap
     *
     * @return array<int, list<array{id: int, title: string}>>
     */
    private function buildSurveyCategoryMap(EntityManagerInterface $entityManager, array $categoryMap): array
    {
        $map = [];
        foreach ($entityManager->getRepository(SurveyCategory::class)->findAll() as $link) {
            $map[$link->getSurveyId()][] = [
                'id' => $link->getCategoryId(),
                'title' => $categoryMap[$link->getCategoryId()] ?? 'Allgemein',
            ];
        }

        return $map;
    }
}