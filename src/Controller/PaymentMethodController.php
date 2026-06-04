<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\PaymentMethod;
use App\Entity\User;
use App\Repository\PaymentMethodRepository;
use App\Service\AuthenticatedUserResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class PaymentMethodController extends AbstractController
{
    #[Route('/payment-methods', name: 'payment_methods_index', methods: ['GET'])]
    public function index(
        Request $request,
        AuthenticatedUserResolver $authenticatedUsers,
        PaymentMethodRepository $paymentMethods
    ): Response {
        $user = $authenticatedUsers->resolve($request);

        if (!$user instanceof User) {
            return $this->redirectToRoute('login');
        }

        return $this->render('payment_method/index.html.twig', [
            'title' => 'Moyens de paiement',
            'isAuthenticated' => true,
            'userFirstName' => $user->getFirstName(),
            'paymentMethods' => $paymentMethods->findForUser($user),
        ]);
    }

    #[Route('/payment-methods', name: 'payment_methods_create', methods: ['POST'])]
    public function create(
        Request $request,
        AuthenticatedUserResolver $authenticatedUsers,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $authenticatedUsers->resolve($request);

        if (!$user instanceof User) {
            return $this->redirectToRoute('login');
        }

        $name = trim((string) $request->request->get('name', ''));

        if ($name !== '') {
            $paymentMethod = (new PaymentMethod())
                ->setUser($user)
                ->setName($name)
                ->setType((string) $request->request->get('type', 'card'));
            $entityManager->persist($paymentMethod);
            $entityManager->flush();
        }

        return $this->redirectToRoute('payment_methods_index');
    }

    #[Route('/payment-methods/{id}/update', name: 'payment_methods_update', methods: ['POST'])]
    public function update(
        int $id,
        Request $request,
        AuthenticatedUserResolver $authenticatedUsers,
        PaymentMethodRepository $paymentMethods,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $authenticatedUsers->resolve($request);
        $paymentMethod = $paymentMethods->find($id);

        if (!$user instanceof User) {
            return $this->redirectToRoute('login');
        }

        if ($paymentMethod instanceof PaymentMethod && $paymentMethod->getUser()?->getId() === $user->getId()) {
            $paymentMethod->setName((string) $request->request->get('name', $paymentMethod->getName()));
            $paymentMethod->setType((string) $request->request->get('type', $paymentMethod->getType()));
            $entityManager->flush();
        }

        return $this->redirectToRoute('payment_methods_index');
    }

    #[Route('/payment-methods/{id}/delete', name: 'payment_methods_delete', methods: ['POST'])]
    public function delete(
        int $id,
        Request $request,
        AuthenticatedUserResolver $authenticatedUsers,
        PaymentMethodRepository $paymentMethods,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $authenticatedUsers->resolve($request);
        $paymentMethod = $paymentMethods->find($id);

        if (!$user instanceof User) {
            return $this->redirectToRoute('login');
        }

        if ($paymentMethod instanceof PaymentMethod && $paymentMethod->getUser()?->getId() === $user->getId()) {
            $entityManager->remove($paymentMethod);
            $entityManager->flush();
        }

        return $this->redirectToRoute('payment_methods_index');
    }
}
