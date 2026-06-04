<?php

declare(strict_types=1);

namespace App\Controller;

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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class TransactionController extends AbstractController
{
    #[Route('/transactions', name: 'transactions_index', methods: ['GET'])]
    public function index(
        Request $request,
        AuthenticatedUserResolver $authenticatedUsers,
        AccountRepository $accounts,
        CategoryRepository $categories,
        PaymentMethodRepository $paymentMethods,
        TransactionRepository $transactions
    ): Response {
        $user = $authenticatedUsers->resolve($request);

        if (!$user instanceof User) {
            return $this->redirectToRoute('login');
        }

        return $this->render('transaction/index.html.twig', [
            'title' => 'Transactions',
            'isAuthenticated' => true,
            'userFirstName' => $user->getFirstName(),
            'accounts' => $accounts->findForUser($user),
            'categories' => $categories->findForUser($user),
            'paymentMethods' => $paymentMethods->findForUser($user),
            'transactions' => $transactions->findForUser($user),
        ]);
    }

    #[Route('/transactions', name: 'transactions_create', methods: ['POST'])]
    public function create(
        Request $request,
        AuthenticatedUserResolver $authenticatedUsers,
        AccountRepository $accounts,
        CategoryRepository $categories,
        PaymentMethodRepository $paymentMethods,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $authenticatedUsers->resolve($request);

        if (!$user instanceof User) {
            return $this->redirectToRoute('login');
        }

        $account = $this->findOwnedAccount((int) $request->request->get('accountId', 0), $user, $accounts);

        if ($account instanceof Account) {
            $transaction = (new Transaction())
                ->setUser($user)
                ->setAccount($account);

            $this->fillTransactionFromRequest($transaction, $request, $user, $categories, $paymentMethods);
            $entityManager->persist($transaction);
            $entityManager->flush();
        }

        return $this->redirectToRoute('transactions_index');
    }

    #[Route('/transactions/{id}/update', name: 'transactions_update', methods: ['POST'])]
    public function update(
        int $id,
        Request $request,
        AuthenticatedUserResolver $authenticatedUsers,
        TransactionRepository $transactions,
        AccountRepository $accounts,
        CategoryRepository $categories,
        PaymentMethodRepository $paymentMethods,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $authenticatedUsers->resolve($request);
        $transaction = $transactions->find($id);

        if (!$user instanceof User) {
            return $this->redirectToRoute('login');
        }

        if ($transaction instanceof Transaction && $transaction->getUser()?->getId() === $user->getId()) {
            $account = $this->findOwnedAccount((int) $request->request->get('accountId', 0), $user, $accounts);

            if ($account instanceof Account) {
                $transaction->setAccount($account);
            }

            $this->fillTransactionFromRequest($transaction, $request, $user, $categories, $paymentMethods);
            $entityManager->flush();
        }

        return $this->redirectToRoute('transactions_index');
    }

    #[Route('/transactions/{id}/delete', name: 'transactions_delete', methods: ['POST'])]
    public function delete(
        int $id,
        Request $request,
        AuthenticatedUserResolver $authenticatedUsers,
        TransactionRepository $transactions,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $authenticatedUsers->resolve($request);
        $transaction = $transactions->find($id);

        if (!$user instanceof User) {
            return $this->redirectToRoute('login');
        }

        if ($transaction instanceof Transaction && $transaction->getUser()?->getId() === $user->getId()) {
            $entityManager->remove($transaction);
            $entityManager->flush();
        }

        return $this->redirectToRoute('transactions_index');
    }

    private function fillTransactionFromRequest(
        Transaction $transaction,
        Request $request,
        User $user,
        CategoryRepository $categories,
        PaymentMethodRepository $paymentMethods
    ): void {
        $label = trim((string) $request->request->get('label', ''));

        if ($label !== '') {
            $transaction->setLabel($label);
        }

        $transaction->setAmount((string) $request->request->get('amount', '0'));
        $transaction->setType((string) $request->request->get('type', Transaction::TYPE_EXPENSE));
        $transaction->setNote((string) $request->request->get('note', ''));

        $executedAt = (string) $request->request->get('executedAt', date('Y-m-d'));
        try {
            $transaction->setExecutedAt(new \DateTimeImmutable($executedAt));
        } catch (\Exception $exception) {
            $transaction->setExecutedAt(new \DateTimeImmutable());
        }

        $category = $this->findOwnedCategory((int) $request->request->get('categoryId', 0), $user, $categories);
        $paymentMethod = $this->findOwnedPaymentMethod((int) $request->request->get('paymentMethodId', 0), $user, $paymentMethods);

        $transaction->setCategory($category);
        $transaction->setPaymentMethod($paymentMethod);
    }

    private function findOwnedAccount(int $id, User $user, AccountRepository $accounts): ?Account
    {
        $account = $accounts->find($id);

        return $account instanceof Account && $account->getUser()?->getId() === $user->getId() ? $account : null;
    }

    private function findOwnedCategory(int $id, User $user, CategoryRepository $categories): ?Category
    {
        $category = $categories->find($id);

        return $category instanceof Category && $category->getUser()?->getId() === $user->getId() ? $category : null;
    }

    private function findOwnedPaymentMethod(int $id, User $user, PaymentMethodRepository $paymentMethods): ?PaymentMethod
    {
        $paymentMethod = $paymentMethods->find($id);

        return $paymentMethod instanceof PaymentMethod && $paymentMethod->getUser()?->getId() === $user->getId() ? $paymentMethod : null;
    }
}
