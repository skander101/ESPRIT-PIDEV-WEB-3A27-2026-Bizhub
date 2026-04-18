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
use Symfony\Component\Validator\Constraints as Assert;

class InvestissementFrontType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('amount', MoneyType::class, [
                'label'    => 'Montant (TND)',
                'currency' => false,
                'attr'     => ['placeholder' => '10 000'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le montant est obligatoire.'),
                    new Assert\GreaterThan(['value' => 99, 'message' => 'Minimum 100 TND.']),
                ],
            ])
            ->add('investment_date', DateTimeType::class, [
                'label'    => "Date d'investissement",
                'widget'   => 'single_text',
                'required' => false,
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
            ->add('statut', ChoiceType::class, [
                'label'   => 'Statut',
                'choices' => [
                    'En attente'      => 'en_attente',
                    'En négociation'  => 'en_negociation',
                    'Accepté'         => 'accepte',
                    'Refusé'          => 'refuse',
                    'Contrat généré'  => 'contrat_genere',
                    'Signé'           => 'signe',
                    'Terminé'         => 'termine',
                ],
            ])
            ->add('contract_url', TextType::class, [
                'label'    => 'URL du contrat',
                'required' => false,
                'attr'     => ['placeholder' => 'https://…'],
                'constraints' => [
                    new Assert\Url(message: "L'URL n'est pas valide."),
                ],
            ])
            ->add('commentaire', TextareaType::class, [
                'label'    => 'Commentaire',
                'required' => false,
                'attr'     => ['placeholder' => 'Remarques, conditions particulières…', 'rows' => 3],
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
