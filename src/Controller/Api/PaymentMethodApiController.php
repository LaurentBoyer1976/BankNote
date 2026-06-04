<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\PaymentMethod;
use App\Entity\User;
use App\Repository\PaymentMethodRepository;
use App\Service\AuthenticatedUserResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/payment-methods')]
final class PaymentMethodApiController extends AbstractController
{
    #[Route('', name: 'api_payment_methods_index', methods: ['GET'])]
    public function index(
        Request $request,
        AuthenticatedUserResolver $authenticatedUsers,
        PaymentMethodRepository $paymentMethods
    ): JsonResponse {
        $user = $authenticatedUsers->resolve($request);

        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        return $this->json([
            'message' => 'Moyens de paiement recuperes avec succes',
            'body' => array_map(static fn (PaymentMethod $paymentMethod): array => $paymentMethod->toApiPayload(), $paymentMethods->findForUser($user)),
        ]);
    }

    #[Route('', name: 'api_payment_methods_create', methods: ['POST'])]
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
            return $this->badRequest('Le nom du moyen de paiement est obligatoire');
        }

        $paymentMethod = (new PaymentMethod())
            ->setUser($user)
            ->setName($name)
            ->setType((string) ($payload['type'] ?? 'card'));

        $entityManager->persist($paymentMethod);
        $entityManager->flush();

        return $this->json([
            'message' => 'Moyen de paiement cree avec succes',
            'body' => $paymentMethod->toApiPayload(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_payment_methods_update', methods: ['PUT', 'PATCH'])]
    public function update(
        int $id,
        Request $request,
        AuthenticatedUserResolver $authenticatedUsers,
        PaymentMethodRepository $paymentMethods,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $user = $authenticatedUsers->resolve($request);
        $paymentMethod = $this->findUserPaymentMethod($id, $user, $paymentMethods);

        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        if (!$paymentMethod instanceof PaymentMethod) {
            return $this->notFound('Moyen de paiement introuvable');
        }

        $payload = $this->requestPayload($request);

        if (isset($payload['name'])) {
            $paymentMethod->setName((string) $payload['name']);
        }

        if (isset($payload['type'])) {
            $paymentMethod->setType((string) $payload['type']);
        }

        $entityManager->flush();

        return $this->json([
            'message' => 'Moyen de paiement mis a jour avec succes',
            'body' => $paymentMethod->toApiPayload(),
        ]);
    }

    #[Route('/{id}', name: 'api_payment_methods_delete', methods: ['DELETE'])]
    public function delete(
        int $id,
        Request $request,
        AuthenticatedUserResolver $authenticatedUsers,
        PaymentMethodRepository $paymentMethods,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $user = $authenticatedUsers->resolve($request);
        $paymentMethod = $this->findUserPaymentMethod($id, $user, $paymentMethods);

        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        if (!$paymentMethod instanceof PaymentMethod) {
            return $this->notFound('Moyen de paiement introuvable');
        }

        $entityManager->remove($paymentMethod);
        $entityManager->flush();

        return $this->json(['message' => 'Moyen de paiement supprime avec succes']);
    }

    private function findUserPaymentMethod(int $id, ?User $user, PaymentMethodRepository $paymentMethods): ?PaymentMethod
    {
        if (!$user instanceof User) {
            return null;
        }

        $paymentMethod = $paymentMethods->find($id);

        return $paymentMethod instanceof PaymentMethod && $paymentMethod->getUser()?->getId() === $user->getId() ? $paymentMethod : null;
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
