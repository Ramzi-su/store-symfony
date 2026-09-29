<?php

namespace App\Form;

use App\Entity\Orders;
use App\Entity\User;
use App\Enum\OrderStatus;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OrdersType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Stored in cents; the admin types dollars (divisor converts both ways).
            ->add('total', MoneyType::class, [
                'currency' => 'USD',
                'divisor' => 100,
            ])
            ->add('status', EnumType::class, [
                'class' => OrderStatus::class,
                'choice_label' => fn (OrderStatus $status) => $status->label(),
            ])
            ->add('created_at', null, [
                'widget' => 'single_text',
            ])
            ->add('user', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'id',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Orders::class,
        ]);
    }
}
