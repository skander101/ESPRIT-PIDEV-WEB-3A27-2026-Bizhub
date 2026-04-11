<?php

namespace App\Form\Elearning;

use App\Entity\Elearning\Formation;
use App\Entity\UsersAvis\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FormationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'required' => true,
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
            ])
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => fn(User $u) => $u->getFullName() ?? $u->getEmail(),
                'label' => 'Formateur',
                'required' => true,
            ])
            ->add('start_date', DateType::class, [
                'label' => 'Date de début',
                'required' => true,
                'widget' => 'single_text',
            ])
            ->add('end_date', DateType::class, [
                'label' => 'Date de fin',
                'required' => true,
                'widget' => 'single_text',
            ])
            ->add('cost', MoneyType::class, [
                'label' => 'Coût (TND)',
                'required' => false,
                'currency' => 'TND',
                'scale' => 2,
            ])
            ->add('lieu', TextType::class, [
                'label' => 'Lieu',
                'required' => true,
            ])
            ->add('en_ligne', CheckboxType::class, [
                'label' => 'En ligne',
                'required' => false,
            ])
            ->add('max_formateurs', IntegerType::class, [
                'label' => 'Nombre max de formateurs',
                'required' => true,
                'attr' => [
                    'min' => 1,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Formation::class,
        ]);
    }
}
