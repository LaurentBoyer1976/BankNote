<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Account;
use App\Entity\Category;
use App\Entity\PaymentMethod;
use App\Entity\Transaction;
use App\Entity\User;
use App\Repository\AccountRepository;
use App\Repository\CategoryRepository;
use App\Repository\PaymentMethodRepository;
use App\Repository\TransactionRepository;
use App\Service\AuthenticatedUserResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/transactions')]
final class TransactionApiController extends AbstractController
{
    #[Route('', name: 'api_transactions_index', methods: ['GET'])]
    public function index(
        Request $request,
        AuthenticatedUserResolver $authenticatedUsers,
        TransactionRepository $transactions
    ): JsonResponse {
        $user = $authenticatedUsers->resolve($request);

        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        return $this->json([
            'message' => 'Transactions recuperees avec succes',
            'body' => array_map(static fn (Transaction $transaction): array => $transaction->toApiPayload(), $transactions->findForUser($user)),
        ]);
    }

    #[Route('', name: 'api_transactions_create', methods: ['POST'])]
    public function create(
        Request $request,
        AuthenticatedUserResolver $authenticatedUsers,
        AccountRepository $accounts,
        CategoryRepository $categories,
        PaymentMethodRepository $paymentMethods,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $user = $authenticatedUsers->resolve($request);

        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        $payload = $this->requestPayload($request);
        $account = $this->findUserAccount((int) ($payload['accountId'] ?? 0), $user, $accounts);

        if (!$account instanceof Account) {
            return $this->badRequest('Un accountId valide est obligatoire');
        }

        $transaction = (new Transaction())
            ->setUser($user)
            ->setAccount($account);

        $error = $this->fillTransaction($transaction, $payload, $user, $categories, $paymentMethods);

        if ($error !== null) {
            return $this->badRequest($error);
        }

        $entityManager->persist($transaction);
        $entityManager->flush();

        return $this->json([
            'message' => 'Transaction creee avec succes',
            'body' => $transaction->toApiPayload(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_transactions_update', methods: ['PUT', 'PATCH'])]
    public function update(
        int $id,
        Request $request,
        AuthenticatedUserResolver $authenticatedUsers,
        TransactionRepository $transactions,
        AccountRepository $accounts,
        CategoryRepository $categories,
        PaymentMethodRepository $paymentMethods,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $user = $authenticatedUsers->resolve($request);
        $transaction = $this->findUserTransaction($id, $user, $transactions);

        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        if (!$transaction instanceof Transaction) {
            return $this->notFound('Transaction introuvable');
        }

        $payload = $this->requestPayload($request);

        if (isset($payload['accountId'])) {
            $account = $this->findUserAccount((int) $payload['accountId'], $user, $accounts);

            if (!$account instanceof Account) {
                return $this->badRequest('Un accountId valide est obligatoire');
            }

            $transaction->setAccount($account);
        }

        $error = $this->fillTransaction($transaction, $payload, $user, $categories, $paymentMethods, false);

        if ($error !== null) {
            return $this->badRequest($error);
        }

        $entityManager->flush();

        return $this->json([
            'message' => 'Transaction mise a jour avec succes',
            'body' => $transaction->toApiPayload(),
        ]);
    }

    #[Route('/{id}', name: 'api_transactions_delete', methods: ['DELETE'])]
    public function delete(
        int $id,
        Request $request,
        AuthenticatedUserResolver $authenticatedUsers,
        TransactionRepository $transactions,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $user = $authenticatedUsers->resolve($request);
        $transaction = $this->findUserTransaction($id, $user, $transactions);

        if (!$user instanceof User) {
            return $this->unauthorized();
        }

        if (!$transaction instanceof Transaction) {
            return $this->notFound('Transaction introuvable');
        }

        $entityManager->remove($transaction);
        $entityManager->flush();

        return $this->json(['message' => 'Transaction supprimee avec succes']);
    }

    private function fillTransaction(
        Transaction $transaction,
        array $payload,
        User $user,
        CategoryRepository $categories,
        PaymentMethodRepository $paymentMethods,
        bool $requireCoreFields = true
    ): ?string {
        if ($requireCoreFields && trim((string) ($payload['label'] ?? '')) === '') {
            return 'Le libelle de la transaction est obligatoire';
        }

        if (isset($payload['label'])) {
            $transaction->setLabel((string) $payload['label']);
        }

        if ($requireCoreFields && !isset($payload['amount'])) {
            return 'Le montant de la transaction est obligatoire';
        }

        if (isset($payload['amount'])) {
            $transaction->setAmount((string) $payload['amount']);
        }

        if (isset($payload['type'])) {
            $transaction->setType((string) $payload['type']);
        }

        if (isset($payload['executedAt'])) {
            try {
                $transaction->setExecutedAt(new \DateTimeImmutable((string) $payload['executedAt']));
            } catch (\Exception $exception) {
                return 'La date de la transaction est invalide';
            }
        }

        if (array_key_exists('note', $payload)) {
            $transaction->setNote($payload['note'] !== null ? (string) $payload['note'] : null);
        }

        if (array_key_exists('categoryId', $payload)) {
            $category = $payload['categoryId'] !== null
                ? $this->findUserCategory((int) $payload['categoryId'], $user, $categories)
                : null;

            if ($payload['categoryId'] !== null && !$category instanceof Category) {
                return 'Categorie introuvable';
            }

            $transaction->setCategory($category);
        }

        if (array_key_exists('paymentMethodId', $payload)) {
            $paymentMethod = $payload['paymentMethodId'] !== null
                ? $this->findUserPaymentMethod((int) $payload['paymentMethodId'], $user, $paymentMethods)
                : null;

            if ($payload['paymentMethodId'] !== null && !$paymentMethod instanceof PaymentMethod) {
                return 'Moyen de paiement introuvable';
            }

            $transaction->setPaymentMethod($paymentMethod);
        }

        return null;
    }

    private function findUserTransaction(int $id, ?User $user, TransactionRepository $transactions): ?Transaction
    {
        if (!$user instanceof User) {
            return null;
        }

        $transaction = $transactions->find($id);

        return $transaction instanceof Transaction && $transaction->getUser()?->getId() === $user->getId() ? $transaction : null;
    }

    private function findUserAccount(int $id, User $user, AccountRepository $accounts): ?Account
    {
        $account = $accounts->find($id);

        return $account instanceof Account && $account->getUser()?->getId() === $user->getId() ? $account : null;
    }

    private function findUserCategory(int $id, User $user, CategoryRepository $categories): ?Category
    {
        $category = $categories->find($id);

        return $category instanceof Category && $category->getUser()?->getId() === $user->getId() ? $category : null;
    }

    private function findUserPaymentMethod(int $id, User $user, PaymentMethodRepository $paymentMethods): ?PaymentMethod
    {
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
