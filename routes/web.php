<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\MfaChallengeController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\ExportDownloadController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Middleware\EnsureProfileComplete;
use App\Livewire\Profile\SetupWizard;
use Illuminate\Support\Facades\Route;

// Guest-only routes
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:register');

    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.update');

    Route::get('/mfa-challenge', [MfaChallengeController::class, 'create'])->name('mfa.challenge');
    Route::post('/mfa-challenge', [MfaChallengeController::class, 'store'])->middleware('throttle:mfa-otp');
});

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // Email verification
    Route::get('/verify-email', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    // Profile setup — requires verified email but not complete profile (that would be circular)
    Route::get('/profile/setup', SetupWizard::class)
        ->middleware('verified')
        ->name('profile.setup');

    // Protected applicant area — requires verified email AND complete profile
    Route::middleware(['verified', EnsureProfileComplete::class])->group(function () {
        Route::get('/dashboard', DashboardController::class)->name('dashboard');
    });
});

// Root redirect
Route::get('/', fn () => redirect()->route('dashboard'));

// Document & export downloads
Route::get('/documents/{version}/download', [DocumentDownloadController::class, 'download'])
    ->name('documents.download')
    ->middleware(['auth', 'signed', 'throttle:document-download']);

Route::get('/exports/{ulid}/download', [ExportDownloadController::class, 'download'])
    ->name('exports.download')
    ->middleware(['auth', 'throttle:document-download']);

// Stripe webhook — no auth
Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])
    ->name('webhooks.stripe')
    ->middleware('throttle:webhook');
