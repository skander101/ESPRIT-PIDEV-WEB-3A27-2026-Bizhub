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
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Constraints\File;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Basic fields for both registration and edit
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Email is required']),
                    new Assert\Email(['message' => 'Please enter a valid email']),
                ],
            ])
            ->add('full_name', TextType::class, [
                'label' => 'Full Name',
                'property_path' => 'full_name',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Full name is required']),
                    new Assert\Length([
                        'min' => 2,
                        'max' => 255,
                        'minMessage' => 'Name must be at least 2 characters',
                        'maxMessage' => 'Name must not exceed 255 characters',
                    ]),
                ],
            ])
            ->add('phone', TextType::class, [
                'label' => 'Phone Number',
                'property_path' => 'phone',
                'required' => false,
                'constraints' => [
                    new Assert\Length([
                        'min' => 9,
                        'max' => 20,
                        'minMessage' => 'Phone number must be at least 9 digits',
                        'maxMessage' => 'Phone number must not exceed 20 characters',
                    ]),
                ],
            ])
            ->add('address', TextType::class, [
                'label' => 'Address',
                'property_path' => 'address',
                'required' => false,
                'constraints' => [
                    new Assert\Length([
                        'max' => 255,
                        'maxMessage' => 'Address must not exceed 255 characters',
                    ]),
                ],
            ])
            ->add('bio', TextareaType::class, [
                'label' => 'Bio',
                'property_path' => 'bio',
                'required' => false,
                'constraints' => [
                    new Assert\Length([
                        'max' => 1000,
                        'maxMessage' => 'Bio must not exceed 1000 characters',
                    ]),
                ],
            ]);

        // User type only during registration
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
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Please select a user type']),
                    new Assert\Choice([
                        'choices' => ['startup', 'fournisseur', 'formateur', 'investisseur'],
                        'message' => 'Invalid user type',
                    ]),
                ],
            ]);
        }

        // Avatar upload only in edit mode
        if ($options['mode'] === 'edit') {
            $builder->add('avatar', FileType::class, [
                'label' => 'Profile Picture',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/gif',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Please upload a valid image (JPEG, PNG, GIF, or WebP)',
                    ])
                ],
            ]);
        }

        // Role-specific fields only for registration and editSpecific mode (NOT edit mode)
        if ($options['mode'] !== 'edit') {
            $builder
                // STARTUP & FOURNISSEUR: Company Information
                ->add('company_name', TextType::class, [
                    'label' => 'Company Name',
                    'property_path' => 'companyName',
                    'required' => false,
                    'constraints' => [
                        new Assert\Length([
                            'max' => 255,
                            'maxMessage' => 'Company name must not exceed 255 characters',
                        ]),
                    ],
                ])
                ->add('sector', TextType::class, [
                    'label' => 'Sector',
                    'property_path' => 'sector',
                    'required' => false,
                    'constraints' => [
                        new Assert\Length([
                            'max' => 255,
                            'maxMessage' => 'Sector must not exceed 255 characters',
                        ]),
                    ],
                ])
                ->add('company_description', TextareaType::class, [
                    'label' => 'Company Description',
                    'property_path' => 'companyDescription',
                    'required' => false,
                    'constraints' => [
                        new Assert\Length([
                            'max' => 5000,
                            'maxMessage' => 'Company description must not exceed 5000 characters',
                        ]),
                    ],
                ])
                // STARTUP only
                ->add('website', TextType::class, [
                    'label' => 'Website',
                    'property_path' => 'website',
                    'required' => false,
                    'constraints' => [
                        new Assert\Length([
                            'max' => 255,
                            'maxMessage' => 'Website must not exceed 255 characters',
                        ]),
                    ],
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
                    'constraints' => [
                        new Assert\Length([
                            'max' => 255,
                            'maxMessage' => 'Business type must not exceed 255 characters',
                        ]),
                    ],
                ])
                // FOURNISSEUR only
                ->add('delivery_zones', TextareaType::class, [
                    'label' => 'Delivery Zones',
                    'property_path' => 'deliveryZones',
                    'required' => false,
                    'constraints' => [
                        new Assert\Length([
                            'max' => 2000,
                            'maxMessage' => 'Delivery zones must not exceed 2000 characters',
                        ]),
                    ],
                ])
                ->add('payment_methods', TextType::class, [
                    'label' => 'Payment Methods',
                    'property_path' => 'paymentMethods',
                    'required' => false,
                    'constraints' => [
                        new Assert\Length([
                            'max' => 255,
                            'maxMessage' => 'Payment methods must not exceed 255 characters',
                        ]),
                    ],
                ])
                ->add('return_policy', TextareaType::class, [
                    'label' => 'Return Policy',
                    'property_path' => 'returnPolicy',
                    'required' => false,
                    'constraints' => [
                        new Assert\Length([
                            'max' => 2000,
                            'maxMessage' => 'Return policy must not exceed 2000 characters',
                        ]),
                    ],
                ])
                // FORMATEUR only
                ->add('specialty', TextType::class, [
                    'label' => 'Specialty',
                    'property_path' => 'specialty',
                    'required' => false,
                    'constraints' => [
                        new Assert\Length([
                            'max' => 255,
                            'maxMessage' => 'Specialty must not exceed 255 characters',
                        ]),
                    ],
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
                    'constraints' => [
                        new Assert\Length([
                            'max' => 1000,
                            'maxMessage' => 'Availability must not exceed 1000 characters',
                        ]),
                    ],
                ])
                ->add('cv_url', TextType::class, [
                    'label' => 'CV URL',
                    'property_path' => 'cvUrl',
                    'required' => false,
                    'constraints' => [
                        new Assert\Length([
                            'max' => 255,
                            'maxMessage' => 'CV URL must not exceed 255 characters',
                        ]),
                    ],
                ])
                // INVESTISSEUR only
                ->add('represented_company', TextType::class, [
                    'label' => 'Represented Company',
                    'property_path' => 'representedCompany',
                    'required' => false,
                    'constraints' => [
                        new Assert\Length([
                            'max' => 255,
                            'maxMessage' => 'Represented company must not exceed 255 characters',
                        ]),
                    ],
                ])
                ->add('investment_sector', TextType::class, [
                    'label' => 'Investment Sector',
                    'property_path' => 'investmentSector',
                    'required' => false,
                    'constraints' => [
                        new Assert\Length([
                            'max' => 255,
                            'maxMessage' => 'Investment sector must not exceed 255 characters',
                        ]),
                    ],
                ])
                ->add('max_budget', NumberType::class, [
                    'label' => 'Maximum Budget (€)',
                    'property_path' => 'maxBudget',
                    'required' => false,
                    'scale' => 2,
                ]);
        }

        // Password fields only for registration
        if ($options['mode'] === 'register') {
            $builder
                ->add('password', RepeatedType::class, [
                    'type' => PasswordType::class,
                    'mapped' => false,
                    'invalid_message' => 'Passwords do not match.',
                    'first_options' => [
                        'label' => 'Password',
                        'constraints' => [
                            new Assert\NotBlank(['message' => 'Password is required']),
                            new Assert\Length([
                                'min' => 8,
                                'minMessage' => 'Password must be at least 8 characters long',
                            ]),
                        ],
                    ],
                    'second_options' => [
                        'label' => 'Confirm Password',
                        'constraints' => [
                            new Assert\NotBlank(['message' => 'Please confirm your password']),
                        ],
                    ],
                ])
            ;
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefaults([
                'data_class' => User::class,
                'mode' => 'edit', // 'register', 'edit', or 'editSpecific'
            ])
            ->setAllowedValues('mode', ['register', 'edit', 'editSpecific']);
    }
}
