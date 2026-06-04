<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Category;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Service\AuthenticatedUserResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/categories')]
final class CategoryApiController extends AbstractController
{
    #[Route('', name: 'api_categories_index', methods: ['GET'])]
    public function index(
        Request $request,
        AuthenticatedUserResolver $authenticatedUsers,
        CategoryRepository $categories
    ): JsonResponse {
        $user = $authenticatedUsers->resolve($request);

        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        return $this->json([
            'message' => 'Categories recuperees avec succes',
            'body' => array_map(static fn (Category $category): array => $category->toApiPayload(), $categories->findForUser($user)),
        ]);
    }

    #[Route('', name: 'api_categories_create', methods: ['POST'])]
    public function create(
        Request $request,
        AuthenticatedUserResolver $authenticatedUsers,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $user = $authenticatedUsers->resolve($request);

        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        $payload = $this->requestPayload($request);
        $name = trim((string) ($payload['name'] ?? ''));

        if ($name === '') {
            return $this->badRequest('Le nom de la categorie est obligatoire');
        }

        $category = (new Category())
            ->setUser($user)
            ->setName($name)
            ->setColor((string) ($payload['color'] ?? '#00bc77'));

        $entityManager->persist($category);
        $entityManager->flush();

        return $this->json([
            'message' => 'Categorie creee avec succes',
            'body' => $category->toApiPayload(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_categories_update', methods: ['PUT', 'PATCH'])]
    public function update(
        int $id,
        Request $request,
        AuthenticatedUserResolver $authenticatedUsers,
        CategoryRepository $categories,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $user = $authenticatedUsers->resolve($request);
        $category = $this->findUserCategory($id, $user, $categories);

        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        if (!$category instanceof Category) {
            return $this->notFound('Categorie introuvable');
        }

        $payload = $this->requestPayload($request);

        if (isset($payload['name'])) {
            $category->setName((string) $payload['name']);
        }

        if (isset($payload['color'])) {
            $category->setColor((string) $payload['color']);
        }

        $entityManager->flush();

        return $this->json([
            'message' => 'Categorie mise a jour avec succes',
            'body' => $category->toApiPayload(),
        ]);
    }

    #[Route('/{id}', name: 'api_categories_delete', methods: ['DELETE'])]
    public function delete(
        int $id,
        Request $request,
        AuthenticatedUserResolver $authenticatedUsers,
        CategoryRepository $categories,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $user = $authenticatedUsers->resolve($request);
        $category = $this->findUserCategory($id, $user, $categories);

        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        if (!$category instanceof Category) {
            return $this->notFound('Categorie introuvable');
        }

        $entityManager->remove($category);
        $entityManager->flush();

        return $this->json(['message' => 'Categorie supprimee avec succes']);
    }

    private function findUserCategory(int $id, ?User $user, CategoryRepository $categories): ?Category
    {
        if (!$user instanceof User) {
            return null;
        }

        $category = $categories->find($id);

        return $category instanceof Category && $category->getUser()?->getId() === $user->getId() ? $category : null;
    }

    private function requestPayload(Request $request): array
    {
        if ($request->getContent() !== '') {
            $payload = json_decode($request->getContent(), true);

            if (is_array($payload)) {
                return $payload;
            }
        }

        return $request->request->all();
    }

    private function unauthorized(): JsonResponse
    {
        return $this->json(['message' => 'Session invalide ou absente'], Response::HTTP_UNAUTHORIZED);
    }

    private function notFound(string $message): JsonResponse
    {
        return $this->json(['message' => $message], Response::HTTP_NOT_FOUND);
    }

    private function badRequest(string $message): JsonResponse
    {
        return $this->json(['message' => $message], Response::HTTP_BAD_REQUEST);
    }
}
