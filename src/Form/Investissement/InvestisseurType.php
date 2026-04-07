<?php

namespace App\Form\Investissement;

use App\Entity\Investissement\Investment;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Formulaire simplifié pour l'investisseur lors de la création d'un investissement.
 * N'expose pas le statut ni l'URL de contrat (gérés en interne).
 */
class InvestisseurType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('amount', MoneyType::class, [
                'label'    => 'Montant à investir (TND)',
                'currency' => false,
                'attr'     => [
                    'placeholder' => 'Ex: 10 000',
                    'min'         => '100',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le montant est obligatoire.'),
                    new Assert\GreaterThan([
                        'value'   => 99,
                        'message' => 'Le montant minimum est de 100 TND.',
                    ]),
                ],
            ])
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
            ->add('commentaire', TextareaType::class, [
                'label'    => 'Message à la startup (optionnel)',
                'required' => false,
                'attr'     => [
                    'placeholder' => 'Conditions particulières, motivations, questions…',
                    'rows'        => 4,
                ],
                'constraints' => [
                    new Assert\Length(['max' => 1000]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Investment::class,
        ]);
    }
}
