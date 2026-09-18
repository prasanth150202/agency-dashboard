<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\StoreModule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class StoreModuleController extends Controller
{
    public function toggle(Store $store, string $module): RedirectResponse
    {
        Gate::authorize('update', $store);

        abort_unless(array_key_exists($module, StoreModule::MODULES), 404);

        $storeModule = $store->modules()->firstOrCreate(
            ['module_key' => $module],
            ['module_name' => StoreModule::MODULES[$module], 'is_active' => false]
        );

        $newActive = ! $storeModule->is_active;

        $storeModule->update([
            'is_active' => $newActive,
            'last_updated_at' => now(),
        ]);

        $label = StoreModule::MODULES[$module];
        $message = $newActive
            ? "{$label} activated for {$store->name}."
            : "{$label} disabled for {$store->name}.";

        $store->organisation->notifications()->create([
            'store_id' => $store->id,
            'type' => 'module_activated',
            'title' => $newActive ? 'Module activated' : 'Module disabled',
            'message' => $message,
        ]);

        return back()->with('success', $message);
    }
}
