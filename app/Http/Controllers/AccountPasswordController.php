<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResetAccountPasswordRequest;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class AccountPasswordController extends Controller
{
    /**
     * Reset the password of an account and sign the account out of its other sessions.
     */
    public function update(ResetAccountPasswordRequest $request, User $account): RedirectResponse
    {
        $account->forceFill([
            'password' => $request->validated('password'),
            'remember_token' => Str::random(60),
        ])->save();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $account->id)
                ->when($account->is($request->user()), fn (Builder $query) => $query
                    ->where('id', '!=', $request->session()->getId()))
                ->delete();
        }

        Inertia::flash('success', "Password of {$account->fullName()} was reset.");

        return back();
    }
}
