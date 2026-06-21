<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class RegistrationController extends AbstractController
{
    /**
     * Zeigt die Seite mit Login- und Registrierungs-Tabs an.
     */
    #[Route('/registration', name: 'registration_index', methods: ['GET'])]
    public function index(AuthenticationUtils $authenticationUtils): Response
    {
        return $this->render('page/content/registration.html.twig', [
            // Letzter Login-Fehler (wird vom Firewall bereitgestellt).
            'error' => $authenticationUtils->getLastAuthenticationError(),
            // Letzte eingegebene E-Mail, damit das Feld vorbefuellt werden kann.
            'last_username' => $authenticationUtils->getLastUsername(),
        ]);
    }

    /**
     * Diese Action wird NIE ausgefuehrt: Der Security-Firewall faengt den
     * POST auf "check_path" (siehe security.yaml) ab und uebernimmt den Login.
     * Die Route muss aber existieren, damit path('login') aufloesbar ist.
     */
    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(): never
    {
        throw new \LogicException('Wird vom Security-Firewall (check_path) abgefangen.');
    }

    /**
     * Registriert einen neuen Kunden und loggt ihn direkt ein.
     */
    #[Route('/register', name: 'registration', methods: ['POST'])]
    public function registration(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        Security $security,
    ): Response {
        $email = trim((string) $request->request->get('email'));
        $password = (string) $request->request->get('password');
        $username = trim((string) $request->request->get('username'));

        // --- einfache Validierung ---
        if ($email === '' || false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('danger', 'Bitte eine gueltige E-Mail-Adresse angeben.');

            return $this->redirectToRoute('registration_index');
        }

        if (strlen($password) < 8) {
            $this->addFlash('danger', 'Das Passwort muss mindestens 8 Zeichen lang sein.');

            return $this->redirectToRoute('registration_index');
        }

        if (null !== $userRepository->findOneBy(['email' => $email])) {
            $this->addFlash('danger', 'Diese E-Mail-Adresse ist bereits registriert.');

            return $this->redirectToRoute('registration_index');
        }

        // --- User anlegen ---
        $user = new User();
        $user->setEmail($email);
        $user->setUsername('' !== $username ? $username : null);
        $user->setRole('ROLE_USER');
        $user->setPassword($passwordHasher->hashPassword($user, $password));

        $entityManager->persist($user);
        $entityManager->flush();

        // Direkt einloggen (Symfony 6.2+ / 7.x).
        $security->login($user);

        $this->addFlash('success', 'Willkommen! Dein Konto wurde erstellt.');

        // TODO: Ziel nach erfolgreicher Registrierung anpassen (z. B. Dashboard).
        return $this->redirectToRoute('registration_index');
    }

    /**
     * Wird ebenfalls vom Firewall abgefangen (siehe logout in security.yaml).
     */
    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): never
    {
        throw new \LogicException('Wird vom Security-Firewall (logout) abgefangen.');
    }
}