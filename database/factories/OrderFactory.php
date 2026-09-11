<?php

namespace Database\Factories;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'order_number'          => 'ORD-' . strtoupper($this->faker->unique()->bothify('####??')),
            'customer_name'         => $this->faker->name(),
            'customer_phone'        => '01' . $this->faker->numerify('#########'),
            'customer_email'        => $this->faker->optional()->safeEmail(),
            'shipping_address'      => $this->faker->streetAddress(),
            'city'                  => $this->faker->randomElement(['Dhaka', 'Chittagong', 'Sylhet']),
            'shipping_zone'         => 'inside_dhaka',
            'subtotal'              => 1000.00,
            'shipping_charge'       => 60.00,
            'tax'                   => 0.00,
            'total'                 => 1060.00,
            'payment_method'        => 'cod',
            'payment_status'        => 'pending',
            'status'                => 'pending',
            'courier_provider'      => null,
            'courier_tracking_code' => null,
            'courier_consignment_id'=> null,
            'courier_status'        => null,
        ];
    }

    /** State: order that is ready to be shipped (confirmed + payment verified). */
    public function readyToShip(): static
    {
        return $this->state([
            'status'         => 'confirmed',
            'payment_status' => 'verified',
        ]);
    }

    /** State: order already shipped via a courier. */
    public function shipped(string $provider = 'steadfast'): static
    {
        return $this->state([
            'status'                => 'shipped',
            'courier_provider'      => $provider,
            'courier_tracking_code' => 'TRACK-' . strtoupper($this->faker->bothify('####??')),
            'courier_status'        => 'in_review',
        ]);
    }
}
