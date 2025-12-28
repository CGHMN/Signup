<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Validator\Constraints\NotBlank;
class RequestType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'disabled' => true,
                'label' => false,
            ])
            ->add('email', TextType::class, [
                'disabled' => true,
                'label' => false,
            ])
            ->add('plan', TextType::class, [
                'disabled' => true,
                'label' => false,
            ])
            ->add('needsHosting', CheckboxType::class, [
                'disabled' => true,
                'label' => false,
            ])
            ->add('hasExperience', CheckboxType::class, [
                'disabled' => true,
                'label' => false,
            ])
            ->add('contactMethod', TextType::class, [
                'disabled' => true,
                'label' => false,
            ])
            ->add('contactDetails', TextType::class, [
                'disabled' => true,
                'label' => false,
            ])
            ->add('decision', ChoiceType::class, [
                'label' => false,
                'mapped' => false,
                'choices' => [
                    'Do Nothing' => 0,
                    'Approve' => 1,
                    'Reject' => 2,
                ],
                'constraints' => [
                    new NotBlank(),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}