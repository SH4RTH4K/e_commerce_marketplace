<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminPopupController extends Controller
{
    public function index(): Response
    {
        $image = setting('popup_image', '');
        $imageUrl = '';
        if ($image) {
            $imageUrl = str_starts_with($image, 'http') ? $image : asset($image);
        }

        return Inertia::render('Admin/Marketing/PopupNotification', [
            'settings' => [
                'popup_enabled'       => setting('popup_enabled', '0') === '1',
                'popup_title'         => setting('popup_title', 'Special Offer!'),
                'popup_text'          => setting('popup_text', 'Get exclusive discount on your favorite products today.'),
                'popup_image'         => $imageUrl,
                'popup_image_raw'     => $image,
                'popup_link'          => setting('popup_link', ''),
                'popup_btn_label'     => setting('popup_btn_label', 'Shop Now'),
                'popup_delay_seconds' => (int) setting('popup_delay_seconds', '3'),
                'popup_frequency'     => setting('popup_frequency', 'once_per_session'),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'popup_enabled'       => ['nullable'],
            'popup_title'         => ['nullable', 'string', 'max:255'],
            'popup_text'          => ['nullable', 'string', 'max:5000'],
            'popup_link'          => ['nullable', 'string', 'max:500'],
            'popup_btn_label'     => ['nullable', 'string', 'max:100'],
            'popup_delay_seconds' => ['required', 'integer', 'min:0', 'max:3600'],
            'popup_frequency'     => ['required', 'string', 'in:always,once_per_session,every_24h'],
            'popup_image_file'    => ['nullable', 'image', 'max:4096'],
            'remove_image'        => ['nullable', 'boolean'],
        ]);

        Setting::put('popup_enabled', $request->boolean('popup_enabled') ? '1' : '0');
        Setting::put('popup_title', (string) ($validated['popup_title'] ?? ''));
        Setting::put('popup_text', (string) ($validated['popup_text'] ?? ''));
        Setting::put('popup_link', (string) ($validated['popup_link'] ?? ''));
        Setting::put('popup_btn_label', (string) ($validated['popup_btn_label'] ?? 'Shop Now'));
        Setting::put('popup_delay_seconds', (string) ($validated['popup_delay_seconds'] ?? '3'));
        Setting::put('popup_frequency', (string) ($validated['popup_frequency'] ?? 'once_per_session'));

        if ($request->boolean('remove_image')) {
            $oldImage = setting('popup_image', '');
            if ($oldImage && !str_starts_with($oldImage, 'http') && file_exists(public_path($oldImage))) {
                @unlink(public_path($oldImage));
            }
            Setting::put('popup_image', '');
        } elseif ($request->hasFile('popup_image_file')) {
            $file = $request->file('popup_image_file');
            $filename = 'popup_' . Str::random(12) . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads/popups'), $filename);
            Setting::put('popup_image', 'uploads/popups/' . $filename);
        }

        return back()->with('status', 'Popup Notification settings updated successfully!');
    }
}
