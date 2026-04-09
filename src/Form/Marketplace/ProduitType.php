<?php

namespace App\Form\Marketplace;

use App\Entity\Marketplace\ProduitService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProduitType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'nom',
                'attr'  => [
                    'placeholder' => 'nom — ex: Développement Web, Design UI',
                    'maxlength' => 200,
                ],
            ])
            ->add('description', TextareaType::class, [
                'label'    => 'description',
                'required' => false,
                'attr'     => [
                    'placeholder' => 'description — décrivez votre produit/service (optionnel)',
                    'rows'        => 4,
                    'maxlength'   => 1000,
                ],
            ])
            ->add('prix', NumberType::class, [
                'label' => 'prix (TND)',
                'attr'  => [
                    'placeholder' => 'prix — ex: 49.900',
                    'step' => '0.01',
                ],
            ])
            ->add('quantite', IntegerType::class, [
                'label' => 'quantite',
                'attr'  => [
                    'placeholder' => 'quantite — ex: 10',
                    'min' => 0,
                    'max' => 999999,
                ],
            ])
            ->add('categorie', TextType::class, [
                'label'    => 'categorie',
                'required' => false,
                'attr'     => [
                    'placeholder' => 'categorie — ex: design, math, consulting',
                    'maxlength' => 100,
                ],
            ])
            ->add('imagePath', FileType::class, [
                'label'    => 'Photo du produit',
                'required' => false,
                'mapped'   => false,
                'attr'     => [
                    'accept'       => 'image/*',
                    'class'        => 'form-control',
                    'data-browse'  => 'Choisir une image...',
                ],
            ])
            ->add('disponible', CheckboxType::class, [
                'label'    => 'Disponible',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProduitService::class,
        ]);
    }
}
