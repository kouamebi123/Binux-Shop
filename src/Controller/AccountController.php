<?php

namespace App\Controller;

use App\Entity\Address;
use App\Entity\User;
use App\Form\AddressType;
use App\Form\ChangePasswordType;
use App\Form\ProfileType;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/compte')]
#[IsGranted('ROLE_USER')]
class AccountController extends AbstractController
{
    #[Route('/', name: 'app_account', methods: ['GET'])]
    public function index(OrderRepository $orderRepository): Response
    {
        return $this->render('account/index.html.twig', [
            'recent_orders' => \array_slice($orderRepository->findByUser($this->user()), 0, 3),
        ]);
    }

    #[Route('/profil', name: 'app_account_profile', methods: ['GET', 'POST'])]
    public function profile(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->user();
        $form = $this->createForm(ProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $entityManager->flush();
                $this->addFlash('success', 'Votre profil est à jour.');

                return $this->redirectToRoute('app_account_profile');
            }

            // Les valeurs refusées ne doivent pas rester dans la session de l'utilisateur.
            $entityManager->refresh($user);
        }

        return $this->render('account/profile.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/mot-de-passe', name: 'app_account_password', methods: ['GET', 'POST'])]
    public function password(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $user = $this->user();
        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword($passwordHasher->hashPassword($user, $form->get('newPassword')->getData()));
            $entityManager->flush();
            $this->addFlash('success', 'Votre mot de passe a été changé.');

            return $this->redirectToRoute('app_account');
        }

        return $this->render('account/password.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/adresses', name: 'app_account_addresses', methods: ['GET'])]
    public function addresses(): Response
    {
        return $this->render('account/addresses.html.twig');
    }

    #[Route('/adresse/ajouter', name: 'app_account_address_add', methods: ['GET', 'POST'])]
    public function addAddress(Request $request, EntityManagerInterface $entityManager): Response
    {
        $address = new Address();
        $address->setUser($this->user());

        $form = $this->createForm(AddressType::class, $address);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->keepSingleDefault($address);
            $entityManager->persist($address);
            $entityManager->flush();

            $this->addFlash('success', 'Adresse ajoutée.');

            return $this->redirectToRoute('app_account_addresses');
        }

        return $this->render('account/address_form.html.twig', [
            'form' => $form,
            'title' => 'Ajouter une adresse',
        ]);
    }

    #[Route('/adresse/modifier/{id}', name: 'app_account_address_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function editAddress(Address $address, Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($address->getUser() !== $this->getUser()) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(AddressType::class, $address);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->keepSingleDefault($address);
            $entityManager->flush();
            $this->addFlash('success', 'Adresse modifiée.');

            return $this->redirectToRoute('app_account_addresses');
        }

        return $this->render('account/address_form.html.twig', [
            'form' => $form,
            'title' => 'Modifier l\'adresse',
        ]);
    }

    #[Route('/adresse/supprimer/{id}', name: 'app_account_address_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteAddress(Address $address, Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($address->getUser() !== $this->getUser()) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('delete_address_' . $address->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide');
        }

        $entityManager->remove($address);
        $entityManager->flush();

        $this->addFlash('success', 'Adresse supprimée.');

        return $this->redirectToRoute('app_account_addresses');
    }

    private function user(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }

    /**
     * Une seule adresse par défaut par client.
     */
    private function keepSingleDefault(Address $address): void
    {
        if (!$address->isIsDefault()) {
            return;
        }

        foreach ($this->user()->getAddresses() as $other) {
            if ($other !== $address) {
                $other->setIsDefault(false);
            }
        }
    }
}
