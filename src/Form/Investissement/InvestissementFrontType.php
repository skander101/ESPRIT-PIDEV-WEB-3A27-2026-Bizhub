<?php

namespace App\Form\Investissement;

use App\Entity\Investissement\Investment;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InvestissementFrontType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('amount', MoneyType::class, [
                'label'    => 'Montant (TND)',
                'currency' => false,
                'attr'     => ['placeholder' => '10 000'],
            ])
            ->add('investment_date', DateTimeType::class, [
                'label'    => "Date d'investissement",
                'widget'   => 'single_text',
                'required' => false,
            ])
            ->add('payment_mode', ChoiceType::class, [
                'label'   => 'Mode de paiement',
                'required' => true,
                'choices' => Investment::PAYMENT_MODES,
            ])
            ->add('statut', ChoiceType::class, [
                'label'   => 'Statut',
                'required' => true,
                'choices' => Investment::STATUTS,
            ])
            ->add('contract_url', TextType::class, [
                'label'    => 'URL du contrat',
                'required' => false,
                'attr'     => ['placeholder' => 'https://…'],
            ])
            ->add('commentaire', TextareaType::class, [
                'label'    => 'Commentaire',
                'required' => false,
                'attr'     => ['placeholder' => 'Remarques, conditions particulières…', 'rows' => 3],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Investment::class,
        ]);
    }
}
