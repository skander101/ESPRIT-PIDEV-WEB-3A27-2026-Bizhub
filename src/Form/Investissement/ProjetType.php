<?php

namespace App\Form\Investissement;

use App\Entity\Investissement\Project;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ProjetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre du projet',
                'attr'  => ['placeholder' => 'Ex: Application FinTech mobile'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le titre est obligatoire.'),
                    new Assert\Length(['min' => 3, 'max' => 255]),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label'    => 'Description',
                'required' => true,
                'attr'     => ['rows' => 5, 'placeholder' => 'Décrivez votre projet, votre vision, votre marché cible…', 'maxlength' => 5000],
                'constraints' => [
                    new Assert\NotBlank(
                        message: 'La description est obligatoire.',
                        normalizer: 'trim',
                    ),
                    new Assert\Length([
                        'min'        => 20,
                        'max'        => 5000,
                        'minMessage' => 'La description doit contenir au moins {{ limit }} caractères.',
                        'maxMessage' => 'La description ne peut pas dépasser {{ limit }} caractères.',
                    ]),
                ],
            ])
            ->add('secteur', ChoiceType::class, [
                'label'       => "Secteur d'activité",
                'required'    => true,
                'placeholder' => '— Choisir un secteur —',
                'choices'     => Project::SECTEURS,
                'constraints' => [
                    new Assert\NotBlank(message: 'Veuillez sélectionner un secteur.'),
                ],
            ])
            ->add('required_budget', MoneyType::class, [
                'label'    => 'Budget requis (TND)',
                'currency' => false,
                'attr'     => ['placeholder' => '50000'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le budget est obligatoire.'),
                    new Assert\Positive(message: 'Le budget doit être positif.'),
                ],
            ])
            ->add('status', ChoiceType::class, [
                'label'   => 'Statut',
                'choices' => Project::STATUTS,
                'constraints' => [
                    new Assert\NotBlank(message: 'Veuillez choisir un statut.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Project::class,
        ]);
    }
}
