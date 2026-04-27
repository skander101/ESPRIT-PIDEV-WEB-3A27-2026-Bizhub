<?php

namespace App\Form\Auth;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Form used to authenticate users with email + TOTP only.
 */
class TotpLoginType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email address',
                'constraints' => [
                    new Assert\NotBlank(message: 'Email is required.'),
                    new Assert\Email(message: 'Enter a valid email address.'),
                ],
            ])
            ->add('otp', TextType::class, [
                'label' => 'Authenticator code',
                'constraints' => [
                    new Assert\NotBlank(message: 'Code is required.'),
                    new Assert\Regex(pattern: '/^\d{6}$/', message: 'Code must contain exactly 6 digits.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'totp_login',
        ]);
    }
}
