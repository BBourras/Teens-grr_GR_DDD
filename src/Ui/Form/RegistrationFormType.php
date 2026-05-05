<?php

declare(strict_types=1);

namespace App\Ui\Form;

use App\Domain\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Formulaire d’inscription.
 */
final class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                'constraints' => [
                    new Assert\NotBlank(message: 'L’email est obligatoire.'),
                    new Assert\Email(message: 'Cet email n’est pas valide.'),
                ],
                'attr' => ['placeholder' => 'vous@exemple.fr'],
            ])
            ->add('username', TextType::class, [
                'label' => 'Pseudo',
                'constraints' => [
                    new Assert\NotBlank(message: 'Le pseudo est obligatoire.'),
                    new Assert\Length(min: 3, max: 50),
                    new Assert\Regex(
                        pattern: '/^[a-zA-Z0-9_\-]+$/',
                        message: 'Le pseudo ne peut contenir que lettres, chiffres, tirets et underscores.'
                    ),
                ],
                'attr' => ['placeholder' => 'MonPseudoIronique'],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'invalid_message' => 'Les deux mots de passe doivent correspondre.',
                'first_options' => [
                    'label' => 'Mot de passe',
                    'attr' => ['placeholder' => '••••••••'],
                ],
                'second_options' => [
                    'label' => 'Confirmer le mot de passe',
                    'attr' => ['placeholder' => '••••••••'],
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le mot de passe est obligatoire.'),
                    new Assert\Length(min: 8),
                    new Assert\PasswordStrength(minScore: Assert\PasswordStrength::STRENGTH_MEDIUM),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}