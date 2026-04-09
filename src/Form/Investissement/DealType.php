<?php

namespace App\Form\Investissement;

use App\Entity\Investissement\Deal;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DealType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('negotiation_id', IntegerType::class, [
                'required' => false,
            ])
            ->add('project_id', IntegerType::class, [])
            ->add('buyer_id', IntegerType::class, [])
            ->add('seller_id', IntegerType::class, [])
            ->add('amount', MoneyType::class, [
                'currency' => false,
            ])
            ->add('stripe_payment_intent_id', TextType::class, [
                'required' => false,
            ])
            ->add('stripe_payment_status', TextType::class, [
                'required' => false,
            ])
            ->add('stripe_checkout_session_id', TextType::class, [
                'required' => false,
            ])
            ->add('contract_pdf_path', TextType::class, [
                'required' => false,
            ])
            ->add('yousign_signature_request_id', TextType::class, [
                'required' => false,
            ])
            ->add('yousign_status', TextType::class, [
                'required' => false,
            ])
            ->add('email_sent', CheckboxType::class, [
                'required' => false,
            ])
            ->add('status', ChoiceType::class, [
                'required' => true,
                'choices' => Deal::STATUTS,
            ])
            ->add('created_at', DateTimeType::class, [
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('completed_at', DateTimeType::class, [
                'widget' => 'single_text',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Deal::class,
        ]);
    }
}
