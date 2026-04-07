<?php

namespace App\Form\Marketplace;

use App\Entity\Marketplace\ProduitService;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ProduitType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'nom',
                'attr'  => ['placeholder' => 'nom — ex: Développement Web, Design UI'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le nom est obligatoire.'),
                    new Assert\Length(min: 2, max: 200,
                        minMessage: 'Minimum 2 caractères.',
                        maxMessage: 'Maximum 200 caractères.'
                    ),
                    new Assert\Regex(
                        pattern: '/^\d+$/',
                        match: false,
                        message: 'Le nom ne peut pas être uniquement des chiffres.'
                    ),
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
            ->add('prix', TextType::class, [
                'label' => 'prix (TND)',
                'attr'  => ['placeholder' => 'prix — ex: 49.900'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le prix est obligatoire.'),
                    new Assert\Regex(
                        pattern: '/^\d+(\.\d{1,3})?$/',
                        message: 'Format invalide — max 3 décimales (ex: 49.900)'
                    ),
                    new Assert\Positive(message: 'Le prix doit être > 0.'),
                ],
            ])
            ->add('quantite', IntegerType::class, [
                'label' => 'quantite',
                'attr'  => ['placeholder' => 'quantite — ex: 10', 'min' => 0, 'max' => 999999],
                'constraints' => [
                    new Assert\NotBlank(message: 'La quantité est obligatoire.'),
                    new Assert\PositiveOrZero(message: 'La quantité doit être ≥ 0.'),
                    new Assert\LessThanOrEqual(value: 999999, message: 'Max 999 999 unités.'),
                ],
            ])
            ->add('categorie', TextType::class, [
                'label'    => 'categorie',
                'required' => false,
                'attr'     => ['placeholder' => 'categorie — ex: design, math, consulting'],
                'constraints' => [
                    new Assert\Length(max: 100, maxMessage: 'Maximum 100 caractères.'),
                    new Assert\Regex(
                        pattern: '/^[\p{L}0-9 ,\-_\/]+$/u',
                        message: 'Caractères spéciaux non autorisés.'
                    ),
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
                'constraints' => [
                    new Assert\File(
                        maxSize: '5M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                        mimeTypesMessage: 'Format accepté: JPEG, PNG, GIF, WebP'
                    ),
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
