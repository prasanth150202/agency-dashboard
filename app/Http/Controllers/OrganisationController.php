<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use App\Models\OrganisationSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrganisationController extends Controller
{
    public function index(Request $request)
    {
        $organisations = $request->user()
            ->organisations()
            ->withCount('stores')
            ->orderBy('name')
            ->get();

        return view('partners.index', [
            'organisations' => $organisations,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validateWithBag('organisation', [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('organisations', 'name')->where(
                    fn ($query) => $query->whereIn(
                        'id',
                        $user->organisations()->pluck('organisations.id')
                    )
                ),
            ],
            'website' => ['nullable', 'url', 'max:255'],
        ], [
            'name.unique' => 'You already have a partner with this name.',
        ]);

        $organisation = Organisation::create([
            'name' => $validated['name'],
            'website' => $validated['website'] ?? null,
        ]);

        $organisation->users()->attach($user->id, ['role' => 'owner']);

        OrganisationSettings::create([
            'organisation_id' => $organisation->id,
        ]);

        session(['organisation_id' => $organisation->id]);

        return redirect()
            ->route('dashboard')
            ->with('success', 'Partner created successfully.');
    }

    public function switch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'organisation_id' => ['required', 'integer'],
        ]);

        $belongs = $request->user()
            ->organisations()
            ->whereKey($validated['organisation_id'])
            ->exists();

        abort_unless($belongs, 403);

        session(['organisation_id' => (int) $validated['organisation_id']]);

        return redirect()
            ->route('dashboard')
            ->with('success', 'Switched partner.');
    }
}
