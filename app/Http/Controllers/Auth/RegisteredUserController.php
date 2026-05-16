<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\Actions\RegisterApplicant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('pages.auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = RegisterApplicant::run(
            $request->validated('name'),
            $request->validated('email'),
            $request->validated('password'),
        );

        Auth::login($user);

        $user->sendEmailVerificationNotification();

        return redirect()->route('verification.notice');
    }
}
