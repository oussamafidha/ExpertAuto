<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/utilisateurs')]
final class UserController extends AbstractController
{
    private const ROLE_CHOICES = [
        'Administrator' => User::ROLE_ADMIN,
        'Mechanic' => User::ROLE_MECHANIC,
        'Client' => User::ROLE_CLIENT,
        'Storekeeper' => User::ROLE_STOREKEEPER,
    ];

    #[Route('', name: 'user_index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(User::ROLE_ADMIN);

        return $this->render('users/index.html.twig', [
            'users' => $em->getRepository(User::class)->findBy([], ['fullName' => 'ASC']),
        ]);
    }

    #[Route('/nouveau', name: 'user_new', methods: ['GET', 'POST'])]
    #[Route('/{id}/modifier', name: 'user_edit', methods: ['GET', 'POST'])]
    public function form(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $passwordHasher,
        ?int $id = null,
    ): Response {
        $this->denyAccessUnlessGranted(User::ROLE_ADMIN);

        $user = $id === null ? new User() : $em->find(User::class, $id);
        if (!$user) {
            throw $this->createNotFoundException();
        }

        $error = null;
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('user_form_' . ($id ?? 'new'), (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }

            $email = mb_strtolower(trim((string) $request->request->get('email')));
            $fullName = trim((string) $request->request->get('fullName'));
            $password = (string) $request->request->get('password');
            $role = (string) $request->request->get('role');
            $duplicate = $em->getRepository(User::class)->findOneBy(['email' => $email]);

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)
                || $fullName === ''
                || !isset(array_flip(self::ROLE_CHOICES)[$role])
                || ($duplicate && $duplicate !== $user)
                || ($id === null && strlen($password) < 12)
                || ($password !== '' && strlen($password) < 12)
            ) {
                $error = 'Vérifiez le nom, l’adresse e-mail, le rôle et le mot de passe (12 caractères minimum).';
            } elseif ($this->wouldRemoveLastAdministrator($em, $user, $role)) {
                $error = 'Il faut conserver au moins un compte administrateur.';
            } else {
                $user->setEmail($email)->setFullName($fullName)->setRoles([$role]);
                if ($password !== '') {
                    $user->setPassword($passwordHasher->hashPassword($user, $password));
                }

                $em->persist($user);
                $em->flush();
                $this->addFlash('success', $id === null ? 'Utilisateur créé.' : 'Utilisateur modifié.');

                return $this->redirectToRoute('user_index');
            }
        }

        return $this->render('users/form.html.twig', [
            'item' => $user,
            'roleChoices' => self::ROLE_CHOICES,
            'error' => $error,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'user_delete', methods: ['POST'])]
    public function delete(Request $request, EntityManagerInterface $em, int $id): Response
    {
        $this->denyAccessUnlessGranted(User::ROLE_ADMIN);

        $user = $em->find(User::class, $id);
        if (!$user) {
            throw $this->createNotFoundException();
        }
        if ($user === $this->getUser()) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer votre propre compte.');

            return $this->redirectToRoute('user_index');
        }
        if (!$this->isCsrfTokenValid('user_delete_' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        if (in_array(User::ROLE_ADMIN, $user->getRoles(), true) && $this->countOtherAdministrators($em, $user) === 0) {
            $this->addFlash('error', 'Il faut conserver au moins un compte administrateur.');

            return $this->redirectToRoute('user_index');
        }

        $em->remove($user);
        $em->flush();
        $this->addFlash('success', 'Utilisateur supprimé.');

        return $this->redirectToRoute('user_index');
    }

    private function wouldRemoveLastAdministrator(EntityManagerInterface $em, User $user, string $newRole): bool
    {
        return in_array(User::ROLE_ADMIN, $user->getRoles(), true)
            && $newRole !== User::ROLE_ADMIN
            && $this->countOtherAdministrators($em, $user) === 0;
    }

    private function countOtherAdministrators(EntityManagerInterface $em, User $user): int
    {
        $administrators = $em->getRepository(User::class)->findAll();
        $count = 0;
        foreach ($administrators as $administrator) {
            if ($administrator !== $user && in_array(User::ROLE_ADMIN, $administrator->getRoles(), true)) {
                ++$count;
            }
        }

        return $count;
    }
}
