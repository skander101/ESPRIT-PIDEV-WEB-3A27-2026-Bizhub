<?php

namespace App\Form\UsersAvis;

use App\Entity\UsersAvis\Avis;
use App\Entity\Elearning\Formation;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AvisType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('formation', EntityType::class, [
                'label' => 'Formation',
                'class' => Formation::class,
                'choice_label' => 'title',
                'placeholder' => '-- Select a formation --',
            ])
            ->add('rating', ChoiceType::class, [
                'label' => 'Rating',
                'choices' => [
                    '⭐' => 1,
                    '⭐⭐' => 2,
                    '⭐⭐⭐' => 3,
                    '⭐⭐⭐⭐' => 4,
                    '⭐⭐⭐⭐⭐' => 5,
                ],
            ])
            ->add('comment', TextareaType::class, [
                'label' => 'Comment',
                'required' => false,
                'attr' => [
                    'rows' => 5,
                    'placeholder' => 'Share your thoughts about this course...',
                ],
            ]);

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
