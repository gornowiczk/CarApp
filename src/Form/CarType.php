<?php

namespace App\Form;

use App\Entity\Car;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class CarType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('brand', TextType::class, [
                'label' => 'Marka',
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('model', TextType::class, [
                'label' => 'Model',
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('year', IntegerType::class, [
                'label' => 'Rok',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Range(min: 1980, max: (int)date('Y')+1),
                ],
            ])
            ->add('registrationNumber', TextType::class, [
                'label' => 'Numer rejestracyjny',
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('pricePerDay', NumberType::class, [
                'label' => 'Cena za dobę (PLN)',
                'html5' => true,
                'scale' => 2,
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Positive(),
                ],
            ])
            ->add('location', TextType::class, [
                'label' => 'Lokalizacja',
                'required' => false,
            ])
            ->add('isAvailable', CheckboxType::class, [
                'label' => 'Dostępny',
                'required' => false,
            ])
            ->add('pausedUntil', DateTimeType::class, [
                'label' => 'Wstrzymaj ogłoszenie do (opcjonalnie)',
                'required' => false,
                'widget' => 'single_text',
                'html5'   => true,
            ])
            ->add('mainImage', FileType::class, [
                'label'       => 'Zdjęcie główne (opcjonalne)',
                'mapped'      => false,
                'required'    => false,
                'constraints' => [
                    new Assert\File(maxSize: '5M', mimeTypes: ['image/jpeg','image/png','image/webp']),
                ],
            ])
            ->add('gallery', FileType::class, [
                'label'       => 'Galeria (opcjonalna, wiele plików)',
                'mapped'      => false,
                'required'    => false,
                'multiple'    => true,
                'constraints' => [
                    new Assert\All([
                        new Assert\File(maxSize: '5M', mimeTypes: ['image/jpeg','image/png','image/webp']),
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Car::class]);
    }
}
