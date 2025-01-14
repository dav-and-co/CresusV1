<?php

namespace App\Form;

use App\Entity\Formulaire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

class FormulaireType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom_demandeur', TextType::class, [
                'label' => 'Votre nom',
                'required' => true,
            ])
            ->add('prenom_demandeur', TextType::class, [
                'label' => 'Votre prénom',
                'required' => true,
            ])
            ->add('mail_demandeur', null, [
                'label' => 'Votre email',
                'required' => true,
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez entrer une adresse email.',
                    ]),
                    new Email([
                        'message' => 'L\'adresse email "{{ value }}" n\'est pas valide.',
                    ]),
                ],
                'invalid_message' => 'Adresse email invalide, veuillez vérifier.',
            ])
            ->add('telephone_demandeur', TelType::class, [
                'attr' => [
                    'placeholder' => '00 00 00 00 00',
                ],
                'label' => 'Votre téléphone',
                'required' => true,
            ])
            ->add('besoin_demandeur', ChoiceType::class, [
        "choices" => [
            'Accompagnement budgétaire' => 'PCB',
            'Demande de microcrédit' => 'microcrédit',
            'Surendettement' => 'surendettement'
        ],
                'label' => 'Votre besoin',
                'required' => true,
                'multiple' => false,
                'expanded' => true,
            ])
            ->add('description_besoin', TextareaType::class, [
                'label' => 'Message',
                'required' => false,
                'attr' => [
                    'placeholder' => 'entrez votre message',
                    'rows'=> '6',
                ],
            ])
            ->add('isGdpr', CheckboxType::class, [
                'label' => 'Cochez pour accepter notre politique de Confidentialité',
                'mapped' => true,
            ])

            ->add('submit', SubmitType::class, [
                'label' => 'Envoyer la demande',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Formulaire::class,
        ]);
    }
}
