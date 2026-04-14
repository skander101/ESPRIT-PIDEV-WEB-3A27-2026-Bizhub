<?php

namespace App\Form\Investissement;

use App\Entity\Investissement\Negotiation;
use App\Entity\Investissement\Project;
use App\Entity\UsersAvis\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class NegociationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('project', EntityType::class, [
                'class' => Project::class,
                'choice_label' => 'title',
                'placeholder' => '-- Sélectionner un projet --',
            ])
            ->add('investor', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'email',
                'placeholder' => '-- Sélectionner un investisseur --',
            ])
            ->add('startup', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'email',
                'placeholder' => '-- Sélectionner une startup --',
            ])
            ->add('status', ChoiceType::class, [
                'required' => true,
                'choices' => Negotiation::STATUTS,
            ])
            ->add('proposed_amount', MoneyType::class, [
                'currency' => false,
                'required' => false,
            ])
            ->add('final_amount', MoneyType::class, [
                'currency' => false,
                'required' => false,
            ])
            ->add('created_at', DateTimeType::class, [
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('updated_at', DateTimeType::class, [
                'widget' => 'single_text',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Negotiation::class,
        ]);
    }
}
