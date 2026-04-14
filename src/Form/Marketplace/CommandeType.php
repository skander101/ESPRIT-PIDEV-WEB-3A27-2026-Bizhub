<?php

namespace App\Form\Marketplace;

use App\Entity\Marketplace\Commande;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CommandeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isAdmin = $options['is_admin'] ?? false;

        $builder
            ->add('idClient', IntegerType::class, [
                'label' => 'ID Client',
                'attr'  => ['placeholder' => 'ID du client'],
            ])
            ->add('idProduit', IntegerType::class, [
                'label'    => 'ID Produit',
                'required' => false,
                'attr'     => ['placeholder' => 'ID du produit (optionnel)'],
            ])
            ->add('quantite', IntegerType::class, [
                'label'    => 'Quantité',
                'required' => false,
                'attr'     => ['min' => 1],
            ])
            ->add('statut', ChoiceType::class, [
                'label'   => 'Statut',
                'required' => true,
                'choices' => [
                    'En attente' => Commande::STATUT_ATTENTE,
                    'Confirmée'  => Commande::STATUT_CONFIRMEE,
                    'Annulée'    => Commande::STATUT_ANNULEE,
                    'Livrée'     => Commande::STATUT_LIVREE,
                ],
            ]);

        if ($isAdmin) {
            $builder->add('paymentStatus', ChoiceType::class, [
                'label'    => 'Statut paiement',
                'required' => false,
                'choices'  => [
                    'Non initié' => 'non initié',
                    'En cours'   => 'en cours',
                    'Complété'   => 'complété',
                    'Échoué'     => 'échoué',
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Commande::class,
            'is_admin'   => false,
        ]);
    }
}
