<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\IsTrue;

class AccountDeleteFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('confirm', CheckboxType::class, [
                'label' => 'I understand deleting my account is permanent and cannot be undone.',
                'required' => true,
                'constraints' => [
                    new IsTrue(
                        message: 'You must check this box to indicate that you understand the terms above.'
                    ),
                ],
            ])
            ->add('password', PasswordType::class, [
                'label' => 'Please enter your password',
                'required' => true,
                'constraints' => [
                    new NotBlank(
                        message: 'You must enter your password.',
                    ),
                ],
                'label_attr' => ['class' => 'required-opt'],
            ])
            ->add('submit', SubmitType::class, ['label' => 'Delete my account.'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
