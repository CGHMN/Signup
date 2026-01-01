<?php

namespace App\Form;

use App\Entity\User;
use App\Entity\WireguardPeer;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\NotBlank;

class WireguardPeerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
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
            ->add('submit', SubmitType::class, [
                'label' => 'Create',
            ]);
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => WireguardPeer::class,
        ]);
    }
}
