<?php

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
