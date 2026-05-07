<?php

declare(strict_types=1);

namespace App\Ui\Form;

use App\Application\Dto\CreateCommentDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Formulaire de création d’un commentaire.
 *
 * Utilise un DTO pour respecter l'approche DDD Light (comme pour Post).
 */
final class CommentFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('content', TextareaType::class, [
                'label' => 'Votre commentaire',
                'constraints' => [
                    new Assert\NotBlank(message: 'Le commentaire ne peut pas être vide.'),
                    new Assert\Length(
                        min: 3,
                        max: 2000,
                        minMessage: 'Le commentaire doit faire au moins {{ limit }} caractères.',
                        maxMessage: 'Le commentaire ne peut pas dépasser {{ limit }} caractères.'
                    ),
                ],
                'attr' => [
                    'rows'        => 6,
                    'placeholder' => 'Votre réaction au post…',
                    'maxlength'   => 2000,
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CreateCommentDto::class,
        ]);
    }
}