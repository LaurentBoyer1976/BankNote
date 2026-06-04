<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class AuthenticatedUserResolver
{
    public const SESSION_USER_ID = 'budget_tracker_user_id';

    private RequestStack $requestStack;
    private UserRepository $userRepository;

    public function __construct(RequestStack $requestStack, UserRepository $userRepository)
    {
        $this->requestStack = $requestStack;
        $this->userRepository = $userRepository;
    }

    public function resolve(?Request $request = null): ?User
    {
        $request ??= $this->requestStack->getCurrentRequest();

        if ($request === null || !$request->hasSession()) {
            return null;
        }

        $session = $request->getSession();
        $userId = $session->get(self::SESSION_USER_ID);

        if ($userId === null) {
            return null;
        }

        $user = $this->userRepository->find((int) $userId);

        if (!$user instanceof User) {
            $session->remove(self::SESSION_USER_ID);

            return null;
        }

        return $user;
    }

    public function login(User $user, Request $request): void
    {
        $session = $request->getSession();
        $session->migrate(true);
        $session->set(self::SESSION_USER_ID, $user->getId());
    }

    public function logout(Request $request): void
    {
        if ($request->hasSession()) {
            $request->getSession()->invalidate();
        }
    }
}
