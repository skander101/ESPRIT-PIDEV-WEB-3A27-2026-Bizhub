<?php

namespace App\Form\Marketplace;

use App\Entity\Marketplace\Commande;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class TrackingInfoFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('trackingCarrier', ChoiceType::class, [
                'label'       => 'Transporteur',
                'choices'     => [
                    'USPS'           => 'usps',
                    'FedEx'          => 'fedex',
                    'UPS'            => 'ups',
                    'DHL Express'    => 'dhl_express',
                    'DHL eCommerce'  => 'dhl_ecommerce',
                    'Chronopost'     => 'chronopost',
                    'Colissimo'      => 'colissimo',
                    'Aramex'         => 'aramex',
                    'TNT'            => 'tnt',
                    'LaPoste'        => 'laposte',
                ],
                'placeholder'  => '— Choisir un transporteur —',
                'constraints'  => [new NotBlank(message: 'Sélectionne un transporteur.')],
            ])
            ->add('trackingNumber', TextType::class, [
                'label'       => 'Numéro de suivi',
                'attr'        => ['placeholder' => 'ex: 9400111899223397846046'],
                'constraints' => [
                    new NotBlank(message: 'Le numéro de suivi est obligatoire.'),
                    new Regex([
                        'pattern' => '/^[A-Z0-9\-]{5,50}$/i',
                        'message' => 'Format invalide — lettres, chiffres et tirets uniquement (5–50 caractères).',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Commande::class]);
    }
}
