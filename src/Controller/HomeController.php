<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AuthenticatedUserResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(Request $request, AuthenticatedUserResolver $authenticatedUsers): Response
    {
        $user = $authenticatedUsers->resolve($request);

        return $this->render('home/index.html.twig', [
            'title' => 'Bank Note',
            'isAuthenticated' => $user !== null,
            'userFirstName' => $user?->getFirstName(),
        ]);
    }
}
