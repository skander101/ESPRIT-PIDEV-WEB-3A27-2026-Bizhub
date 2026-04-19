<?php

namespace App\Form\Investissement;

use App\Entity\Investissement\Investment;
use App\Entity\Investissement\Project;
use App\Entity\UsersAvis\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InvestissementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('project', EntityType::class, [
                'label'        => 'Projet',
                'class'        => Project::class,
                'choice_label' => 'title',
                'placeholder'  => '-- Sélectionner un projet --',
            ])
            ->add('user', EntityType::class, [
                'label'        => 'Investisseur',
                'class'        => User::class,
                'choice_label' => 'email',
                'placeholder'  => '-- Sélectionner un investisseur --',
            ])
            ->add('amount', MoneyType::class, [
                'label'    => 'Montant investi (TND)',
                'currency' => false,
                'attr'     => ['placeholder' => '10000'],
            ])
            ->add('investment_date', DateTimeType::class, [
                'label'    => "Date d'investissement",
                'widget'   => 'single_text',
                'required' => false,
            ])
            ->add('contract_url', TextType::class, [
                'label'    => 'URL du contrat',
                'required' => false,
                'attr'     => ['placeholder' => 'https://…'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Investment::class,
        ]);
    }
}
