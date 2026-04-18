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
                'constraints' => [
                    new Assert\NotBlank(message: 'Le titre est obligatoire.'),
                    new Assert\Length(min: 3, max: 255, minMessage: 'Minimum 3 caractères.', maxMessage: 'Maximum 255 caractères.'),
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
                'constraints' => [
                    new Assert\Length(max: 5000, maxMessage: 'Maximum 5000 caractères.'),
                ],
            ])
            ->add('required_budget', MoneyType::class, [
                'label' => 'Budget requis (TND)',
                'currency' => 'TND',
                'attr' => [
                    'placeholder' => '50000',
                    'class' => 'front-input',
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le budget est obligatoire.'),
                    new Assert\Positive(message: 'Le budget doit être positif.'),
                ],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'En attente' => 'pending',
                    'En cours' => 'in_progress',
                    'Financé' => 'funded',
                    'Terminé' => 'completed',
                ],
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
