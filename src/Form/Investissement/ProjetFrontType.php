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

class ProjetFrontType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre du projet',
                'attr' => [
                    'placeholder' => 'Ex: Application mobile de livraison',
                    'class' => 'front-input',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Décrivez votre projet, vos objectifs, votre marché cible...',
                    'class' => 'front-input',
                    'rows' => 5,
                ],
            ])
            ->add('required_budget', MoneyType::class, [
                'label' => 'Budget requis (TND)',
                'currency' => 'TND',
                'attr' => [
                    'placeholder' => '50000',
                    'class' => 'front-input',
                ],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'required' => true,
                'choices' => Project::STATUTS,
                'attr' => ['class' => 'front-input'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Project::class,
        ]);
    }
}
