<?php

namespace App\Form\Elearning;

use App\Entity\UsersAvis\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FormationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'required' => false,
                'attr' => ['maxlength' => 200, 'class' => 'form-control', 'data-validate-field' => 'title'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 4, 'class' => 'form-control', 'data-validate-field' => 'description'],
            ])
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => fn (User $u) => $u->getFull_name().' ('.$u->getEmail().')',
                'label' => 'Formateur',
                'required' => false,
                'query_builder' => fn ($r) => $r->createQueryBuilder('u')->orderBy('u.full_name', 'ASC'),
                'attr' => ['class' => 'form-select', 'data-validate-field' => 'trainer'],
            ])
            ->add('start_date', DateType::class, [
                'label' => 'Date de début',
                'widget' => 'single_text',
                'html5' => true,
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'data-validate-field' => 'start_date',
                    'title' => 'Choisir la date avec le calendrier',
                ],
            ])
            ->add('end_date', DateType::class, [
                'label' => 'Date de fin',
                'widget' => 'single_text',
                'html5' => true,
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'data-validate-field' => 'end_date',
                    'title' => 'Choisir la date avec le calendrier',
                ],
            ])
            ->add('cost', NumberType::class, [
                'label' => 'Coût (TND)',
                'scale' => 2,
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'step' => '0.01',
                    'min' => '0',
                    'data-validate-field' => 'cost',
                    'title' => 'Montant positif ou zéro (pas de valeur négative)',
                ],
            ])
            ->add('lieu', TextType::class, [
                'label' => 'Lieu',
                'required' => false,
                'attr' => ['maxlength' => 300, 'class' => 'form-control', 'data-validate-field' => 'lieu'],
            ])
            ->add('enligne', CheckboxType::class, [
                'label' => 'Formation en ligne',
                'required' => false,
                'attr' => ['class' => 'form-check-input', 'data-validate-field' => 'enligne'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => \App\Entity\Elearning\Formation::class,
        ]);
    }
}
