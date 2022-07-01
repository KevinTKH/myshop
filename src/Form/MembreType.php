<?php

namespace App\Form;

use App\Entity\Membre;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;

class MembreType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('pseudo')
            ->add('password', PasswordType::class, [
                'label' => 'Choisissez un mot de passe'

            ])
            ->add('nom')
            ->add('prenom')
            ->add('email')
            ->add('civilite', ChoiceType::class, [
                "choices" => [
                    "femme" => "f",
                    "homme" => "m"
                ],
                "placeholder" => "--choisir--"
            ])
            ->add('roles', ChoiceType::class, [
                "mapped" => false,
                "choices" => [
                    "membre" => "ROLE_MEMBRE",
                    "admin" => "ROLE_ADMIN"
                ],
                "placeholder" => "--choisir--"
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Membre::class,
        ]);
    }
}
