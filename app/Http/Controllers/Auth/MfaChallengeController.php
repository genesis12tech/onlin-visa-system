<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MfaChallengeController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('mfa_user_id')) {
            return redirect()->route('login');
        }

        return view('pages.auth.mfa-challenge');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'size:6']]);

        $userId = $request->session()->get('mfa_user_id');

        if (! $userId) {
            return redirect()->route('login');
        }

        $storedOtp = cache()->get("mfa.otp.{$userId}");

        if (! $storedOtp || $storedOtp !== $request->input('code')) {
            return back()->withErrors(['code' => 'The code is invalid or has expired.']);
        }

        cache()->forget("mfa.otp.{$userId}");
        $request->session()->forget('mfa_user_id');

        $user = User::findOrFail($userId);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }
}
