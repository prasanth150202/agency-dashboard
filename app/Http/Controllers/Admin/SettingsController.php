<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingsController extends Controller
{
    public function index()
    {
        return view('admin.settings.index', [
            'settings' => PlatformSetting::current(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'minimum_payout_amount' => ['required', 'numeric', 'min:0'],
            'commission_holding_period_days' => ['required', 'integer', 'min:0', 'max:365'],
        ]);

        foreach ($validated as $key => $value) {
            DB::table('app_settings')->updateOrInsert(
                ['setting_key' => $key],
                ['value' => (string) $value, 'updated_by' => auth('admin')->id(), 'updated_at' => now()]
            );
        }

        return back()->with('success', 'Platform settings updated.');
    }
}
