<?php

namespace App\Services;

use App\Models\Coupon;

/**
 * Session-backed applied coupon at checkout. Only the coupon code is stored;
 * validation and discount math always run against the live database.
 */
class CouponService
{
    private const KEY = 'checkout_coupon';

    public function code(): ?string
    {
        $code = session(self::KEY);

        return $code ? (string) $code : null;
    }

    public function coupon(): ?Coupon
    {
        $code = $this->code();

        return $code ? Coupon::where('code', $code)->first() : null;
    }

    /** @return array{ok: bool, message: string, coupon?: Coupon} */
    public function apply(string $code, float $subtotal): array
    {
        $code = strtoupper(trim($code));

        if ($code === '') {
            return ['ok' => false, 'message' => 'Please enter a coupon code.'];
        }

        $coupon = Coupon::where('code', $code)->first();

        if (! $coupon) {
            return ['ok' => false, 'message' => 'Coupon code not found.'];
        }

        $error = $coupon->validateForSubtotal($subtotal);
        if ($error) {
            return ['ok' => false, 'message' => $error];
        }

        session([self::KEY => $coupon->code]);

        return [
            'ok'      => true,
            'message' => "Coupon \"{$coupon->code}\" applied.",
            'coupon'  => $coupon,
        ];
    }

    public function remove(): void
    {
        session()->forget(self::KEY);
    }

    public function discount(float $subtotal): float
    {
        $coupon = $this->coupon();

        return $coupon ? $coupon->calculateDiscount($subtotal) : 0.0;
    }

    public function summary(float $subtotal): array
    {
        $coupon   = $this->coupon();
        $discount = $this->discount($subtotal);

        return [
            'code'     => $coupon?->code,
            'discount' => $discount,
            'coupon'   => $coupon,
        ];
    }
}
