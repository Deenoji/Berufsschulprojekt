<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Option;
use App\Entity\Survey;
use App\Entity\SurveyHistory;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class AccountController extends AbstractController
{
    /**
     * Rang-Leiter: ab wie vielen Votes welcher Rang gilt.
     * Reihenfolge muss aufsteigend nach 'min' sein.
     */
    private const RANKS = [
        ['min' => 0,   'name' => 'Neuling',        'icon' => 'bi-egg-fill'],
        ['min' => 5,   'name' => 'Mitredner',      'icon' => 'bi-chat-dots-fill'],
        ['min' => 15,  'name' => 'Aktiver Voter',  'icon' => 'bi-hand-thumbs-up-fill'],
        ['min' => 30,  'name' => 'Profi-Voter',    'icon' => 'bi-trophy-fill'],
        ['min' => 50,  'name' => 'Meinungsmacher', 'icon' => 'bi-star-fill'],
        ['min' => 100, 'name' => 'Legende',        'icon' => 'bi-gem'],
    ];

    #[Route('/account', name: 'account')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $voteCount = $user->getVoteCount();

        // --- aktuellen Rang + naechsten Rang bestimmen ---
        $currentIndex = 0;
        foreach (self::RANKS as $i => $rankDef) {
            if ($voteCount >= $rankDef['min']) {
                $currentIndex = $i;
            }
        }
        $current = self::RANKS[$currentIndex];
        $next = self::RANKS[$currentIndex + 1] ?? null;

        if (null !== $next) {
            $span = $next['min'] - $current['min'];
            $done = $voteCount - $current['min'];
            $progress = $span > 0 ? (int) round($done / $span * 100) : 0;
            $votesToNext = $next['min'] - $voteCount;
        } else {
            $progress = 100;
            $votesToNext = 0;
        }

        $rank = [
            'level' => $currentIndex + 1,
            'name' => $current['name'],
            'icon' => $current['icon'],
            'next_name' => $next['name'] ?? null,
            'next_min' => $next['min'] ?? null,
            'votes_to_next' => $votesToNext,
            'progress' => $progress,
            'is_max' => null === $next,
        ];

        // --- Rollen-Label ---
        $roleLabels = ['ROLE_ADMIN' => 'Admin', 'ROLE_CUSTOMER' => 'Kunde', 'ROLE_USER' => 'Nutzer'];
        $roleLabel = $roleLabels[$user->getRole()] ?? $user->getRole();

        // --- Kategorie-Lookup ---
        $categoryMap = [];
        foreach ($entityManager->getRepository(Category::class)->findAll() as $category) {
            $categoryMap[$category->getId()] = $category->getTitle();
        }

        // --- Umfragen, an denen der Nutzer teilgenommen hat ---
        $historyRepo = $entityManager->getRepository(SurveyHistory::class);
        $surveyRepo = $entityManager->getRepository(Survey::class);
        $optionRepo = $entityManager->getRepository(Option::class);

        // Antworten nach Umfrage gruppieren.
        $bySurvey = [];
        foreach ($historyRepo->findBy(['user_id' => $user->getId()]) as $history) {
            $bySurvey[$history->getSurveyId()][] = $history->getSelectedOptionId();
        }

        $now = new \DateTime();
        $participated = [];
        foreach ($bySurvey as $surveyId => $optionIds) {
            $survey = $surveyRepo->find($surveyId);
            if (null === $survey) {
                continue;
            }

            $choices = [];
            foreach ($optionIds as $optionId) {
                $option = $optionRepo->find($optionId);
                if (null !== $option) {
                    $choices[] = $option->getOptionText();
                }
            }

            $participated[] = [
                'survey' => $survey,
                'category' => $categoryMap[$survey->getCategoryId()] ?? 'Allgemein',
                'choices' => $choices,
                'is_active' => 'ACTIVE' === $survey->getStatus()
                    && (null === $survey->getEndDate() || $survey->getEndDate() >= $now),
            ];
        }

        return $this->render('page/account/index.html.twig', [
            'rank' => $rank,
            'role_label' => $roleLabel,
            'vote_count' => $voteCount,
            'participated' => $participated,
        ]);
    }

    /**
     * Stellt eine Anfrage auf Hochstufung zum Kunden.
     * Nur fuer reine ROLE_USER moeglich.
     */
    #[Route('/account/upgrade-request', name: 'requestUpgrade', methods: ['POST'])]
    public function requestUpgrade(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->isCsrfTokenValid('request_upgrade', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Ungueltiges Sicherheits-Token.');

            return $this->redirectBack($request);
        }

        // Kunden/Admins koennen keine Aufstufung anfragen.
        if ('ROLE_USER' !== $user->getRole()) {
            $this->addFlash('danger', 'Eine Aufstufung ist fuer dein Konto nicht moeglich.');

            return $this->redirectBack($request);
        }

        if ($user->isUpgradeRequested()) {
            $this->addFlash('info', 'Deine Aufstufungsanfrage liegt bereits vor.');

            return $this->redirectBack($request);
        }

        $user->setUpgradeRequested(true);
        $entityManager->flush();

        $this->addFlash('success', 'Deine Aufstufungsanfrage wurde gesendet.');

        return $this->redirectBack($request);
    }

    /**
     * Leitet zurueck zur vorherigen Seite (Navbar ist ueberall sichtbar),
     * faellt sonst auf das Dashboard zurueck.
     */
    private function redirectBack(Request $request): Response
    {
        $referer = $request->headers->get('referer');

        return $referer
            ? $this->redirect($referer)
            : $this->redirectToRoute('dashboard');
    }
}