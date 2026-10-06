<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Constraints\PasswordStrength;

class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Mot de passe actuel',
                'attr' => ['class' => 'form-control', 'autocomplete' => 'current-password'],
                'constraints' => [
                    new NotBlank(['message' => 'Saisissez votre mot de passe actuel.']),
                    new UserPassword(['message' => 'Ce n\'est pas votre mot de passe actuel.']),
                ],
            ])
            ->add('newPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'Les deux mots de passe ne sont pas identiques.',
                'first_options' => [
                    'label' => 'Nouveau mot de passe',
                    'attr' => ['class' => 'form-control', 'autocomplete' => 'new-password'],
                    'help' => '10 caractères au minimum.',
                ],
                'second_options' => [
                    'label' => 'Confirmer le nouveau mot de passe',
                    'attr' => ['class' => 'form-control', 'autocomplete' => 'new-password'],
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Choisissez un nouveau mot de passe.']),
                    new Length(['min' => 10, 'max' => 128, 'minMessage' => 'Votre mot de passe doit contenir au moins {{ limit }} caractères']),
                    new PasswordStrength([
                        'minScore' => PasswordStrength::STRENGTH_WEAK,
                        'message' => 'Ce mot de passe est trop facile à deviner. Allongez-le ou mélangez davantage de caractères.',
                    ]),
                    new NotCompromisedPassword([
                        'skipOnError' => true,
                        'message' => 'Ce mot de passe figure dans des fuites de données connues. Choisissez-en un autre.',
                    ]),
                ],
            ])
        ;
    }
}
