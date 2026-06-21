<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Option;
use App\Entity\Question;
use App\Entity\Survey;
use App\Entity\SurveyCategory;
use App\Entity\SurveyHistory;
use App\Entity\User;
use App\Entity\Vote;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class SurveyController extends AbstractController
{
    /**
     * Zeigt alle Umfragen mit echten Daten (Kategorie, Status, Beteiligung)
     * sowie - nach dem Abstimmen - die Ergebnisverteilung.
     */
    #[Route('/survey', name: 'survey', methods: ['GET'])]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $surveys = $entityManager->getRepository(Survey::class)->findBy([], ['id' => 'DESC']);
        $questions = $entityManager->getRepository(Question::class)->findAll();
        $options = $entityManager->getRepository(Option::class)->findAll();
        $categories = $entityManager->getRepository(Category::class)->findAll();

        // Lookup category_id => Titel (keine Doctrine-Relation vorhanden).
        $categoryMap = [];
        foreach ($categories as $category) {
            $categoryMap[$category->getId()] = $category->getTitle();
        }

        // survey_id => [ ['id' => ..., 'title' => ...], ... ] (mehrere Kategorien).
        $surveyCategories = [];
        foreach ($entityManager->getRepository(SurveyCategory::class)->findAll() as $link) {
            $surveyCategories[$link->getSurveyId()][] = [
                'id' => $link->getCategoryId(),
                'title' => $categoryMap[$link->getCategoryId()] ?? 'Allgemein',
            ];
        }

        $historyRepo = $entityManager->getRepository(SurveyHistory::class);
        $histories = $historyRepo->findAll();

        // Stimmen je Option zaehlen.
        $optionVotes = [];
        foreach ($histories as $history) {
            $oid = $history->getSelectedOptionId();
            $optionVotes[$oid] = ($optionVotes[$oid] ?? 0) + 1;
        }

        // Gesamtstimmen je Frage (Summe ihrer Optionen) -> fuer Prozentwerte.
        $questionTotals = [];
        foreach ($options as $option) {
            $qid = $option->getQuestionId();
            $questionTotals[$qid] = ($questionTotals[$qid] ?? 0) + ($optionVotes[$option->getId()] ?? 0);
        }

        // Welche Umfragen hat der aktuelle Nutzer schon beantwortet + seine Wahl.
        $votedMap = [];
        $userChoices = [];
        $user = $this->getUser();
        if ($user instanceof User) {
            foreach ($historyRepo->findBy(['user_id' => $user->getId()]) as $history) {
                $votedMap[$history->getSurveyId()] = true;
                $userChoices[$history->getSelectedOptionId()] = true;
            }
        }

        // Beteiligung + Aktiv-Status je Umfrage.
        $totalUsers = $entityManager->getRepository(User::class)->count([]);
        $now = new \DateTime();
        $participation = [];
        $activeMap = [];

        foreach ($surveys as $survey) {
            $count = $historyRepo->count(['survey_id' => $survey->getId()]);
            $participation[$survey->getId()] = [
                'count' => $count,
                'percent' => $totalUsers > 0 ? (int) round($count / $totalUsers * 100) : 0,
            ];

            $activeMap[$survey->getId()] =
                'ACTIVE' === $survey->getStatus()
                && (null === $survey->getEndDate() || $survey->getEndDate() >= $now);
        }

        return $this->render('page/content/survey.html.twig', [
            'surveys' => $surveys,
            'questions' => $questions,
            'options' => $options,
            'categories' => $categories,
            'category_map' => $categoryMap,
            'survey_categories' => $surveyCategories,
            'participation' => $participation,
            'active_map' => $activeMap,
            'option_votes' => $optionVotes,
            'question_totals' => $questionTotals,
            'voted_map' => $votedMap,
            'user_choices' => $userChoices,
        ]);
    }

    /**
     * Nimmt die Stimmabgabe entgegen. Nur fuer Kunden (und Admins via Hierarchie).
     * Reine ROLE_USER duerfen Umfragen sehen, aber nicht abstimmen.
     */
    #[Route('/survey', name: 'surveyVote', methods: ['POST'])]
    #[IsGranted('ROLE_CUSTOMER')]
    public function vote(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // answers = [ question_id => option_id ]
        $answers = $request->request->all('answers');

        if (empty($answers)) {
            $this->addFlash('danger', 'Bitte mindestens eine Antwort auswaehlen.');

            return $this->redirectToRoute('survey');
        }

        // Survey aus der ersten beantworteten Frage ableiten (Formular = eine Umfrage).
        $firstQuestionId = (int) array_key_first($answers);
        $firstQuestion = $entityManager->getRepository(Question::class)->find($firstQuestionId);

        if (null === $firstQuestion) {
            $this->addFlash('danger', 'Ungueltige Umfrage.');

            return $this->redirectToRoute('survey');
        }

        $surveyId = $firstQuestion->getSurveyId();

        // Doppelte Teilnahme verhindern.
        $existing = $entityManager->getRepository(SurveyHistory::class)->findOneBy([
            'survey_id' => $surveyId,
            'user_id' => $user->getId(),
        ]);

        if (null !== $existing) {
            $this->addFlash('danger', 'Du hast an dieser Umfrage bereits teilgenommen.');

            return $this->redirectToRoute('survey');
        }

        // Antworten als History-Eintraege speichern.
        foreach ($answers as $optionId) {
            $history = new SurveyHistory();
            $history->setSurveyId($surveyId);
            $history->setUserId($user->getId());
            $history->setSelectedOptionId((int) $optionId);

            $entityManager->persist($history);
        }

        // Umfrage-Stimmenzaehler erhoehen (Vote-Datensatz bei Bedarf anlegen).
        $vote = $entityManager->getRepository(Vote::class)->findOneBy(['survey_id' => $surveyId]);
        if (null === $vote) {
            $vote = new Vote();
            $vote->setSurveyId($surveyId);
            $vote->setVotes(0);
            $entityManager->persist($vote);
        }
        $vote->setVotes($vote->getVotes() + 1);

        // Persoenlichen Vote-Count des Nutzers erhoehen.
        $user->setVoteCount($user->getVoteCount() + 1);

        $entityManager->flush();

        $this->addFlash('success', 'Danke fuer deine Teilnahme!');

        return $this->redirectToRoute('survey');
    }
}