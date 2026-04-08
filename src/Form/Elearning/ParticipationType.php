<?php

namespace App\Form\Elearning;

use App\Entity\Elearning\Formation;
use App\Entity\Elearning\Participation;
use App\Entity\UsersAvis\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ParticipationType extends AbstractType
{
    public const PAYMENT_STATUSES = [
        'En attente' => 'PENDING',
        'Payé' => 'PAID',
        'Échoué' => 'FAILED',
        'Remboursé' => 'REFUNDED',
        'Annulé' => 'CANCELLED',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['admin_mode']) {
            $builder
                ->add('user', EntityType::class, [
                    'class' => User::class,
                    'choice_label' => fn (User $u) => $u->getFull_name().' ('.$u->getEmail().')',
                    'label' => 'Participant',
                    'required' => false,
                    'query_builder' => fn ($r) => $r->createQueryBuilder('u')->orderBy('u.full_name', 'ASC'),
                    'attr' => ['class' => 'form-select', 'data-validate-field' => 'user'],
                ]);

            if (!$options['hide_formation_field']) {
                $builder->add('formation', EntityType::class, [
                    'class' => Formation::class,
                    'choice_label' => fn (Formation $f) => $f->getTitle(),
                    'label' => 'Formation',
                    'required' => false,
                    'query_builder' => fn ($r) => $r->createQueryBuilder('f')->orderBy('f.title', 'ASC'),
                    'attr' => ['class' => 'form-select', 'data-validate-field' => 'formation'],
                ]);
            }

            $builder
                ->add('date_affectation', DateTimeType::class, [
                    'label' => "Date d'affectation",
                    'widget' => 'single_text',
                    'html5' => false,
                    'required' => false,
                    'attr' => ['class' => 'form-control', 'data-validate-field' => 'date_affectation'],
                ])
                ->add('remarques', TextareaType::class, [
                    'label' => 'Remarques',
                    'required' => false,
                    'attr' => ['rows' => 3, 'class' => 'form-control', 'data-validate-field' => 'remarques'],
                ])
                ->add('payment_status', ChoiceType::class, [
                    'label' => 'Statut paiement',
                    'choices' => self::PAYMENT_STATUSES,
                    'required' => false,
                    'attr' => ['class' => 'form-select', 'data-validate-field' => 'payment_status'],
                ])
                ->add('payment_provider', TextType::class, [
                    'label' => 'Fournisseur de paiement',
                    'required' => false,
                    'attr' => ['maxlength' => 30, 'class' => 'form-control', 'data-validate-field' => 'payment_provider'],
                ])
                ->add('payment_ref', TextType::class, [
                    'label' => 'Référence paiement',
                    'required' => false,
                    'attr' => ['maxlength' => 255, 'class' => 'form-control', 'data-validate-field' => 'payment_ref'],
                ])
                ->add('amount', NumberType::class, [
                    'label' => 'Montant',
                    'scale' => 2,
                    'required' => false,
                    'attr' => ['class' => 'form-control', 'step' => '0.01', 'data-validate-field' => 'amount'],
                ])
                ->add('paid_at', DateTimeType::class, [
                    'label' => 'Payé le',
                    'widget' => 'single_text',
                    'html5' => false,
                    'required' => false,
                    'attr' => ['class' => 'form-control', 'data-validate-field' => 'paid_at'],
                ]);
        } else {
            $builder->add('remarques', TextareaType::class, [
                'label' => 'Message / remarques (optionnel)',
                'required' => false,
                'attr' => ['rows' => 3, 'class' => 'form-control', 'data-validate-field' => 'remarques'],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Participation::class,
            'admin_mode' => true,
            'hide_formation_field' => false,
        ]);
        $resolver->setAllowedTypes('admin_mode', 'bool');
        $resolver->setAllowedTypes('hide_formation_field', 'bool');
    }
}
