<?php

namespace Database\Seeders;

use App\Models\ContactFormField;
use App\Models\ContactMessage;
use App\Models\ContactMessageValue;
use Illuminate\Database\Seeder;

class ContactMessageSeeder extends Seeder
{
    public function run(): void
    {
        $fields = ContactFormField::query()->orderBy('position')->orderBy('id')->get();
        if ($fields->isEmpty()) {
            $this->command?->warn('ContactMessageSeeder skipped: no contact form fields. Seed ContactFormFieldSeeder first.');

            return;
        }

        $samples = [
            [
                'status'   => ContactMessage::STATUS_NEW,
                'name'     => 'Rahim Uddin',
                'email'    => 'rahim.uddin@example.com',
                'phone'    => '01711-111111',
                'subject'  => 'Order delivery question',
                'message'  => 'Assalamu alaikum. I placed an order yesterday and wanted to confirm the delivery timeline for outside Dhaka.',
                'days_ago' => 0,
                'hours'    => 2,
            ],
            [
                'status'   => ContactMessage::STATUS_NEW,
                'name'     => 'Ayesha Akter',
                'email'    => 'ayesha.akter@example.com',
                'phone'    => '01822-222222',
                'subject'  => 'Product availability',
                'message'  => 'Hi, is the featured product available in size M? Also do you offer Cash on Delivery in Chattogram?',
                'days_ago' => 0,
                'hours'    => 5,
            ],
            [
                'status'   => ContactMessage::STATUS_NEW,
                'name'     => 'Karim Mia',
                'email'    => 'karim.mia@example.com',
                'phone'    => '01933-333333',
                'subject'  => 'Payment verification',
                'message'  => 'I paid via bKash but the order still shows pending. Txn ID: BKASHDEMO123. Please check.',
                'days_ago' => 1,
                'hours'    => 3,
            ],
            [
                'status'   => ContactMessage::STATUS_READ,
                'name'     => 'Nadia Islam',
                'email'    => 'nadia.islam@example.com',
                'phone'    => '01644-444444',
                'subject'  => 'Return / exchange',
                'message'  => 'The item I received is the wrong color. Can I exchange it within 7 days?',
                'days_ago' => 2,
                'hours'    => 4,
            ],
            [
                'status'   => ContactMessage::STATUS_READ,
                'name'     => 'Sumon Hossain',
                'email'    => 'sumon.hossain@example.com',
                'phone'    => '01555-555555',
                'subject'  => 'Wholesale inquiry',
                'message'  => 'We run a small shop and would like bulk pricing for 50+ units. Please share your wholesale process.',
                'days_ago' => 3,
                'hours'    => 6,
            ],
            [
                'status'   => ContactMessage::STATUS_ARCHIVED,
                'name'     => 'Tanvir Ahmed',
                'email'    => 'tanvir.ahmed@example.com',
                'phone'    => '01366-666666',
                'subject'  => 'Thanks for the help',
                'message'  => "Support resolved my shipping issue quickly. Thank you \u{2014} keeping this for the record.",
                'days_ago' => 7,
                'hours'    => 1,
            ],
            [
                'status'   => ContactMessage::STATUS_ARCHIVED,
                'name'     => 'Farzana Begum',
                'email'    => 'farzana.begum@example.com',
                'phone'    => '01777-777777',
                'subject'  => 'Website feedback',
                'message'  => 'Checkout was smooth on mobile. One suggestion: show estimated delivery dates more clearly.',
                'days_ago' => 10,
                'hours'    => 8,
            ],
        ];

        foreach ($samples as $sample) {
            $createdAt = now()->subDays($sample['days_ago'])->subHours($sample['hours']);
            $readAt = in_array($sample['status'], [ContactMessage::STATUS_READ, ContactMessage::STATUS_ARCHIVED], true)
                ? $createdAt->copy()->addHours(2)
                : null;

            $message = ContactMessage::create([
                'status'     => $sample['status'],
                'name'       => $sample['name'],
                'email'      => $sample['email'],
                'phone'      => $sample['phone'],
                'subject'    => $sample['subject'],
                'ip_address' => '127.0.0.1',
                'user_agent' => 'SeedBot/1.0',
                'read_at'    => $readAt,
                'created_at' => $createdAt,
                'updated_at' => $readAt ?? $createdAt,
            ]);

            $pos = 0;
            foreach ($fields as $field) {
                if ($field->type === 'file') {
                    continue;
                }

                $value = match ($field->key) {
                    'name'    => $sample['name'],
                    'email'   => $sample['email'],
                    'phone'   => $sample['phone'],
                    'subject' => $sample['subject'],
                    'message' => $sample['message'],
                    default   => $field->type === 'checkbox' ? '1' : null,
                };

                if ($value === null || $value === '') {
                    continue;
                }

                ContactMessageValue::create([
                    'contact_message_id' => $message->id,
                    'field_key'          => $field->key,
                    'field_label'        => $field->label,
                    'field_type'         => $field->type,
                    'value'              => $value,
                    'file_path'          => null,
                    'file_name'          => null,
                    'position'           => $pos++,
                    'created_at'         => $createdAt,
                    'updated_at'         => $createdAt,
                ]);
            }
        }
    }
}
