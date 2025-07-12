<?php

namespace App\Form\Type;

use App\Entity\ContactRequest;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ContactRequestType extends AbstractType {
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $form = $builder
            ->add('name', TextType::class, [
                'label' => false,
                'attr' => ['placeholder' => 'Dein Name'],
            ])
            ->add('email', EmailType::class, [
                'label' => false,
                'attr' => ['placeholder' => 'Deine E-Mail-Adresse'],
            ])
            ->add('message', TextareaType::class, [
                'label' => false,
                'help' => 'Bitte beachte, dass Nachrichten mit mehr als einer URL aus Sicherheitsgründen ausgefiltert werden. Schreibe mir in dem Fall am besten eine E-Mail.',
                'attr' => [
                    'rows' => 5,
                    'placeholder' => 'Deine Nachricht',
                ],
            ])
        ;

        $form->add('submit', SubmitType::class, [
            'label' => 'Nachricht senden'
        ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ContactRequest::class,
        ]);
    }
}
