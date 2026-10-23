<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ProfileController extends Controller
{
    /**
     * Update the name, surname, phone and email address of the signed in user.
     */
    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        Inertia::flash('success', 'Your profile was updated.');

        return back();
    }
}
