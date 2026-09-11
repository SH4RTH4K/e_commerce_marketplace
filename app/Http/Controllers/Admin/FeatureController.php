<?php

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use App\Http\Controllers\Controller;
use App\Models\Feature;
use Illuminate\Http\Request;

class FeatureController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Features/Index', [
            'features' => Feature::orderBy('position')->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Features/Form', ['feature' => new Feature(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data = $this->sanitizeFeatureData($data);
        $data['is_active'] = $request->boolean('is_active');
        Feature::create($data);

        return redirect()->route('admin.features.index')->with('status', 'Feature created.');
    }

    public function edit(Feature $feature)
    {
        return Inertia::render('Admin/Features/Form', compact('feature'));
    }

    public function update(Request $request, Feature $feature)
    {
        $data = $this->validateData($request);
        $data = $this->sanitizeFeatureData($data);
        $data['is_active'] = $request->boolean('is_active');
        $feature->update($data);

        return redirect()->route('admin.features.index')->with('status', 'Feature updated.');
    }

    public function destroy(Feature $feature)
    {
        $feature->delete();

        return redirect()->route('admin.features.index')->with('status', 'Feature deleted.');
    }

    public function bulk(Request $request)
    {
        $data = $request->validate([
            'ids'         => ['required', 'array', 'min:1'],
            'ids.*'       => ['integer', 'exists:features,id'],
            'bulk_action' => ['required', 'in:activate,deactivate,delete'],
        ]);

        $ids = $data['ids'];
        $count = count($ids);

        if ($data['bulk_action'] === 'activate') {
            Feature::whereIn('id', $ids)->update(['is_active' => true]);

            return back()->with('status', "{$count} feature(s) set to Active.");
        }

        if ($data['bulk_action'] === 'deactivate') {
            Feature::whereIn('id', $ids)->update(['is_active' => false]);

            return back()->with('status', "{$count} feature(s) set to Hidden.");
        }

        Feature::whereIn('id', $ids)->delete();

        return back()->with('status', "{$count} feature(s) deleted.");
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'title'    => ['required', 'string', 'max:120'],
            'subtitle' => ['nullable', 'string', 'max:160'],
            'icon'     => ['nullable', 'string', 'max:1000'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function sanitizeFeatureData(array $data): array
    {
        $data['title'] = trim((string) ($data['title'] ?? ''));
        $data['position'] = (isset($data['position']) && trim((string) $data['position']) !== '')
            ? max(0, (int) $data['position'])
            : 0;

        foreach (['subtitle', 'icon'] as $field) {
            if (isset($data[$field])) {
                $trimmed = trim((string) $data[$field]);
                $data[$field] = $trimmed !== '' ? $trimmed : null;
            }
        }

        return $data;
    }
}
