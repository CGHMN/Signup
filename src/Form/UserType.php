<?php

namespace App\Form;

use App\Entity\User;
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
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Email;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'constraints' => [
                    new Regex(
                        pattern: '/^[a-z]\w{0, 63}$/i',
                        message: 'You must enter a valid username (no spaces or special chacters).',
                    ),
                    new NotBlank(
                        message: 'Please choose a username ya dingus.',
                    ),
                ]
            ])
            ->add('plainPassword', RepeatedType::class, [
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
            ])
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
            ->add('pubKey', TextType::class, [
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
            ])
            ->add('plan', TextareaType::class, [
                'label' => 'What do you plan to do on CGHMN?',
                'constraints' => [
                    new NotBlank(
                        message: 'Please tell us what you plan to do on CGHMN!',
                    ),
                ],
            ])
            ->add('needsHosting')
            ->add('hasExperience')
            ->add('contactMethod', ChoiceType::class, [
                'label' => 'What is you\'re preferred contact method? (If other, please specify below.)',
                'choices' => [
                    'Email' => 'email',
                    'IRC' => 'irc',
                    'Discord' => 'discord',
                    'Other' => 'other',
                ],
                'constraints' => [
                    new NotBlank(
                        message: 'We need to know how to reach you.',
                    ),
                ],
            ])
            ->add('contactDetails', TextType::class, ['required' => false])
            ->add('submit', SubmitType::class, ['label' => 'Submit'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
