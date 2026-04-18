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
            // ── Montant ────────────────────────────────────────────────────────
            ->add('amount', MoneyType::class, [
                'label'    => 'Montant à investir (TND)',
                'currency' => false,
                'attr'     => ['placeholder' => 'Ex : 10 000', 'min' => '100'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le montant est obligatoire.'),
                    new Assert\GreaterThan(['value' => 99, 'message' => 'Le montant minimum est de 100 TND.']),
                ],
            ])

            // ── Type d'investissement ──────────────────────────────────────────
            ->add('typeInvestissement', ChoiceType::class, [
                'label'       => "Type d'investissement",
                'placeholder' => '— Sélectionner —',
                'required'    => false,
                'choices'     => [
                    'Prise de participation' => 'prise_participation',
                    'Prêt convertible'       => 'pret_convertible',
                    'Prêt simple'            => 'pret_simple',
                    'Don / Grant'            => 'don',
                ],
            ])

            // ── Durée souhaitée ────────────────────────────────────────────────
            ->add('dureeSouhaitee', ChoiceType::class, [
                'label'       => 'Durée souhaitée',
                'placeholder' => '— Non précisée —',
                'required'    => false,
                'choices'     => [
                    '3 mois'  => '3m',
                    '6 mois'  => '6m',
                    '1 an'    => '12m',
                    '2 ans'   => '24m',
                    '3 ans'   => '36m',
                    '5 ans'   => '60m',
                ],
            ])

            // ── Mode de paiement ──────────────────────────────────────────────
            ->add('payment_mode', ChoiceType::class, [
                'label'   => 'Mode de paiement',
                'choices' => [
                    'Virement bancaire' => 'virement',
                    'Chèque'            => 'cheque',
                    'Espèces'           => 'especes',
                    'Carte bancaire'    => 'carte',
                    'Crypto'            => 'crypto',
                ],
            ])

            // ── Conditions particulières ───────────────────────────────────────
            ->add('conditionsParticulieres', TextareaType::class, [
                'label'    => 'Conditions particulières',
                'required' => false,
                'attr'     => [
                    'placeholder' => 'Clauses spécifiques, exigences de retour, droits de vote…',
                    'rows'        => 3,
                ],
                'constraints' => [new Assert\Length(['max' => 800])],
            ])

            // ── Message à la startup ───────────────────────────────────────────
            ->add('commentaire', TextareaType::class, [
                'label'    => 'Message à la startup',
                'required' => false,
                'attr'     => [
                    'placeholder' => 'Motivations, questions, points à discuter…',
                    'rows'        => 3,
                ],
                'constraints' => [new Assert\Length(['max' => 1000])],
            ])

            // ── Case de confirmation (non persistée) ───────────────────────────
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
