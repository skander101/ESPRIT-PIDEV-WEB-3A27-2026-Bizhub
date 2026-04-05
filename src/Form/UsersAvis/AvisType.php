<?php

namespace App\Form\UsersAvis;

use App\Entity\UsersAvis\Avis;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class AvisType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('rating', ChoiceType::class, [
                'label' => 'Rating',
                'choices' => [
                    '⭐' => 1,
                    '⭐⭐' => 2,
                    '⭐⭐⭐' => 3,
                    '⭐⭐⭐⭐' => 4,
                    '⭐⭐⭐⭐⭐' => 5,
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Please select a rating']),
                    new Assert\Choice([
                        'choices' => [1, 2, 3, 4, 5],
                        'message' => 'Rating must be between 1 and 5',
                    ]),
                ],
            ])
            ->add('comment', TextareaType::class, [
                'label' => 'Comment',
                'required' => false,
                'constraints' => [
                    new Assert\Length([
                        'max' => 1000,
                        'maxMessage' => 'Comment must not exceed 1000 characters',
                    ]),
                ],
                'attr' => [
                    'rows' => 5,
                    'placeholder' => 'Share your thoughts about this course...',
                ],
            ]);

        // Only show is_verified field for admin users
        if ($options['allow_admin_fields']) {
            $builder
                ->add('is_verified', CheckboxType::class, [
                    'label' => 'Verify this review',
                    'required' => false,
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'data_class' => Avis::class,
                'allow_admin_fields' => false,
            ]);
    }
}
