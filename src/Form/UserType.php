<?php

namespace App\Form;

use App\Entity\User;
use App\Form\WireguardPeerType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Email;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Show only the Wireguard peers.
        if ($options['update'] === 'peers') {
            $builder->add('wireguardPeers', CollectionType::class, [
                'entry_type' => WireguardPeerType::class,
                'entry_options' => ['label' => false],
            ]);
        } else {
            if ($options['update'] !== 'password') {
                if ($options['update']) {
                    $builder->add('password', TextType::class, [
                        'label' => 'Please enter your password.',
                        'mapped' => false,
                    ]);
                }
                $builder->add('username', TextType::class, [
                    'constraints' => [
                        new Regex(
                            pattern: '/^[a-z]\w{0, 63}$/i',
                            message: 'You must enter a valid username (no spaces or special chacters).',
                        ),
                        new NotBlank(
                            message: 'Please choose a username ya dingus.',
                        ),
                    ]
                ]);
            } else {
                $builder->add('password', PasswordType::class, [
                    'label' => 'Please enter your current password.',
                    'mapped' => false,
                ]);
            }
            if (!$options['update'] || $options['update'] === 'password') {
                $builder->add('plainPassword', RepeatedType::class, [
                    'mapped' => false,
                    'type' => PasswordType::class,
                    'invalid_message' => 'Sorry, your passwords don\'t match. Please try again.',
                    'required' => true,
                    'first_options'  => ['label' => 'Password'],
                    'second_options' => ['label' => 'Confirm Password'],
                    'constraints' => [
                        new NotBlank(
                            message: 'Please choose a password.'
                        ),
                        new Length(
                            min: 6,
                            minMessage: 'Please choose a password that is at least {{ limit }} characters.',
                            max: 4096,
                        ),
                    ],
                ]);
            }
            if ($options['update'] !== 'password') {
                $builder
                    ->add('email', EmailType::class, [
                        'label' => 'Email Address',
                        'constraints' => [
                            new Length(
                                min: 5,
                                max: 256,
                                maxMessage: 'Your email address cannot be longer than 256 characters.',
                            ),
                            new Email(
                                message: 'You must enter a valid email address.',
                            ),
                            new NotBlank(
                                message: 'You must enter an email address.',
                            ),
                        ]
                    ])
                    ->add('contactMethod', ChoiceType::class, [
                        'label' => $options['methodLabel'],
                        'choices' => [
                            'Email' => 'Email',
                            'IRC' => 'IRC',
                            'Discord' => 'Discord',
                            'Other' => 'Other',
                        ],
                        'constraints' => [
                            new NotBlank(
                                message: 'We need to know how to reach you.',
                            ),
                        ],
                    ])
                    ->add('contactDetails', TextareaType::class, [
                        'required' => false,
                        'label' => $options['detailsLabel'],
                    ])
                ;
                // Updating WireGuard is handled seperately.
                // All the important info about Wireguard peers MUST be printed manually!!!
                if ($options['update']) {
                    $builder->add('wireguardPeers', CollectionType::class, [
                        'entry_type' => WireguardPeerType::class,
                        'entry_options' => ['label' => false],
                    ]);
                } else {
                    $builder->add('pubKey', TextType::class, [
                        'label' => 'Wireguard Public Key',
                        'constraints' => [
                            new Regex(
                                pattern: '/^[a-z0-9\+\/]{43}=$/i',
                                message: 'You must enter a valid WireGuard public key.',
                            ),
                            new NotBlank(
                                message: 'You must enter a WireGuard public key.',
                            ),
                        ],
                    ]);
                }
                if ($options['type'] === 'user') {
                    $builder
                        ->add('plan', TextareaType::class, [
                            'label' => 'What do you plan to do on CGHMN?',
                            'constraints' => [
                                new NotBlank(
                                    message: 'Please tell us what you plan to do on CGHMN!',
                                ),
                            ],
                        ])
                        ->add('needsHosting', CheckboxType::class, [
                            'label' => 'Do you need hosting from CGHMN?',
                            'required' => false,
                        ])
                        ->add('hasExperience', CheckboxType::class, [
                            'label' => 'Do you have sys admin/networking experience?',
                            'required' => false,
                        ])
                    ;
                } else if ($options['type'] === 'admin') {
                    $builder
                        ->add('pubKey', HiddenType::class, [
                            'data' => '0000000000000000000000000000000000000000000=',
                        ])
                        ->add('plan', HiddenType::class, [
                            'data' => 'Be an admin.',
                        ])
                        ->add('super', CheckboxType::class, [
                            'mapped' => false,
                            'required' => false,
                            'label' => 'Should this admin be able to create new admins? (!!DANGEROUS!!)'
                        ])
                    ;
                }
            }
        }
        $builder->add('submit', SubmitType::class, ['label' => 'Submit']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'type' => 'user',
            'update' => false,
            'methodLabel' => 'What is your preferred contact method? (If other, please specify below.)',
            'detailsLabel' => 'How can we reach you?',
        ]);
        $resolver->setAllowedTypes('type', 'string');
    }
}
