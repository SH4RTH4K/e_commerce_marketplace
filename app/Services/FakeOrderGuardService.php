<?php

namespace App\Services;

use App\Models\BlockedDevice;
use App\Models\BlockedIp;
use App\Models\BlockedPhone;
use App\Models\BdCourierCheck;
use App\Models\Order;
use App\Support\BdPhoneValidator;
use Illuminate\Http\Request;

/**
 * FakeOrderGuardService — centralises the 8-step fraud-prevention logic.
 *
 * Previously this logic was duplicated across CheckoutController,
 * QuickOrderController, and LandingPageController. Any change here now
 * propagates to all three entry-points automatically.
 */
class FakeOrderGuardService
{
    /**
     * Run all enabled fraud-guard checks and return the first blocking error,
     * or null when the order is allowed through.
     *
     * @param  Request  $request
     * @param  string   $phone    Already-validated customer phone number
     * @return array{field: string, message: string}|null
     */
    public function check(Request $request, string $phone): ?array
    {
        $fogEnabled = (string) setting('fog_enabled', '1') === '1';
        if (! $fogEnabled) {
            return null;
        }

        $clientIp   = $request->ip();
        $deviceHash = hash('sha256', $request->userAgent() ?? '');

        // 1. IP Block
        if ((string) setting('fog_ip_block_enabled', '1') === '1') {
            if (BlockedIp::where('ip_address', $clientIp)->exists()) {
                return [
                    'field'   => 'cart',
                    'message' => 'আপনার ডিভাইস/আইপি সাময়িকভাবে ব্লক করা হয়েছে। সন্দেহজনক কার্যক্রমের কারণে অর্ডার দেওয়া যাচ্ছে না।',
                ];
            }
        }

        // 2. Device Fingerprint Block
        if ((string) setting('fog_device_block_enabled', '1') === '1') {
            if (BlockedDevice::where('device_hash', $deviceHash)->exists()) {
                return [
                    'field'   => 'cart',
                    'message' => 'আপনার ডিভাইস ব্লক করা হয়েছে। অর্ডার দেওয়া সম্ভব নয়।',
                ];
            }
        }

        // 3. Phone Number Block
        if ((string) setting('fog_phone_block_enabled', '1') === '1') {
            $phoneNorm = BdPhoneValidator::normalize($phone);
            if ($phone && (
                BlockedPhone::where('phone', $phone)->exists() ||
                BlockedPhone::where('phone', $phoneNorm)->exists()
            )) {
                return [
                    'field'   => 'customer_phone',
                    'message' => 'এই ফোন নম্বর থেকে অর্ডার দেওয়া ব্লক করা হয়েছে।',
                ];
            }
        }

        // 4. Fake / Invalid BD Number Block
        if ((string) setting('fog_fake_number_block_enabled', '1') === '1') {
            if ($phone) {
                if (! BdPhoneValidator::isValid($phone)) {
                    return [
                        'field'   => 'customer_phone',
                        'message' => 'অনুগ্রহ করে একটি সঠিক বাংলাদেশী ফোন নম্বর দিন (01X-XXXXXXXX)।',
                    ];
                }
                if (BdPhoneValidator::isFake($phone)) {
                    return [
                        'field'   => 'customer_phone',
                        'message' => 'এই ফোন নম্বরটি বৈধ মনে হচ্ছে না। সঠিক নম্বর দিয়ে আবার চেষ্টা করুন।',
                    ];
                }
            }
        }

        // 5. Max Total Orders Per Phone
        $maxOrdersPerPhone = (int) setting('fog_max_orders_per_phone', 0);
        if ($maxOrdersPerPhone > 0 && $phone) {
            $phoneNorm    = BdPhoneValidator::normalize($phone);
            $totalByPhone = Order::where(function ($q) use ($phone, $phoneNorm) {
                $q->where('customer_phone', $phone)->orWhere('customer_phone', $phoneNorm);
            })->count();
            if ($totalByPhone >= $maxOrdersPerPhone) {
                return [
                    'field'   => 'customer_phone',
                    'message' => "এই নম্বর থেকে সর্বোচ্চ {$maxOrdersPerPhone}টি অর্ডার করা যাবে।",
                ];
            }
        }

        // 6. IP Cooldown Timer
        $ipCooldown = (int) setting('fog_ip_cooldown_minutes', setting('fraud_order_time_limit_minutes', 0));
        if ($ipCooldown > 0) {
            $recentByIp = Order::where('ip_address', $clientIp)
                ->where('created_at', '>=', now()->subMinutes($ipCooldown))
                ->exists();
            if ($recentByIp) {
                return [
                    'field'   => 'cart',
                    'message' => "আপনি ইতিমধ্যে একটি অর্ডার দিয়েছেন। পরবর্তী অর্ডারের জন্য {$ipCooldown} মিনিট অপেক্ষা করুন।",
                ];
            }
        }

        // 7. Phone Cooldown Timer
        $phoneCooldown = (int) setting('fog_phone_cooldown_minutes', 0);
        if ($phoneCooldown > 0 && $phone) {
            $recentByPhone = Order::where('customer_phone', $phone)
                ->where('created_at', '>=', now()->subMinutes($phoneCooldown))
                ->exists();
            if ($recentByPhone) {
                return [
                    'field'   => 'customer_phone',
                    'message' => "এই নম্বর থেকে সম্প্রতি অর্ডার দেওয়া হয়েছে। {$phoneCooldown} মিনিট পর আবার চেষ্টা করুন।",
                ];
            }
        }

        // 8. BD Courier Fraud Check (auto check at checkout)
        if ((string) setting('fog_bdcourier_enabled', '0') === '1' &&
            (string) setting('fog_bdcourier_auto_check_checkout', '0') === '1') {
            if ($phone) {
                $courier = new BdCourierService();
                $result  = $courier->check($phone);
                if ($result) {
                    BdCourierCheck::record(
                        $phone,
                        $result,
                        'checkout',
                        customerName: $request->string('customer_name')->toString()
                    );
                }
                // Fail-open: if API is unreachable, allow order
                if ($result && $courier->shouldBlock($result)) {
                    return [
                        'field'   => 'customer_phone',
                        'message' => $courier->blockMessage($result),
                    ];
                }
            }
        }

        return null;
    }
}
