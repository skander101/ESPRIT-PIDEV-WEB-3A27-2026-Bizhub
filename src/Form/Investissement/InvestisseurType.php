<?php

namespace App\Form\Investissement;

use App\Entity\Investissement\Investment;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class InvestisseurType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('amount', MoneyType::class, [
                'label'    => 'Montant à investir (TND)',
                'currency' => false,
                'attr'     => ['placeholder' => 'Ex : 10 000', 'min' => '100'],
            ])
            ->add('typeInvestissement', ChoiceType::class, [
                'label'       => "Type d'investissement",
                'placeholder' => '— Sélectionner —',
                'required'    => false,
                'choices'     => Investment::TYPES_INVESTISSEMENT,
            ])
            ->add('dureeSouhaitee', ChoiceType::class, [
                'label'       => 'Durée souhaitée',
                'placeholder' => '— Non précisée —',
                'required'    => false,
                'choices'     => Investment::DUREES,
            ])
            ->add('payment_mode', ChoiceType::class, [
                'label'   => 'Mode de paiement',
                'required' => true,
                'choices' => Investment::PAYMENT_MODES,
            ])
            ->add('conditionsParticulieres', TextareaType::class, [
                'label'    => 'Conditions particulières',
                'required' => false,
                'attr'     => [
                    'placeholder' => 'Clauses spécifiques, exigences de retour, droits de vote…',
                    'rows'        => 3,
                ],
            ])
            ->add('commentaire', TextareaType::class, [
                'label'    => 'Message à la startup',
                'required' => false,
                'attr'     => [
                    'placeholder' => 'Motivations, questions, points à discuter…',
                    'rows'        => 3,
                ],
            ])
            ->add('confirmation', CheckboxType::class, [
                'label'    => "J'ai vérifié les informations et je confirme ma demande d'investissement.",
                'mapped'   => false,
                'required' => true,
                'constraints' => [
                    new Assert\IsTrue(message: 'Vous devez confirmer avant de soumettre.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Investment::class]);
    }
}
