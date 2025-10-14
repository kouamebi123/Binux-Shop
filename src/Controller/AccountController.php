<?php

namespace App\Controller;

use App\Entity\Address;
use App\Form\AddressType;
use App\Form\ProfileType;
use App\Repository\AddressRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/compte')]
#[IsGranted('ROLE_USER')]
class AccountController extends AbstractController
{
    #[Route('/', name: 'app_account')]
    public function index(): Response
    {
        return $this->render('account/index.html.twig');
    }

    #[Route('/profil', name: 'app_account_profile')]
    public function profile(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $form = $this->createForm(ProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Profil mis à jour avec succès !');
            return $this->redirectToRoute('app_account_profile');
        }

        return $this->render('account/profile.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/adresses', name: 'app_account_addresses')]
    public function addresses(): Response
    {
        return $this->render('account/addresses.html.twig');
    }

    #[Route('/adresse/ajouter', name: 'app_account_address_add')]
    public function addAddress(Request $request, EntityManagerInterface $entityManager): Response
    {
        $address = new Address();
        $address->setUser($this->getUser());

        $form = $this->createForm(AddressType::class, $address);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($address);
            $entityManager->flush();

            $this->addFlash('success', 'Adresse ajoutée avec succès !');
            return $this->redirectToRoute('app_account_addresses');
        }

        return $this->render('account/address_form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Ajouter une adresse',
        ]);
    }

    #[Route('/adresse/modifier/{id}', name: 'app_account_address_edit')]
    public function editAddress(
        Address $address,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        if ($address->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(AddressType::class, $address);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Adresse modifiée avec succès !');
            return $this->redirectToRoute('app_account_addresses');
        }

        return $this->render('account/address_form.html.twig', [
            'form' => $form->createView(),
            'title' => 'Modifier l\'adresse',
        ]);
    }

    #[Route('/adresse/supprimer/{id}', name: 'app_account_address_delete', methods: ['POST'])]
    public function deleteAddress(Address $address, Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($address->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        // Vérification CSRF
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_address_' . $address->getId(), $token)) {
            throw $this->createAccessDeniedException('Token CSRF invalide');
        }

        $entityManager->remove($address);
        $entityManager->flush();

        $this->addFlash('success', 'Adresse supprimée avec succès !');
        return $this->redirectToRoute('app_account_addresses');
    }
}

