<?php

namespace App\Form;

use App\Entity\Requests;
use App\Form\RequestType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// Creates a form for a list of requests
class RequestCollectionType extends AbstractType {
    public function buildForm(FormBuilderInterface $builder, array $options): void {
        $builder
            ->add('requests', CollectionType::class, [
                'entry_type' => RequestType::class,
                'entry_options' => ['label' => false],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Go!',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void {
        $resolver->setDefaults([
            'data_class' => Requests::class,
        ]);
    }
}