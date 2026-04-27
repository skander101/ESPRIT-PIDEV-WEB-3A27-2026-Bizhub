<?php

namespace App\Form\Elearning;

use App\Entity\Elearning\Participation;
use App\Entity\Elearning\Formation;
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
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isAdmin = $options['admin_mode'] ?? false;

        if ($isAdmin && !($options['hide_formation_field'] ?? false)) {
            $builder->add('formation', EntityType::class, [
                'class' => Formation::class,
                'choice_label' => 'title',
                'label' => 'Formation',
                'required' => true,
            ]);
        }

        if ($isAdmin) {
            $builder->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => fn(User $u) => $u->getFullName() ?? $u->getEmail(),
                'label' => 'Participant',
                'required' => true,
            ]);

            $builder
                ->add('date_affectation', DateTimeType::class, [
                    'label' => 'Date d\'affectation',
                    'required' => false,
                    'widget' => 'single_text',
                ])
                ->add('payment_status', ChoiceType::class, [
                    'label' => 'Statut du paiement',
                    'choices' => [
                        'En attente' => 'PENDING',
                        'Payé' => 'PAID',
                        'Échoué' => 'FAILED',
                        'Remboursé' => 'REFUNDED',
                    ],
                    'required' => true,
                ])
                ->add('payment_provider', TextType::class, [
                    'label' => 'Fournisseur de paiement',
                    'required' => false,
                ])
                ->add('payment_ref', TextType::class, [
                    'label' => 'Référence de paiement',
                    'required' => false,
                ])
                ->add('amount', NumberType::class, [
                    'label' => 'Montant',
                    'required' => true,
                    'scale' => 2,
                ])
                ->add('paid_at', DateTimeType::class, [
                    'label' => 'Date de paiement',
                    'required' => false,
                    'widget' => 'single_text',
                    'input' => 'datetime_immutable',
                ]);
        }

        $builder->add('remarques', TextareaType::class, [
            'label' => 'Remarques',
            'required' => false,
            'attr' => ['rows' => 3],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Participation::class,
            'admin_mode' => false,
            'hide_formation_field' => false,
        ]);
    }
}
