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
            ['module' => $module],
            ['status' => 'inactive']
        );

        $newStatus = $storeModule->status === 'active' ? 'inactive' : 'active';

        $storeModule->update([
            'status' => $newStatus,
            'last_updated_at' => now(),
        ]);

        $label = StoreModule::MODULES[$module];
        $message = $newStatus === 'active'
            ? "{$label} activated for {$store->name}."
            : "{$label} disabled for {$store->name}.";

        $store->organisation->notifications()->create([
            'store_id' => $store->id,
            'type' => 'module_activated',
            'title' => $newStatus === 'active' ? 'Module activated' : 'Module disabled',
            'message' => $message,
        ]);

        return back()->with('success', $message);
    }
}
