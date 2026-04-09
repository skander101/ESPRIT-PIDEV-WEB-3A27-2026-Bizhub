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

class ProjetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre du projet',
                'attr'  => [
                    'placeholder' => 'Ex: Application FinTech mobile',
                    'maxlength' => 255,
                ],
            ])
            ->add('description', TextareaType::class, [
                'label'    => 'Description',
                'required' => true,
                'attr'     => [
                    'rows' => 5,
                    'placeholder' => 'Décrivez votre projet, votre vision, votre marché cible…',
                    'maxlength' => 5000,
                ],
            ])
            ->add('secteur', ChoiceType::class, [
                'label'       => "Secteur d'activité",
                'required'    => true,
                'placeholder' => '— Choisir un secteur —',
                'choices'     => Project::SECTEURS,
            ])
            ->add('required_budget', MoneyType::class, [
                'label'    => 'Budget requis (TND)',
                'currency' => false,
                'attr'     => ['placeholder' => '50000'],
            ])
            ->add('status', ChoiceType::class, [
                'label'   => 'Statut',
                'required' => true,
                'choices' => Project::STATUTS,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Project::class,
        ]);
    }
}
