<?php
namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class SecurityController extends AbstractController
{
    #[Route('/connexion', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(): Response
    {
        if ($this->getUser()) return $this->redirectToRoute('app_dashboard');
        return $this->render('security/login.html.twig');
    }

    #[Route('/deconnexion', name: 'app_logout', methods: ['GET'])]
    public function logout(): void { throw new \LogicException('Cette route est interceptée par le firewall.'); }

    #[Route('/initialiser-admin', name: 'app_setup_admin', methods: ['GET', 'POST'])]
    public function setupAdmin(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        if ((int)$em->getRepository(User::class)->count([]) > 0) {
            throw $this->createAccessDeniedException('Un utilisateur existe déjà. Supprimez cette route après la création du premier compte.');
        }
        $error = null;
        if ($request->isMethod('POST')) {
            $email = trim((string)$request->request->get('email'));
            $name = trim((string)$request->request->get('fullName'));
            $password = (string)$request->request->get('password');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '' || strlen($password) < 12) {
                $error = 'Saisissez un email valide, un nom et un mot de passe de 12 caractères minimum.';
            } else {
                $user = (new User())->setEmail($email)->setFullName($name)->setRoles(['ROLE_ADMIN', 'ROLE_MECHANIC']);
                $user->setPassword($hasher->hashPassword($user, $password));
                $em->persist($user); $em->flush();
                return $this->redirectToRoute('app_login');
            }
        }
        return $this->render('security/setup.html.twig', ['error' => $error]);
    }
}
