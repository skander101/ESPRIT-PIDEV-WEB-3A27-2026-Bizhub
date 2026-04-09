<?php

namespace App\Form\UsersAvis;

use App\Entity\UsersAvis\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['mode'] !== 'editSpecific') {
            $builder
                ->add('email', EmailType::class, [
                    'label' => 'Email',
                ])
                ->add('full_name', TextType::class, [
                    'label' => 'Full Name',
                    'property_path' => 'full_name',
                ])
                ->add('phone', TextType::class, [
                    'label' => 'Phone Number',
                    'property_path' => 'phone',
                    'required' => false,
                    'empty_data' => null,
                ])
                ->add('address', TextType::class, [
                    'label' => 'Address',
                    'property_path' => 'address',
                    'required' => false,
                ])
                ->add('bio', TextareaType::class, [
                    'label' => 'Bio',
                    'property_path' => 'bio',
                    'required' => false,
                ]);
        }

        if ($options['mode'] === 'register') {
            $builder->add('user_type', ChoiceType::class, [
                'label' => 'User Type',
                'property_path' => 'user_type',
                'choices' => [
                    'Startup' => 'startup',
                    'Supplier' => 'fournisseur',
                    'Trainer' => 'formateur',
                    'Investor' => 'investisseur',
                ],
            ]);
        }

        if ($options['mode'] === 'edit') {
            $builder->add('avatar', FileType::class, [
                'label' => 'Profile Picture',
                'mapped' => false,
                'required' => false,
            ]);
        }

        if ($options['mode'] !== 'edit') {
            $builder
                ->add('company_name', TextType::class, [
                    'label' => 'Company Name',
                    'property_path' => 'companyName',
                    'required' => false,
                ])
                ->add('sector', TextType::class, [
                    'label' => 'Sector',
                    'property_path' => 'sector',
                    'required' => false,
                ])
                ->add('company_description', TextareaType::class, [
                    'label' => 'Company Description',
                    'property_path' => 'companyDescription',
                    'required' => false,
                ])
                ->add('website', TextType::class, [
                    'label' => 'Website',
                    'property_path' => 'website',
                    'required' => false,
                ])
                ->add('founding_date', DateType::class, [
                    'label' => 'Founding Date',
                    'property_path' => 'foundingDate',
                    'required' => false,
                    'widget' => 'single_text',
                ])
                ->add('business_type', TextType::class, [
                    'label' => 'Business Type',
                    'property_path' => 'businessType',
                    'required' => false,
                ])
                ->add('delivery_zones', TextareaType::class, [
                    'label' => 'Delivery Zones',
                    'property_path' => 'deliveryZones',
                    'required' => false,
                ])
                ->add('payment_methods', TextType::class, [
                    'label' => 'Payment Methods',
                    'property_path' => 'paymentMethods',
                    'required' => false,
                ])
                ->add('return_policy', TextareaType::class, [
                    'label' => 'Return Policy',
                    'property_path' => 'returnPolicy',
                    'required' => false,
                ])
                ->add('specialty', TextType::class, [
                    'label' => 'Specialty',
                    'property_path' => 'specialty',
                    'required' => false,
                ])
                ->add('years_experience', IntegerType::class, [
                    'label' => 'Years of Experience',
                    'property_path' => 'yearsExperience',
                    'required' => false,
                ])
                ->add('hourly_rate', NumberType::class, [
                    'label' => 'Hourly Rate (€)',
                    'property_path' => 'hourlyRate',
                    'required' => false,
                    'scale' => 2,
                ])
                ->add('availability', TextareaType::class, [
                    'label' => 'Availability',
                    'property_path' => 'availability',
                    'required' => false,
                ])
                ->add('cv_url', TextType::class, [
                    'label' => 'CV URL',
                    'property_path' => 'cvUrl',
                    'required' => false,
                ])
                ->add('represented_company', TextType::class, [
                    'label' => 'Represented Company',
                    'property_path' => 'representedCompany',
                    'required' => false,
                ])
                ->add('investment_sector', TextType::class, [
                    'label' => 'Investment Sector',
                    'property_path' => 'investmentSector',
                    'required' => false,
                ])
                ->add('max_budget', NumberType::class, [
                    'label' => 'Maximum Budget (€)',
                    'property_path' => 'maxBudget',
                    'required' => false,
                    'scale' => 2,
                ]);
        }

        if ($options['mode'] === 'register') {
            $builder
                ->add('password', RepeatedType::class, [
                    'type' => PasswordType::class,
                    'mapped' => false,
                    'invalid_message' => 'Passwords do not match.',
                    'first_options' => ['label' => 'Password'],
                    'second_options' => ['label' => 'Confirm Password'],
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'data_class' => User::class,
                'mode' => 'edit',
            ])
            ->setAllowedValues('mode', ['register', 'edit', 'editSpecific']);
    }
}
