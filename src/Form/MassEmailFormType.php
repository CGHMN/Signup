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

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class MassEmailFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('subject', TextType::class, [
                'label' => 'Email Subject',
                'required' => true,
                'constraints' => [
                    new NotBlank(
                        message: 'Please choose enter a subject for your email ya dingus.',
                    ),
                ],
                'label_attr' => ['class' => 'required-opt'],
            ])
            ->add('body', TextareaType::class, [
                'label' => 'Email Body',
                'required' => true,
                'constraints' => [
                    new NotBlank(
                        message: 'You really wanna send an empty email?',
                    ),
                ],
                'label_attr' => ['class' => 'required-opt'],
            ])
            ->add('send', SubmitType::class, ['label' => 'Send'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
