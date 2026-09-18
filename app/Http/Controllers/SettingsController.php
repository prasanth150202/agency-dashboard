<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('currentOrganisation');

        $role = $request->user()
            ->organisations()
            ->whereKey($organisation->id)
            ->first()
            ?->pivot
            ?->role;

        return view('settings.index', [
            'organisation' => $organisation,
            'role' => $role,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('currentOrganisation');

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('organisations', 'name')->ignore($organisation->id),
            ],
            'website' => ['nullable', 'url', 'max:255'],
        ]);

        $organisation->update($validated);

        return back()->with('success', 'Partner settings updated.');
    }
}
