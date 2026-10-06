<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/connexion', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        // Page à retrouver après la connexion : uniquement un chemin interne au site.
        $target = (string) $request->query->get('retour', '');
        if (!preg_match('#^/(?![/\\\\])[A-Za-z0-9_\-./%]*$#', $target)) {
            $target = '';
        }

        return $this->render('security/login.html.twig', [
            'target_path' => $target,
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/inscription', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        RateLimiterFactory $registrationLimiter,
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $user = new User();
        $form = $this->createForm(RegistrationType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$registrationLimiter->create((string) $request->getClientIp())->consume()->isAccepted()) {
                $form->addError(new FormError('Trop de comptes ont été créés depuis cette connexion. Réessayez dans une heure.'));
            } else {
                $user->setEmail(mb_strtolower(trim((string) $user->getEmail())));
                $user->setPassword($passwordHasher->hashPassword($user, $form->get('plainPassword')->getData()));

                $entityManager->persist($user);
                $entityManager->flush();

                $this->addFlash('success', 'Votre compte est créé. Connectez-vous pour commencer vos achats.');

                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('security/register.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/deconnexion', name: 'app_logout', methods: ['GET', 'POST'])]
    public function logout(): void
    {
        throw new \LogicException('Cette méthode peut être vide - elle sera interceptée par la clé logout de votre firewall.');
    }
}
