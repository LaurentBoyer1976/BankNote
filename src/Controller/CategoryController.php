<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Category;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Service\AuthenticatedUserResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class CategoryController extends AbstractController
{
    #[Route('/categories', name: 'categories_index', methods: ['GET'])]
    public function index(
        Request $request,
        AuthenticatedUserResolver $authenticatedUsers,
        CategoryRepository $categories
    ): Response {
        $user = $authenticatedUsers->resolve($request);

        if (!$user instanceof User) {
            return $this->redirectToRoute('login');
        }

        return $this->render('category/index.html.twig', [
            'title' => 'Categories',
            'isAuthenticated' => true,
            'userFirstName' => $user->getFirstName(),
            'categories' => $categories->findForUser($user),
        ]);
    }

    #[Route('/categories', name: 'categories_create', methods: ['POST'])]
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
            $category = (new Category())
                ->setUser($user)
                ->setName($name)
                ->setColor((string) $request->request->get('color', '#00bc77'));
            $entityManager->persist($category);
            $entityManager->flush();
        }

        return $this->redirectToRoute('categories_index');
    }

    #[Route('/categories/{id}/update', name: 'categories_update', methods: ['POST'])]
    public function update(
        int $id,
        Request $request,
        AuthenticatedUserResolver $authenticatedUsers,
        CategoryRepository $categories,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $authenticatedUsers->resolve($request);
        $category = $categories->find($id);

        if (!$user instanceof User) {
            return $this->redirectToRoute('login');
        }

        if ($category instanceof Category && $category->getUser()?->getId() === $user->getId()) {
            $category->setName((string) $request->request->get('name', $category->getName()));
            $category->setColor((string) $request->request->get('color', $category->getColor()));
            $entityManager->flush();
        }

        return $this->redirectToRoute('categories_index');
    }

    #[Route('/categories/{id}/delete', name: 'categories_delete', methods: ['POST'])]
    public function delete(
        int $id,
        Request $request,
        AuthenticatedUserResolver $authenticatedUsers,
        CategoryRepository $categories,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $authenticatedUsers->resolve($request);
        $category = $categories->find($id);

        if (!$user instanceof User) {
            return $this->redirectToRoute('login');
        }

        if ($category instanceof Category && $category->getUser()?->getId() === $user->getId()) {
            $entityManager->remove($category);
            $entityManager->flush();
        }

        return $this->redirectToRoute('categories_index');
    }
}
