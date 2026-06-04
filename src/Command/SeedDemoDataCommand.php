<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Account;
use App\Entity\Category;
use App\Entity\PaymentMethod;
use App\Entity\Transaction;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\PaymentMethodRepository;
use App\Repository\TransactionRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SeedDemoDataCommand extends Command
{
    protected static $defaultName = 'app:seed-demo-data';

    private EntityManagerInterface $entityManager;
    private UserRepository $users;
    private CategoryRepository $categories;
    private PaymentMethodRepository $paymentMethods;
    private TransactionRepository $transactions;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserRepository $users,
        CategoryRepository $categories,
        PaymentMethodRepository $paymentMethods,
        TransactionRepository $transactions,
        UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->users = $users;
        $this->categories = $categories;
        $this->paymentMethods = $paymentMethods;
        $this->transactions = $transactions;
        $this->passwordHasher = $passwordHasher;
    }

    protected function configure(): void
    {
        $this->setDescription('Seed demo users, accounts and budget data.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tony = $this->upsertUser('Tony', 'Stark', 'tony@stark.com', 'password123');
        $steve = $this->upsertUser('Steve', 'Rogers', 'steve@rogers.com', 'password456');

        $this->seedAccounts($tony, [
            ['Compte courant (x8349)', '2082.79', 'Solde disponible', '8349'],
            ['Compte epargne (x6712)', '10928.42', 'Solde disponible', '6712'],
            ['Carte de credit (x8349)', '184.30', 'Solde actuel', '8349'],
        ]);

        $this->seedAccounts($steve, [
            ['Compte courant principal (x1234)', '3500.00', 'Solde disponible', '1234'],
            ['Compte epargne projet (x5678)', '15000.00', 'Solde disponible', '5678'],
            ['Carte de credit recompenses (x9101)', '500.00', 'Solde actuel', '9101'],
        ]);

        $this->seedBudgetData($tony);
        $this->seedBudgetData($steve);

        $this->entityManager->flush();
        $output->writeln('Demo data seeded.');

        return Command::SUCCESS;
    }

    private function upsertUser(string $firstName, string $lastName, string $email, string $plainPassword): User
    {
        $user = $this->users->findOneByEmail($email) ?? new User();
        $user->setEmail($email);
        $user->setFirstName($firstName);
        $user->setLastName($lastName);
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        $this->entityManager->persist($user);

        return $user;
    }

    /**
     * @param array<int, array{0:string, 1:string, 2:string, 3:string}> $accounts
     */
    private function seedAccounts(User $user, array $accounts): void
    {
        $existingAccounts = array_values($user->getAccounts()->toArray());

        foreach ($accounts as $index => [$title, $amount, $description, $accountNumber]) {
            $account = $existingAccounts[$index] ?? null;

            if (!$account instanceof Account) {
                $account = new Account();
                $user->addAccount($account);
            }

            $account
                ->setTitle($title)
                ->setAmount($amount)
                ->setCurrency('EUR')
                ->setDescription($description)
                ->setAccountNumber($accountNumber);

            $this->entityManager->persist($account);
        }
    }

    private function seedBudgetData(User $user): void
    {
        $groceries = $this->upsertCategory($user, 'Courses', '#00bc77');
        $salary = $this->upsertCategory($user, 'Salaire', '#3366cc');
        $card = $this->upsertPaymentMethod($user, 'Carte bancaire', 'card');
        $transfer = $this->upsertPaymentMethod($user, 'Virement', 'transfer');

        if ($this->transactions->findForUser($user) !== []) {
            return;
        }

        $account = $user->getAccounts()->first();

        if (!$account instanceof Account) {
            return;
        }

        $this->entityManager->persist(
            (new Transaction())
                ->setUser($user)
                ->setAccount($account)
                ->setCategory($salary)
                ->setPaymentMethod($transfer)
                ->setLabel('Salaire mensuel')
                ->setAmount('2500.00')
                ->setType(Transaction::TYPE_INCOME)
                ->setExecutedAt(new \DateTimeImmutable('first day of this month'))
        );

        $this->entityManager->persist(
            (new Transaction())
                ->setUser($user)
                ->setAccount($account)
                ->setCategory($groceries)
                ->setPaymentMethod($card)
                ->setLabel('Courses alimentaires')
                ->setAmount('86.40')
                ->setType(Transaction::TYPE_EXPENSE)
                ->setExecutedAt(new \DateTimeImmutable('yesterday'))
        );
    }

    private function upsertCategory(User $user, string $name, string $color): Category
    {
        $category = $this->categories->findOneBy(['user' => $user, 'name' => $name]) ?? new Category();
        $category->setUser($user);
        $category->setName($name);
        $category->setColor($color);
        $this->entityManager->persist($category);

        return $category;
    }

    private function upsertPaymentMethod(User $user, string $name, string $type): PaymentMethod
    {
        $paymentMethod = $this->paymentMethods->findOneBy(['user' => $user, 'name' => $name]) ?? new PaymentMethod();
        $paymentMethod->setUser($user);
        $paymentMethod->setName($name);
        $paymentMethod->setType($type);
        $this->entityManager->persist($paymentMethod);

        return $paymentMethod;
    }
}
