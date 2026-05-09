<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class PeerMigrateFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('peer', NumberType::class, [
                'label' => 'Peer ID',
                'required' => true,
                'constraints' => [
                    new NotBlank(
                        message: 'Please enter the ID of the peer to assign.',
                    ),
                ],
                'label_attr' => ['class' => 'required-opt'],
            ])
            ->add('username', TextType::class, [
                'label' => 'Username',
                'required' => true,
                'constraints' => [
                    new NotBlank(
                        message: 'Please enter the username of the user to ' .
                        'assign the peer to.',
                    ),
                ],
                'label_attr' => ['class' => 'required-opt'],
            ])
            ->add('send', SubmitType::class, ['label' => 'Migrate'])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
