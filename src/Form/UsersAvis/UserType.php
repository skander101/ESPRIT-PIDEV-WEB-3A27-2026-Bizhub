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
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class UserType extends AbstractType
{
    private const DEFAULT_PHONE_PREFIX = '+216';

    private const PHONE_PREFIXES = [
        'Tunisia (+216)' => '+216',
        'Algeria (+213)' => '+213',
        'Morocco (+212)' => '+212',
        'France (+33)' => '+33',
        'Italy (+39)' => '+39',
        'Germany (+49)' => '+49',
        'United Kingdom (+44)' => '+44',
        'United States (+1)' => '+1',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['mode'] !== 'editSpecific') {
            $builder
                ->add('email', EmailType::class, [
                    'label' => 'Email',
                ]);

            if ($options['mode'] === 'register') {
                $builder
                    ->add('first_name', TextType::class, [
                        'label' => 'First Name',
                        'mapped' => false,
                        'required' => false,
                        'constraints' => [
                            new NotBlank(message: 'First name is required.'),
                        ],
                    ])
                    ->add('last_name', TextType::class, [
                        'label' => 'Last Name',
                        'mapped' => false,
                        'required' => false,
                        'constraints' => [
                            new NotBlank(message: 'Last name is required.'),
                        ],
                    ])
                    ->add('full_name', HiddenType::class, [
                        'property_path' => 'full_name',
                    ]);
            } else {
                $builder->add('full_name', TextType::class, [
                    'label' => 'Full Name',
                    'property_path' => 'full_name',
                ]);
            }

            $builder
                ->add('phone_country_code', ChoiceType::class, [
                    'label' => 'Country Prefix',
                    'mapped' => false,
                    'required' => false,
                    'placeholder' => false,
                    'choices' => self::PHONE_PREFIXES,
                    'data' => self::DEFAULT_PHONE_PREFIX,
                ])
                ->add('phone', TextType::class, [
                    'label' => 'Phone Number',
                    'property_path' => 'phone',
                    'required' => false,
                    'empty_data' => null,
                    'attr' => [
                        'placeholder' => '12 345 678',
                    ],
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

            $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
                $form = $event->getForm();
                $data = $event->getData();

                if (!$form->has('phone') || !$form->has('phone_country_code')) {
                    return;
                }

                $rawPhone = $data instanceof User ? $data->getPhone() : null;
                [$prefix, $localNumber] = $this->splitPhoneForForm($rawPhone);

                $form->get('phone_country_code')->setData($prefix ?? self::DEFAULT_PHONE_PREFIX);
                $form->get('phone')->setData($localNumber);

                if ($form->has('first_name') && $form->has('last_name')) {
                    $rawFullName = $data instanceof User ? $data->getFullName() : null;
                    [$firstName, $lastName] = $this->splitFullNameForForm($rawFullName);

                    $form->get('first_name')->setData($firstName);
                    $form->get('last_name')->setData($lastName);
                }
            });

            $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
                $data = $event->getData();

                if (!is_array($data)) {
                    return;
                }

                if (array_key_exists('first_name', $data) || array_key_exists('last_name', $data)) {
                    $firstName = trim((string) ($data['first_name'] ?? ''));
                    $lastName = trim((string) ($data['last_name'] ?? ''));
                    $fullName = trim(sprintf('%s %s', $firstName, $lastName));

                    if ($fullName !== '') {
                        $data['full_name'] = $fullName;
                    }
                }

                $prefix = trim((string) ($data['phone_country_code'] ?? ''));
                $number = trim((string) ($data['phone'] ?? ''));

                if ($number === '') {
                    $data['phone'] = null;
                    $event->setData($data);

                    return;
                }

                $normalizedNumber = preg_replace('/\s+/', ' ', $number) ?: $number;

                if (str_starts_with($normalizedNumber, '+')) {
                    $data['phone'] = $normalizedNumber;
                } elseif ($prefix !== '') {
                    $data['phone'] = sprintf('%s %s', $prefix, ltrim($normalizedNumber, ' -'));
                } else {
                    $data['phone'] = $normalizedNumber;
                }

                $event->setData($data);
            });
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
                    'constraints' => [
                        new NotBlank(message: 'Password is required.'),
                        new Length(min: 8, minMessage: 'Password must be at least {{ limit }} characters long.'),
                        new Regex(pattern: '/[A-Z]/', message: 'Password must include at least one uppercase letter.'),
                        new Regex(pattern: '/\d/', message: 'Password must include at least one number.'),
                    ],
                ])
                ->add('terms_accepted', CheckboxType::class, [
                    'mapped' => false,
                    'required' => false,
                    'label' => 'I accept the Terms and Privacy Policy',
                    'constraints' => [
                        new IsTrue(message: 'You must accept the terms to create an account.'),
                    ],
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

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function splitPhoneForForm(?string $rawPhone): array
    {
        $phone = trim((string) $rawPhone);
        if ($phone === '') {
            return [null, null];
        }

        $phone = preg_replace('/\s+/', ' ', $phone) ?: $phone;

        $codes = array_values(self::PHONE_PREFIXES);
        usort($codes, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($codes as $code) {
            if (!str_starts_with($phone, $code)) {
                continue;
            }

            $local = trim(substr($phone, strlen($code)));
            $local = ltrim($local, ' -');

            return [$code, $local !== '' ? $local : null];
        }

        return [null, $phone];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function splitFullNameForForm(?string $rawFullName): array
    {
        $fullName = trim((string) $rawFullName);
        if ($fullName === '') {
            return [null, null];
        }

        $parts = preg_split('/\s+/', $fullName) ?: [];
        if (count($parts) <= 1) {
            return [$parts[0] ?? null, null];
        }

        $firstName = array_shift($parts);
        $lastName = trim(implode(' ', $parts));

        return [$firstName !== '' ? $firstName : null, $lastName !== '' ? $lastName : null];
    }
}
