<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CompanyProfileController extends Controller
{
    public function edit(): View
    {
        $profile = CompanyProfile::query()->firstOrCreate(
            ['id' => 1],
            ['company_name' => config('app.name', 'POS Pro')]
        );

        return view('settings.company', compact('profile'));
    }

    public function update(Request $request): RedirectResponse
    {
        $profile = CompanyProfile::query()->firstOrCreate(
            ['id' => 1],
            ['company_name' => config('app.name', 'POS Pro')]
        );

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        if ($request->hasFile('logo')) {
            if ($profile->logo_path) {
                Storage::disk('public')->delete($profile->logo_path);
            }

            $validated['logo_path'] = $request->file('logo')->store('company', 'public');
        }

        $profile->update($validated);

        return back()->with('status', __('pos.company_profile_updated'));
    }
}
