<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

class LanguageController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'in:en,ar,ur'],
        ]);

        session(['locale' => $validated['locale']]);

        return back()->with('status', __('pos.language_updated'));
    }
}
