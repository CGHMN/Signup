<?php

/*
CGHMN Signup Page - A PHP project to ease the process of joining CGHMN.
Copyright (C) 2026 Logan C. et al. loganius@cghmn.org

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of  MERCHANTABILITY or FITNESS FOR
A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <http://www.gnu.org/licenses/>.

Many thanks to Jonas Luehrig (Snep) for all his contributions to this project,
both through writing code and providing advice.
*/

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
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;

// Big ol' form type for handling every form type relating to users/admins.
class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // If we're creating a new user/admin, show the prompt for their username.
        if (!$options['update']) {
            $builder->add('username', TextType::class, [
                'constraints' => [
                    new Regex(
                        pattern: '/^[a-z]\w{0, 63}$/i',
                        message: 'You must enter a valid username (no spaces or special chacters).',
                    ),
                    new NotBlank(
                        message: 'Please choose a username ya dingus.',
                    ),
                ],
                'label_attr' => ['class' => 'required-opt'],
            ]);
        }

        // If a user/admin is updating their password, they need to enter their current password.
        if ($options['update'] === 'password') {
            $builder->add('password', PasswordType::class, [
                'label' => 'Please enter your current password.',
                'constraints' => [
                    new NotBlank(
                        message: 'You must enter your password.',
                    ),
                ],
                'label_attr' => ['class' => 'required-opt'],
                'mapped' => false,
            ]);
        }

        // If we're creating a new user/admin, or updating a user/admin's password,
        // prompt for their new passsword.
        if (!$options['update'] || $options['update'] === 'password') {
            $builder->add('plainPassword', RepeatedType::class, [
                'mapped' => false,
                'type' => PasswordType::class,
                'invalid_message' => 'Sorry, your passwords don\'t match. Please try again.',
                'required' => true,
                'first_options'  => ['label' => 'Password'],
                'second_options' => ['label' => 'Confirm Password'],
                'options' => ['label_attr' => ['class' => 'required-opt']],
                'constraints' => [
                    new NotBlank(
                        message: 'Please choose a password.'
                    ),
                    new Length(
                        min: 6,
                        minMessage: 'Please choose a password that is at least {{ limit }} characters.',
                        max: 4096,
                    ),
                    new NotCompromisedPassword(),
                ],
            ]);
        }

        // If we're creating a new user/admin, or updating a user/admin's general profile,
        // show their email.
        if (!$options['update']) {
            $builder->add('email', EmailType::class, [
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
                ],
                'label_attr' => ['class' => 'required-opt'],
            ]);
        }

        // If we're creating a new user, show the prompt for their public key.
        if (!$options['update']) {
            if ($options['type'] === 'user') {
                $builder->add('pubKey', TextType::class, [
                    'constraints' => [
                        new Regex(
                            pattern: '/^[A-Za-z0-9+\/]{42}[AEIMQUYcgkosw480]=$/',
                            message: 'You must enter a valid WireGuard public key.',
                        ),
                        new NotBlank(
                            message: 'You must enter a WireGuard public key.',
                        ),
                    ],
                    'label_attr' => ['class' => 'required-opt'],
                ]);
            } else {
                $builder->add('pubKey', HiddenType::class, [
                    'data' => '0000000000000000000000000000000000000000000=',
                ]);
            }
        }

        // If we're updating a user's Wireguard peers,
        // show their Wireguard peers.
        if (($options['update'] === 'peers' || $options['update'] === 'profile') && $options['type'] === 'user') {
            $builder->add('wireguardPeers', CollectionType::class, [
                'entry_type' => WireguardPeerType::class,
                'entry_options' => ['label' => false],
            ]);
        }

        // If we're creating a new user, or updating a user's general profile,
        // show the prompt for their plan for CGHMN.
        if (!$options['update'] || $options['update'] === 'profile') {
            if ($options['type'] === 'user') {
                $builder->add('plan', TextareaType::class, [
                    'constraints' => [
                        new NotBlank(
                            message: 'Please tell us what you plan to do on CGHMN!',
                        ),
                    ],
                    'label_attr' => ['class' => 'required-opt'],
                ]);
            } else {
                $builder->add('plan', HiddenType::class, [
                    'data' => 'Be an admin.',
                ]);
            }
        }

        // If we're creating a new user, or updating a user's general profile,
        // show the prompt for whether they need hosting.
        if ((!$options['update'] || $options['update'] === 'profile') && $options['type'] === 'user') {
            $builder->add('needsHosting', ChoiceType::class, [
                'label' => 'Do you need hosting from CGHMN?',
                'choices' => [
                    'Yes' => true,
                    'No' => false,
                ],
                'expanded' => true,
                'multiple' => false,
                'required' => true,
            ]);
            $builder->add('hasExperience', ChoiceType::class, [
                'label' => 'Do you have sys admin/networking experience?',
                'choices' => [
                    'Yes' => true,
                    'No' => false,
                ],
                'expanded' => true,
                'multiple' => false,
                'required' => true,
            ]);
        }

        // If we're creating a new user, or updating a user's general profile,
        // show the prompt for whether they want their IP to be displayed publicly.
        if ((!$options['update'] || $options['update'] === 'profile') && $options['type'] === 'user') {
            $builder->add('display', ChoiceType::class, [
                'label' => "Do you want your CGHMN IP allocations to be displayed publicly?",
                'choices' => [
                    'Yes' => true,
                    'No' => false,
                ],
                'expanded' => true,
                'multiple' => false,
                'required' => true,
            ]);
        }

        // If we're creating a new user, or updating a user's general profile,
        // show their contact method & info.
        if (!$options['update'] || $options['update'] === 'profile') {
            $builder->add('contactMethod', ChoiceType::class, [
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
                'label_attr' => ['class' => 'required-opt'],
            ]);
            $builder->add('contactDetails', TextareaType::class, [
                'required' => false,
                'label' => $options['detailsLabel'],
            ]);
        }
        
        // Make them agree to TOS
        // show their contact method & info.
        if (!$options['update']) {
            $builder->add('TOS', CheckboxType::class, [
                'label' => 'I have read and agree to the CGHMN Terms of Service (found by clicking on this text).',
                'label_attr' => ['class' => 'required-opt'],
                'mapped' => false,
                'required' => true,
                'constraints' => [
                    new NotBlank(
                        message: 'You must agree to the Terms of Service.',
                    ),
                ],
            ]);
        }

        // If we're creating a new admin
        // show the prompt for super admin.
        if (!$options['update'] && $options['type'] === 'admin') {
            $builder->add('super', CheckboxType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Should this admin be able to create new admins? (!!DANGEROUS!!)'
            ]);
        }
        $builder->add('submit', SubmitType::class, ['label' => 'Submit']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'type' => 'user',
            'update' => null,
            'methodLabel' => 'What is your preferred contact method? (If other, please specify below.)',
            'detailsLabel' => 'How can we reach you?',
        ]);
        $resolver->setAllowedTypes('type', 'string');
    }
}
