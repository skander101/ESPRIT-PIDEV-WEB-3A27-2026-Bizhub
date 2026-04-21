<?php

namespace App\Form\Marketplace;

use App\Entity\Marketplace\ProduitService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Vich\UploaderBundle\Form\Type\VichImageType;

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
            ->add('imageFile', VichImageType::class, [
                // VichImageType est fourni par le bundle — il lie directement
                // l'upload à la propriété imageFile de l'entité.
                // Vich s'occupe du reste : nommage, déplacement, suppression.
                'label'           => 'Photo du produit',
                'required'        => false,
                'allow_delete'    => true,   // affiche une case "supprimer l'image"
                'download_uri'    => false,  // pas de lien de téléchargement
                'image_uri'       => true,   // affiche un aperçu de l'image actuelle
                'attr'            => ['accept' => 'image/*'],
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
