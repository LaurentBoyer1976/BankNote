<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Transaction;
use App\Entity\User;
use App\Repository\TransactionRepository;
use App\Service\AuthenticatedUserResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/statistics')]
final class StatisticsApiController extends AbstractController
{
    #[Route('/annual', name: 'api_statistics_annual', methods: ['GET'])]
    public function annual(
        Request $request,
        AuthenticatedUserResolver $authenticatedUsers,
        TransactionRepository $transactions
    ): JsonResponse {
        $user = $authenticatedUsers->resolve($request);

        if (!$user instanceof User) {
            return $this->json(['message' => 'Session invalide ou absente'], Response::HTTP_UNAUTHORIZED);
        }

        $year = (int) $request->query->get('year', date('Y'));
        $summary = [
            'year' => $year,
            'income' => 0.0,
            'expense' => 0.0,
            'balance' => 0.0,
            'byMonth' => [],
            'byCategory' => [],
        ];

        for ($month = 1; $month <= 12; ++$month) {
            $summary['byMonth'][sprintf('%04d-%02d', $year, $month)] = [
                'income' => 0.0,
                'expense' => 0.0,
                'balance' => 0.0,
            ];
        }

        foreach ($transactions->findForUser($user) as $transaction) {
            if ((int) $transaction->getExecutedAt()->format('Y') !== $year) {
                continue;
            }

            $amount = (float) $transaction->getAmount();
            $monthKey = $transaction->getExecutedAt()->format('Y-m');
            $categoryName = $transaction->getCategory()?->getName() ?? 'Sans categorie';

            if ($transaction->getType() === Transaction::TYPE_INCOME) {
                $summary['income'] += $amount;
                $summary['byMonth'][$monthKey]['income'] += $amount;
            } else {
                $summary['expense'] += $amount;
                $summary['byMonth'][$monthKey]['expense'] += $amount;
            }

            $summary['byCategory'][$categoryName] ??= [
                'income' => 0.0,
                'expense' => 0.0,
                'balance' => 0.0,
            ];

            if ($transaction->getType() === Transaction::TYPE_INCOME) {
                $summary['byCategory'][$categoryName]['income'] += $amount;
            } else {
                $summary['byCategory'][$categoryName]['expense'] += $amount;
            }
        }

        $summary['balance'] = $summary['income'] - $summary['expense'];

        foreach ($summary['byMonth'] as &$month) {
            $month['balance'] = $month['income'] - $month['expense'];
        }
        unset($month);

        foreach ($summary['byCategory'] as &$category) {
            $category['balance'] = $category['income'] - $category['expense'];
        }
        unset($category);

        return $this->json([
            'message' => 'Bilan annuel recupere avec succes',
            'body' => $summary,
        ]);
    }
}
