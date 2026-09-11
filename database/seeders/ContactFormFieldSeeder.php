<?php

namespace Database\Seeders;

use App\Models\ContactFormField;
use Illuminate\Database\Seeder;

class ContactFormFieldSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            [
                'key'         => 'name',
                'label'       => 'Full name',
                'type'        => 'text',
                'placeholder' => 'Your name',
                'is_required' => true,
                'is_system'   => true,
                'is_active'   => true,
                'position'    => 0,
            ],
            [
                'key'         => 'email',
                'label'       => 'Email',
                'type'        => 'email',
                'placeholder' => 'you@example.com',
                'is_required' => true,
                'is_system'   => true,
                'is_active'   => true,
                'position'    => 1,
            ],
            [
                'key'         => 'phone',
                'label'       => 'Phone',
                'type'        => 'tel',
                'placeholder' => '01XXX-XXXXXX',
                'is_required' => false,
                'is_system'   => true,
                'is_active'   => true,
                'position'    => 2,
            ],
            [
                'key'         => 'subject',
                'label'       => 'Subject',
                'type'        => 'text',
                'placeholder' => 'How can we help?',
                'is_required' => false,
                'is_system'   => true,
                'is_active'   => true,
                'position'    => 3,
            ],
            [
                'key'         => 'message',
                'label'       => 'Message',
                'type'        => 'textarea',
                'placeholder' => "Write your message\u{2026}",
                'is_required' => true,
                'is_system'   => true,
                'is_active'   => true,
                'position'    => 4,
            ],
        ];

        foreach ($defaults as $row) {
            ContactFormField::updateOrCreate(
                ['key' => $row['key']],
                $row
            );
        }
    }
}
