<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

final class FeatureApiController extends AbstractController
{
    #[Route('/api/features', name: 'api_features', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return $this->json([
            [
                'id' => 1,
                'title' => 'Vos finances en un coup d\'oeil',
                'description' => 'Consultez vos comptes, vos soldes et vos mouvements depuis un tableau de bord simple.',
                'icon' => 'icon-chat.png',
            ],
            [
                'id' => 2,
                'title' => 'Des categories personnalisables',
                'description' => 'Organisez vos revenus et depenses par categorie pour comprendre ou part votre argent.',
                'icon' => 'icon-money.png',
            ],
            [
                'id' => 3,
                'title' => 'Des bilans annuels lisibles',
                'description' => 'Suivez l\'evolution de vos revenus, depenses et soldes sur toute l\'annee.',
                'icon' => 'icon-security.png',
            ],
        ]);
    }
}
