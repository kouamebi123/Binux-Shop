<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Création (ou remise à zéro) du compte administrateur.
 *
 * La page n'existe que si la variable SETUP_TOKEN est définie sur le serveur, et n'agit
 * que si le code saisi lui correspond. Une fois le compte créé, la variable se supprime.
 */
class SetupController extends AbstractController
{
    public function __construct(private readonly string $setupToken)
    {
    }

    #[Route('/installation', name: 'app_setup', methods: ['GET', 'POST'])]
    public function __invoke(
        Request $request,
        UserRepository $users,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        ValidatorInterface $validator,
        RateLimiterFactory $setupLimiter,
    ): Response {
        if (\strlen($this->setupToken) < 24) {
            throw $this->createNotFoundException();
        }

        $errors = [];
        $email = '';

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('setup', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Jeton de sécurité invalide');
            }

            $email = mb_strtolower(trim((string) $request->request->get('email')));
            $password = (string) $request->request->get('password');

            if (!$setupLimiter->create((string) $request->getClientIp())->consume()->isAccepted()) {
                $errors[] = 'Trop d\'essais. Réessayez dans un quart d\'heure.';
            } elseif (!hash_equals($this->setupToken, (string) $request->request->get('token'))) {
                $errors[] = 'Le code d\'installation est incorrect.';
            } else {
                foreach ($validator->validate($email, [new Assert\NotBlank(), new Assert\Email(), new Assert\Length(max: 180)]) as $violation) {
                    $errors[] = 'Adresse e-mail : ' . $violation->getMessage();
                }

                $passwordRules = [
                    new Assert\Length(min: 12, max: 128, minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.'),
                    new Assert\PasswordStrength(minScore: Assert\PasswordStrength::STRENGTH_MEDIUM, message: 'Ce mot de passe est trop facile à deviner pour un compte administrateur.'),
                ];
                foreach ($validator->validate($password, $passwordRules) as $violation) {
                    $errors[] = $violation->getMessage();
                }

                if ($password !== (string) $request->request->get('password_confirm')) {
                    $errors[] = 'Les deux mots de passe ne sont pas identiques.';
                }
            }

            if (!$errors) {
                $user = $users->findOneBy(['email' => $email]);

                if (!$user) {
                    $user = new User();
                    $user->setEmail($email);
                    $user->setFirstName('Admin');
                    $user->setLastName('Binux Shop');
                    $entityManager->persist($user);
                }

                $user->setRoles(['ROLE_ADMIN']);
                $user->setPassword($passwordHasher->hashPassword($user, $password));
                $entityManager->flush();

                $this->addFlash('success', 'Le compte administrateur est prêt. Supprimez maintenant la variable SETUP_TOKEN dans Railway, puis connectez-vous.');

                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('security/setup.html.twig', [
            'errors' => $errors,
            'email' => $email,
        ]);
    }
}
