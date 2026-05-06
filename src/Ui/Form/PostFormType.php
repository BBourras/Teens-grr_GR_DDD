<?php

declare(strict_types=1);

namespace App\Ui\Form;

use App\Application\Dto\CreatePostDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class PostFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'constraints' => [
                    new Assert\NotBlank(message: 'Le titre ne peut pas être vide.'),
                    new Assert\Length(min: 5, max: 255),
                ],
                'attr' => [
                    'placeholder' => 'Un titre bien ironique…',
                    'autofocus'   => true,
                ],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Votre message',
                'constraints' => [
                    new Assert\NotBlank(message: 'Le contenu ne peut pas être vide.'),
                    new Assert\Length(min: 10, max: 5000),
                ],
                'attr' => [
                    'rows'        => 12,
                    'placeholder' => 'Racontez-nous votre dernier moment "ado" mémorable…',
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CreatePostDto::class,
        ]);
    }
}