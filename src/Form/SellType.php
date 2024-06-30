<?php

namespace App\Form;

use App\Entity\Category;
use App\Entity\Products;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SellType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('price', MoneyType::class, [
                "label"=>"Prix Unitaire -  ",
                'attr'=> ['class' => 'form-control mb-3'],
                'label_attr'=>['class'=>'mb-1'],
                'error_bubbling'=> true,
                'invalid_message' => 'Veuillez entrer un nombre avec virgule ou sans'
            ])
            ->add('quantity', NumberType::class, [
                "label"=>"Quantité",
                'attr'=> ['class' => 'form-control mb-3'],
                'label_attr'=>['class'=>'mb-1'],
                'error_bubbling'=> true,
                'invalid_message' => 'Veuillez entrer un nombre avec virgule ou sans'
            ])

        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Products::class,
        ]);
    }

}