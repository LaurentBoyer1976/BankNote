<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Account;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AuthenticatedUserResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

final class AuthController extends AbstractController
{
    #[Route('/register', name: 'register', methods: ['GET'])]
    public function register(Request $request, AuthenticatedUserResolver $authenticatedUsers): Response
    {
        $user = $authenticatedUsers->resolve($request);

        if ($user instanceof User) {
            return $this->redirectToRoute('dashboard');
        }

        return $this->render('auth/register.html.twig', [
            'title' => 'Creation de compte',
            'isAuthenticated' => false,
            'error' => null,
            'values' => [],
        ]);
    }

    #[Route('/register', name: 'register_submit', methods: ['POST'])]
    public function submitRegister(
        Request $request,
        UserRepository $users,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        AuthenticatedUserResolver $authenticatedUsers
    ): Response {
        $email = trim((string) $request->request->get('email', ''));
        $password = (string) $request->request->get('password', '');
        $firstName = trim((string) $request->request->get('firstName', ''));
        $lastName = trim((string) $request->request->get('lastName', ''));
        $values = [
            'email' => $email,
            'firstName' => $firstName,
            'lastName' => $lastName,
        ];

        if ($email === '' || $password === '' || $firstName === '' || $lastName === '') {
            return $this->render('auth/register.html.twig', [
                'title' => 'Creation de compte',
                'isAuthenticated' => false,
                'error' => 'Tous les champs sont obligatoires.',
                'values' => $values,
            ], new Response('', Response::HTTP_BAD_REQUEST));
        }

        if ($users->findOneByEmail($email) instanceof User) {
            return $this->render('auth/register.html.twig', [
                'title' => 'Creation de compte',
                'isAuthenticated' => false,
                'error' => 'Cet email est deja utilise.',
                'values' => $values,
            ], new Response('', Response::HTTP_BAD_REQUEST));
        }

        $user = (new User())
            ->setEmail($email)
            ->setFirstName($firstName)
            ->setLastName($lastName);
        $user->setPassword($passwordHasher->hashPassword($user, $password));

        $account = (new Account())
            ->setTitle('Compte principal')
            ->setAmount('0.00')
            ->setCurrency('EUR')
            ->setDescription('Solde disponible');
        $user->addAccount($account);

        $entityManager->persist($user);
        $entityManager->flush();
        $authenticatedUsers->login($user, $request);

        return $this->redirectToRoute('dashboard');
    }

    #[Route('/login', name: 'login', methods: ['GET'])]
    public function login(Request $request, AuthenticatedUserResolver $authenticatedUsers): Response
    {
        $user = $authenticatedUsers->resolve($request);

        if ($user instanceof User) {
            return $this->redirectToRoute('dashboard');
        }

        return $this->render('auth/login.html.twig', [
            'title' => 'Connexion',
            'isAuthenticated' => false,
            'error' => null,
            'lastEmail' => '',
        ]);
    }

    #[Route('/login', name: 'login_submit', methods: ['POST'])]
    public function submit(
        Request $request,
        UserRepository $users,
        UserPasswordHasherInterface $passwordHasher,
        AuthenticatedUserResolver $authenticatedUsers
    ): Response {
        $email = (string) $request->request->get('email', '');
        $password = (string) $request->request->get('password', '');
        $user = $users->findOneByEmail($email);

        if (!$user instanceof User || !$passwordHasher->isPasswordValid($user, $password)) {
            return $this->render('auth/login.html.twig', [
                'title' => 'Connexion',
                'isAuthenticated' => false,
                'error' => 'Email ou mot de passe incorrect.',
                'lastEmail' => $email,
            ], new Response('', Response::HTTP_UNAUTHORIZED));
        }

        $authenticatedUsers->login($user, $request);

        return $this->redirectToRoute('dashboard');
    }

    #[Route('/logout', name: 'logout', methods: ['GET', 'POST'])]
    public function logout(Request $request, AuthenticatedUserResolver $authenticatedUsers): RedirectResponse
    {
        $authenticatedUsers->logout($request);

        return $this->redirectToRoute('home');
    }
}
