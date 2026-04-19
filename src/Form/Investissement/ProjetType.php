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
            // ── Section 1 : Informations de base ─────────────────────────────
            ->add('title', TextType::class, [
                'label' => 'Titre du projet',
                'attr'  => [
                    'placeholder' => 'Ex : Application FinTech mobile',
                    'maxlength'   => 255,
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
                'attr'     => ['placeholder' => '50 000'],
            ])
            ->add('status', ChoiceType::class, [
                'label'    => 'Statut du projet',
                'required' => true,
                'choices'  => Project::STATUTS,
            ])

            // ── Section 2 : Vision & Description ─────────────────────────────
            ->add('description', TextareaType::class, [
                'label'    => 'Description générale',
                'required' => true,
                'attr'     => [
                    'rows'        => 5,
                    'placeholder' => 'Vue d\'ensemble du projet, vision, contexte général…',
                    'maxlength'   => 5000,
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'La description est obligatoire.'),
                    new Assert\Length(
                        min: 20,
                        max: 5000,
                        minMessage: 'La description doit contenir au moins {{ limit }} caractères.',
                        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.'
                    ),
                ],
            ])
            ->add('problem_description', TextareaType::class, [
                'label'    => 'Problème résolu',
                'required' => false,
                'attr'     => [
                    'rows'        => 4,
                    'placeholder' => 'Quel problème réel et concret ce projet résout-il ? Qui en souffre et pourquoi les solutions actuelles sont insuffisantes ?',
                    'maxlength'   => 2000,
                ],
            ])
            ->add('solution_description', TextareaType::class, [
                'label'    => 'Solution proposée',
                'required' => false,
                'attr'     => [
                    'rows'        => 4,
                    'placeholder' => 'Décrivez votre produit ou service : comment fonctionne-t-il ? Quelle est votre valeur ajoutée unique ?',
                    'maxlength'   => 2000,
                ],
            ])

            // ── Section 3 : Marché & Positionnement ──────────────────────────
            ->add('target_audience', TextareaType::class, [
                'label'    => 'Public cible',
                'required' => false,
                'attr'     => [
                    'rows'        => 3,
                    'placeholder' => 'Qui sont vos clients / utilisateurs ? Âge, secteur, profil, comportement d\'achat…',
                    'maxlength'   => 1000,
                ],
            ])
            ->add('market_scope', ChoiceType::class, [
                'label'       => 'Marché visé',
                'required'    => false,
                'placeholder' => '— Sélectionner —',
                'choices'     => Project::MARCHES,
            ])
            ->add('competitive_advantage', TextareaType::class, [
                'label'    => 'Avantage concurrentiel',
                'required' => false,
                'attr'     => [
                    'rows'        => 3,
                    'placeholder' => 'Qu\'est-ce qui vous différencie des concurrents ? Technologie, prix, réseau, partenariats, propriété intellectuelle…',
                    'maxlength'   => 1500,
                ],
            ])

            // ── Section 4 : Modèle économique ────────────────────────────────
            ->add('business_model', ChoiceType::class, [
                'label'       => 'Modèle économique',
                'required'    => false,
                'placeholder' => '— Sélectionner —',
                'choices'     => Project::BUSINESS_MODELS,
            ])
            ->add('financial_forecast', TextareaType::class, [
                'label'    => 'Prévisions / objectifs financiers',
                'required' => false,
                'attr'     => [
                    'rows'        => 3,
                    'placeholder' => 'Ex : CA cible à 12 mois, point d\'équilibre, nombre de clients visés, revenu récurrent mensuel attendu…',
                    'maxlength'   => 1000,
                ],
            ])
            ->add('funding_usage', TextareaType::class, [
                'label'    => 'Utilisation du financement',
                'required' => false,
                'attr'     => [
                    'rows'        => 3,
                    'placeholder' => 'Comment le budget sera-t-il réparti ? Développement produit, marketing, recrutement, équipement…',
                    'maxlength'   => 1500,
                ],
            ])

            // ── Section 5 : Équipe & Stade ────────────────────────────────────
            ->add('project_stage', ChoiceType::class, [
                'label'       => 'Stade du projet',
                'required'    => false,
                'placeholder' => '— Sélectionner —',
                'choices'     => Project::STADES,
            ])
            ->add('team_description', TextType::class, [
                'label'    => 'Équipe & ressources',
                'required' => false,
                'attr'     => [
                    'placeholder' => 'Ex : 3 associés (dev, marketing, finance) + 1 conseiller senior',
                    'maxlength'   => 500,
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Project::class,
        ]);
    }
}
