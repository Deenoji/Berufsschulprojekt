<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Survey;
use App\Entity\SurveyHistory;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'dashboard')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        $surveyRepo = $entityManager->getRepository(Survey::class);
        $historyRepo = $entityManager->getRepository(SurveyHistory::class);
        $userRepo = $entityManager->getRepository(User::class);

        $categoryMap = [];
        foreach ($entityManager->getRepository(Category::class)->findAll() as $category) {
            $categoryMap[$category->getId()] = $category->getTitle();
        }

        // Alle Umfragen (fuer Statistik), neueste 5 fuer die Liste.
        $allSurveys = $surveyRepo->findBy([], ['id' => 'DESC']);
        $surveys = array_slice($allSurveys, 0, 5);

        $now = new \DateTime();

        // Stimmen je Umfrage + abgeleitete Werte.
        $openCount = 0;
        $totalVotes = 0;
        $surveyVotes = [];
        $mostVoted = null;
        $leastVoted = null;

        foreach ($allSurveys as $survey) {
            $count = $historyRepo->count(['survey_id' => $survey->getId()]);
            $surveyVotes[$survey->getId()] = $count;
            $totalVotes += $count;

            $isActive = 'ACTIVE' === $survey->getStatus()
                && (null === $survey->getEndDate() || $survey->getEndDate() >= $now);
            if ($isActive) {
                ++$openCount;
            }

            if (null === $mostVoted || $count > $mostVoted['votes']) {
                $mostVoted = ['title' => $survey->getTitle(), 'votes' => $count];
            }
            if (null === $leastVoted || $count < $leastVoted['votes']) {
                $leastVoted = ['title' => $survey->getTitle(), 'votes' => $count];
            }
        }

        // Meta fuer die angezeigten 5 Umfragen.
        $surveyMeta = [];
        foreach ($surveys as $survey) {
            $surveyMeta[$survey->getId()] = [
                'category' => $categoryMap[$survey->getCategoryId()] ?? 'Allgemein',
                'is_active' => 'ACTIVE' === $survey->getStatus()
                    && (null === $survey->getEndDate() || $survey->getEndDate() >= $now),
                'votes' => $surveyVotes[$survey->getId()] ?? 0,
            ];
        }

        // Statistik-Kennzahlen.
        $stats = [
            'surveys_total' => count($allSurveys),
            'surveys_open' => $openCount,
            'users_total' => $userRepo->count([]),
            'users_customers' => $userRepo->count(['role' => 'ROLE_CUSTOMER']),
            'users_guests' => $userRepo->count(['role' => 'ROLE_USER']),
            'votes_total' => $totalVotes,
            'most_voted' => $mostVoted,
            'least_voted' => $leastVoted,
        ];

        // Top 10 Voter.
        $topUsers = $userRepo->findBy([], ['voteCount' => 'DESC'], 10);
        $leaderboard = [];
        foreach ($topUsers as $topUser) {
            $leaderboard[] = [
                'name' => $topUser->getDisplayName(),
                'email' => $topUser->getUserIdentifier(),
                'votes' => $topUser->getVoteCount(),
                'rank' => $this->rankName($topUser->getVoteCount()),
            ];
        }

        return $this->render('page/content/dashboard.html.twig', [
            'surveys' => $surveys,
            'survey_meta' => $surveyMeta,
            'leaderboard' => $leaderboard,
            'stats' => $stats,
        ]);
    }

    /**
     * Rang-Bezeichnung anhand der Vote-Anzahl (gleiche Leiter wie im AccountController).
     */
    private function rankName(int $votes): string
    {
        return match (true) {
            $votes >= 100 => 'Legende',
            $votes >= 50 => 'Meinungsmacher',
            $votes >= 30 => 'Profi-Voter',
            $votes >= 15 => 'Aktiver Voter',
            $votes >= 5 => 'Mitredner',
            default => 'Neuling',
        };
    }
}