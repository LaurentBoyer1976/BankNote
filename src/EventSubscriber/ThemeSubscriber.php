<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\AuthenticatedUserResolver;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ThemeSubscriber implements EventSubscriberInterface
{
    private AuthenticatedUserResolver $authenticatedUsers;

    public function __construct(AuthenticatedUserResolver $authenticatedUsers)
    {
        $this->authenticatedUsers = $authenticatedUsers;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onController',
        ];
    }

    public function onController(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        $themeMode = 'light';
        $accentColor = '#00bc77';
        $user = $this->authenticatedUsers->resolve($request);

        if ($user instanceof User) {
            $themeMode = $user->getThemeMode();
            $accentColor = $user->getAccentColor();
        }

        $request->attributes->set('app_theme_mode', $themeMode);
        $request->attributes->set('app_accent_color', $accentColor);
    }
}
