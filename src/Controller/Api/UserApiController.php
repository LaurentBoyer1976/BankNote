<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\AccountRepository;
use App\Repository\UserRepository;
use App\Service\AuthenticatedUserResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/v1/user')]
final class UserApiController extends AbstractController
{
    #[Route('/signup', name: 'api_user_signup', methods: ['POST'])]
    public function signup(
        Request $request,
        UserRepository $users,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $payload = $this->requestPayload($request);
        $email = (string) ($payload['email'] ?? '');
        $password = (string) ($payload['password'] ?? '');
        $firstName = (string) ($payload['firstName'] ?? '');
        $lastName = (string) ($payload['lastName'] ?? '');

        if ($email === '' || $password === '' || $firstName === '' || $lastName === '') {
            return $this->legacyError('Les champs d\'inscription sont incomplets');
        }

        if ($users->findOneByEmail($email) instanceof User) {
            return $this->legacyError('Cet email existe deja');
        }

        $user = (new User())
            ->setEmail($email)
            ->setFirstName($firstName)
            ->setLastName($lastName);
        $user->setPassword($passwordHasher->hashPassword($user, $password));

        $entityManager->persist($user);
        $entityManager->flush();

        return $this->json([
            'status' => Response::HTTP_OK,
            'message' => 'Utilisateur cree avec succes',
            'body' => $user->toProfilePayload(),
        ]);
    }

    #[Route('/login', name: 'api_user_login', methods: ['POST'])]
    public function login(
        Request $request,
        UserRepository $users,
        UserPasswordHasherInterface $passwordHasher,
        AuthenticatedUserResolver $authenticatedUsers
    ): JsonResponse {
        $payload = $this->requestPayload($request);
        $email = (string) ($payload['email'] ?? '');
        $password = (string) ($payload['password'] ?? '');
        $user = $users->findOneByEmail($email);

        if (!$user instanceof User) {
            return $this->legacyError('Utilisateur introuvable');
        }

        if (!$passwordHasher->isPasswordValid($user, $password)) {
            return $this->legacyError('Mot de passe invalide');
        }

        $authenticatedUsers->login($user, $request);

        return $this->json([
            'status' => Response::HTTP_OK,
            'message' => 'Utilisateur connecte avec succes',
            'body' => [
                'authMode' => 'session',
                'user' => $user->toProfilePayload(),
            ],
        ]);
    }

    #[Route('/profile', name: 'api_user_profile', methods: ['POST'])]
    public function profile(Request $request, AuthenticatedUserResolver $authenticatedUsers): JsonResponse
    {
        $user = $authenticatedUsers->resolve($request);

        if (!$user instanceof User) {
            return $this->json(['message' => 'Session invalide ou absente'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'status' => Response::HTTP_OK,
            'message' => 'Profil utilisateur recupere avec succes',
            'body' => $user->toProfilePayload(),
        ]);
    }

    #[Route('/profile', name: 'api_user_profile_update', methods: ['PUT'])]
    public function updateProfile(
        Request $request,
        AuthenticatedUserResolver $authenticatedUsers,
        UserRepository $users,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $user = $authenticatedUsers->resolve($request);

        if (!$user instanceof User) {
            return $this->json(['message' => 'Session invalide ou absente'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = $this->requestPayload($request);
        $firstName = trim((string) ($payload['firstName'] ?? ''));
        $lastName = trim((string) ($payload['lastName'] ?? ''));
        $email = trim((string) ($payload['email'] ?? $user->getEmail()));

        if ($firstName === '' || $lastName === '' || $email === '') {
            return $this->legacyError('Le prenom, le nom et l email sont obligatoires');
        }

        $existingUser = $users->findOneByEmail($email);

        if ($existingUser instanceof User && $existingUser->getId() !== $user->getId()) {
            return $this->legacyError('Cet email est deja utilise par un autre compte');
        }

        $user
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setEmail($email)
            ->setAddress((string) ($payload['address'] ?? $user->getAddress()))
            ->setPhone((string) ($payload['phone'] ?? $user->getPhone()))
            ->setThemeMode((string) ($payload['themeMode'] ?? $user->getThemeMode()))
            ->setAccentColor((string) ($payload['accentColor'] ?? $user->getAccentColor()));

        $plainPassword = (string) ($payload['password'] ?? '');

        if ($plainPassword !== '') {
            $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
        }

        $entityManager->flush();

        return $this->json([
            'status' => Response::HTTP_OK,
            'message' => 'Profil utilisateur mis a jour avec succes',
            'body' => $user->toProfilePayload(),
        ]);
    }

    #[Route('/accounts', name: 'api_user_accounts', methods: ['GET'])]
    public function accounts(
        Request $request,
        AuthenticatedUserResolver $authenticatedUsers,
        AccountRepository $accounts
    ): JsonResponse {
        $user = $authenticatedUsers->resolve($request);

        if (!$user instanceof User) {
            return $this->json(['message' => 'Session invalide ou absente'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = array_map(static fn ($account): array => $account->toApiPayload(), $accounts->findForUser($user));

        if ($payload === []) {
            return $this->json(['message' => 'Aucun compte trouve pour cet utilisateur'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'message' => 'Comptes recuperes avec succes',
            'body' => $payload,
        ]);
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

    private function legacyError(string $message): JsonResponse
    {
        return $this->json([
            'status' => Response::HTTP_BAD_REQUEST,
            'message' => $message,
        ], Response::HTTP_BAD_REQUEST);
    }
}
