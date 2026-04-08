<?php

namespace App\Form\Marketplace;

use App\Entity\Marketplace\Panier;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class PanierType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('idProduit', IntegerType::class, [
                'label' => 'ID Produit',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Positive(),
                ],
            ])
            ->add('quantite', IntegerType::class, [
                'label' => 'Quantité',
                'attr'  => ['min' => 1, 'max' => 999],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Positive(message: 'La quantité doit être ≥ 1.'),
                    new Assert\LessThanOrEqual(value: 999),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Panier::class,
        ]);
    }
}
