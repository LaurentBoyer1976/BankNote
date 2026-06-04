<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\AccountRepository;
use App\Repository\CategoryRepository;
use App\Repository\PaymentMethodRepository;
use App\Repository\TransactionRepository;
use App\Repository\UserRepository;
use App\Service\AuthenticatedUserResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

final class UserController extends AbstractController
{
    #[Route('/dashboard', name: 'dashboard')]
    public function dashboard(
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

        $userAccounts = $accounts->findForUser($user);
        $userTransactions = $transactions->findForUser($user);
        $currentYear = (int) date('Y');
        $totalBalance = 0.0;
        $annualIncome = 0.0;
        $annualExpense = 0.0;

        foreach ($userAccounts as $account) {
            $totalBalance += (float) $account->getAmount();
        }

        foreach ($userTransactions as $transaction) {
            if ((int) $transaction->getExecutedAt()->format('Y') !== $currentYear) {
                continue;
            }

            if ($transaction->getType() === 'income') {
                $annualIncome += (float) $transaction->getAmount();
            } else {
                $annualExpense += (float) $transaction->getAmount();
            }
        }

        return $this->render('dashboard/index.html.twig', [
            'title' => 'Tableau de bord',
            'isAuthenticated' => true,
            'userFirstName' => $user->getFirstName(),
            'userLastName' => $user->getLastName(),
            'accounts' => array_map(static fn ($account): array => $account->toApiPayload(), $userAccounts),
            'dashboard' => [
                'accountCount' => count($userAccounts),
                'transactionCount' => count($userTransactions),
                'categoryCount' => count($categories->findForUser($user)),
                'paymentMethodCount' => count($paymentMethods->findForUser($user)),
                'totalBalance' => $totalBalance,
                'annualIncome' => $annualIncome,
                'annualExpense' => $annualExpense,
                'annualBalance' => $annualIncome - $annualExpense,
                'year' => $currentYear,
            ],
        ]);
    }

    #[Route('/user', name: 'user_profile', methods: ['GET'])]
    public function profile(Request $request, AuthenticatedUserResolver $authenticatedUsers): Response
    {
        $user = $authenticatedUsers->resolve($request);

        if (!$user instanceof User) {
            return $this->redirectToRoute('login');
        }

        return $this->renderProfile(
            $user,
            [],
            null,
            $request->query->getBoolean('saved') ? 'Profil mis a jour.' : null
        );
    }

    #[Route('/user/profile', name: 'user_profile_update', methods: ['POST'])]
    public function updateProfile(
        Request $request,
        AuthenticatedUserResolver $authenticatedUsers,
        UserRepository $users,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $authenticatedUsers->resolve($request);

        if (!$user instanceof User) {
            return $this->redirectToRoute('login');
        }

        $firstName = trim((string) $request->request->get('firstName', ''));
        $lastName = trim((string) $request->request->get('lastName', ''));
        $email = trim((string) $request->request->get('email', ''));
        $address = trim((string) $request->request->get('address', ''));
        $phone = trim((string) $request->request->get('phone', ''));
        $themeMode = (string) $request->request->get('themeMode', 'light');
        $accentColor = (string) $request->request->get('accentColor', '#00bc77');
        $plainPassword = (string) $request->request->get('password', '');
        $submittedValues = [
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $email,
            'address' => $address,
            'phone' => $phone,
            'themeMode' => $themeMode,
            'accentColor' => $accentColor,
        ];

        if ($firstName === '' || $lastName === '' || $email === '') {
            return $this->renderProfile(
                $user,
                $submittedValues,
                'Le prenom, le nom et l email sont obligatoires.',
                null,
                Response::HTTP_BAD_REQUEST
            );
        }

        $existingUser = $users->findOneByEmail($email);

        if ($existingUser instanceof User && $existingUser->getId() !== $user->getId()) {
            return $this->renderProfile(
                $user,
                $submittedValues,
                'Cet email est deja utilise par un autre compte.',
                null,
                Response::HTTP_BAD_REQUEST
            );
        }

        $user
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setEmail($email)
            ->setAddress($address)
            ->setPhone($phone)
            ->setThemeMode($themeMode)
            ->setAccentColor($accentColor);

        if ($plainPassword !== '') {
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
        }

        $entityManager->flush();

        return $this->redirectToRoute('user_profile', ['saved' => 1]);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function renderProfile(
        User $user,
        array $values = [],
        ?string $error = null,
        ?string $success = null,
        int $statusCode = Response::HTTP_OK
    ): Response {
        $profile = array_merge($user->toProfilePayload(), $values);

        return $this->render('user/index.html.twig', [
            'title' => 'Profil',
            'isAuthenticated' => true,
            'userFirstName' => $user->getFirstName(),
            'userLastName' => $user->getLastName(),
            'profile' => $profile,
            'error' => $error,
            'success' => $success,
            'accentColors' => [
                ['label' => 'Vert Bank Note', 'value' => '#00bc77'],
                ['label' => 'Vert fonce', 'value' => '#00451a'],
                ['label' => 'Bleu budget', 'value' => '#2563eb'],
                ['label' => 'Orange alerte', 'value' => '#f97316'],
                ['label' => 'Violet suivi', 'value' => '#7c3aed'],
                ['label' => 'Rouge depense', 'value' => '#dc2626'],
            ],
        ], new Response('', $statusCode));
    }
}
