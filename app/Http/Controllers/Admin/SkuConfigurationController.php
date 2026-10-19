<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\SkuConfigurationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SkuConfigurationController extends Controller
{
    public function index(SkuConfigurationService $skus)
    {
        $config = $skus->config();
        return Inertia::render('Admin/SkuConfiguration', [
            'config' => $config, 'categories' => $skus->categories(), 'next_sequence' => $skus->nextSequence($config),
            'totals' => ['products' => Product::count(), 'missing' => Product::whereNull('sku')->orWhereRaw("TRIM(sku) = ''")->count()],
            'history' => DB::table('sku_configuration_runs')->latest('id')->limit(30)->get(['id', 'actor_name', 'action', 'scope', 'changed_count', 'created_at']),
        ]);
    }

    public function update(Request $request, SkuConfigurationService $skus)
    {
        $skus->save($skus->validate($request->all()), $request->user());
        return redirect()->route('admin.sku-configuration.index')->with('status', 'SKU configuration and category prefixes saved. Existing SKUs are unchanged.');
    }

    public function preview(Request $request, SkuConfigurationService $skus)
    {
        $data = $request->validate(['config' => ['required', 'array'], 'scope' => ['required', 'in:all,missing'], 'source' => ['required', 'in:all,manual,dropshipping']]);
        return response()->json($skus->preview($skus->validate($data['config']), $data['scope'], $data['source'], $request->user()));
    }

    public function apply(Request $request, SkuConfigurationService $skus)
    {
        $data = $request->validate(['token' => ['required', 'string', 'size:48'], 'confirm' => ['required', 'accepted']]);
        $count = $skus->apply($data['token'], $request->user());
        return redirect()->route('admin.sku-configuration.index')->with('status', "SKU configuration saved and {$count} existing local SKUs updated.");
    }
}
