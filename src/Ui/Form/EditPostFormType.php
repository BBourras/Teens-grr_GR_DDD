<?php

declare(strict_types=1);

namespace App\Ui\Form;

use App\Application\Dto\EditPostDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Formulaire de modification d’un Post.
 *
 * Utilise un DTO dédié afin de séparer
 * les cas d’usage création / édition.
 */
final class EditPostFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'constraints' => [
                    new Assert\NotBlank(
                        message: 'Le titre ne peut pas être vide.'
                    ),
                    new Assert\Length(
                        min: 5,
                        max: 255,
                        minMessage: 'Le titre doit contenir au moins {{ limit }} caractères.',
                        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.',
                    ),
                ],
                'attr' => [
                    'placeholder' => 'Un titre bien ironique…',
                    'autofocus'   => true,
                    'maxlength'   => 255,
                ],
            ])

            ->add('content', TextareaType::class, [
                'label' => 'Votre message',
                'constraints' => [
                    new Assert\NotBlank(
                        message: 'Le contenu ne peut pas être vide.'
                    ),
                    new Assert\Length(
                        min: 10,
                        max: 5000,
                        minMessage: 'Le contenu doit contenir au moins {{ limit }} caractères.',
                        maxMessage: 'Le contenu ne peut pas dépasser {{ limit }} caractères.',
                    ),
                ],
                'attr' => [
                    'rows'        => 12,
                    'maxlength'   => 5000,
                    'placeholder' => 'Modifiez votre anecdote…',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EditPostDto::class,
        ]);
    }
}