<?php

declare(strict_types=1);

namespace App\Ui\Form;

use App\Application\Formatter\ReportReasonFormatter;
use App\Domain\Enum\ReportReason;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class ReportFormType extends AbstractType
{
    public function __construct(
        private readonly ReportReasonFormatter $formatter,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('reason', EnumType::class, [
                'class'        => ReportReason::class,
                'label'        => 'Raison du signalement',
                'choice_label' => fn(ReportReason $reason) => $this->formatter->label($reason),
                'placeholder'  => 'Choisissez une raison...',
                'required'     => true,
                'constraints'  => [
                    new Assert\NotNull(message: 'Vous devez choisir une raison.'),
                ],
            ])
            ->add('reason_detail', TextareaType::class, [
                'label'    => 'Précisions supplémentaires (facultatif)',
                'required' => false,
                'attr'     => [
                    'rows'        => 4,
                    'placeholder' => 'Détails, contexte, lien...',
                    'maxlength'   => 1000,
                ],
                'constraints' => [
                    new Assert\Length(max: 1000),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,   // On utilise ReportFactory
        ]);
    }
}