<?php

declare(strict_types=1);

namespace App\Ui\Form;

use App\Domain\Enum\ReportReason;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Formulaire de signalement d’un contenu (Post ou Comment).
 */
final class ReportFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('reason', EnumType::class, [
                'class'        => ReportReason::class,
                'label'        => 'Raison du signalement',
                'choice_label' => fn(ReportReason $reason) => $reason->label(),
                'placeholder'  => 'Choisissez une raison…',
                'required'     => true,
            ])

            ->add('reason_detail', TextareaType::class, [
                'label'    => 'Précisions supplémentaires (facultatif)',
                'required' => false,
                'attr'     => [
                    'rows'        => 5,
                    'placeholder' => 'Décrivez brièvement pourquoi vous signalez ce contenu (insultes, harcèlement, hors-sujet, etc.)',
                    'maxlength'   => 1000,
                ],
                'constraints' => [
                    new Assert\Length(
                        max: 1000,
                        maxMessage: 'Les précisions ne peuvent pas dépasser {{ limit }} caractères.'
                    ),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,   // On utilise la ReportFactory pour créer l'entité
        ]);
    }
}