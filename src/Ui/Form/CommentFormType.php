<?php

declare(strict_types=1);

namespace App\Ui\Form;

use App\Domain\Entity\Comment;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Formulaire de création d’un commentaire.
 */
final class CommentFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('content', TextareaType::class, [
                'label' => false,   // On met le label dans le template pour plus de flexibilité
                'constraints' => [
                    new Assert\NotBlank(message: 'Le commentaire ne peut pas être vide.'),
                    new Assert\Length(
                        min: 3,
                        max: 2000,
                        minMessage: 'Le commentaire doit contenir au moins {{ limit }} caractères.',
                        maxMessage: 'Le commentaire ne peut pas dépasser {{ limit }} caractères.'
                    ),
                ],
                'attr' => [
                    'rows'        => 5,
                    'placeholder' => 'Votre réaction ironique ou votre anecdote...',
                    'maxlength'   => 2000,
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Comment::class,
        ]);
    }
}