<?php

declare(strict_types=1);

namespace App\Form\Elearning;

use App\Entity\Elearning\Formation;
use App\Entity\UsersAvis\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FormationType extends AbstractType
{
    public function __construct(
        private readonly FormationLocationFormSubscriber $formationLocationFormSubscriber,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'required' => true,
                'attr' => [
                    'data-validate-field' => 'title',
                    'maxlength' => 200,
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
            ])
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => fn (User $u) => $u->getFullName() ?? $u->getEmail(),
                'label' => 'Formateur',
                'required' => true,
                'attr' => [
                    'data-validate-field' => 'trainer',
                ],
            ])
            ->add('start_date', DateType::class, [
                'label' => 'Date de début',
                'required' => true,
                'widget' => 'single_text',
                'attr' => [
                    'data-validate-field' => 'start_date',
                ],
            ])
            ->add('end_date', DateType::class, [
                'label' => 'Date de fin',
                'required' => true,
                'widget' => 'single_text',
                'attr' => [
                    'data-validate-field' => 'end_date',
                ],
            ])
            ->add('cost', MoneyType::class, [
                'label' => 'Coût (TND)',
                'required' => false,
                'currency' => 'TND',
                'scale' => 2,
                'attr' => [
                    'data-validate-field' => 'cost',
                ],
            ])
            ->add('en_ligne', ChoiceType::class, [
                'label' => 'Type de formation',
                'choices' => [
                    'Présentielle' => false,
                    'En ligne' => true,
                ],
                'required' => true,
                'placeholder' => false,
                'attr' => [
                    'class' => 'form-select js-formation-type-select',
                    'data-validate-field' => 'formation_type',
                ],
            ])
            ->add('lieu', TextType::class, [
                'label' => 'Lieu (adresse)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control js-formation-lieu',
                    'data-validate-field' => 'lieu',
                    'maxlength' => 500,
                    'placeholder' => 'Cliquez sur la carte pour remplir l’adresse (modifiable)',
                ],
                'help' => 'Rempli automatiquement depuis la carte (vous pouvez corriger le texte si besoin).',
            ])
            ->add('latitude', HiddenType::class, [
                'required' => false,
                'attr' => [
                    'class' => 'js-formation-latitude',
                    'data-validate-field' => 'latitude',
                ],
            ])
            ->add('longitude', HiddenType::class, [
                'required' => false,
                'attr' => [
                    'class' => 'js-formation-longitude',
                    'data-validate-field' => 'longitude',
                ],
            ])
            ->add('max_formateurs', IntegerType::class, [
                'label' => 'Nombre max de formateurs',
                'required' => true,
                'attr' => [
                    'min' => 1,
                ],
            ]);

        $builder->addEventSubscriber($this->formationLocationFormSubscriber);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Formation::class,
        ]);
    }
}
