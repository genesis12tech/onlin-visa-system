<?php

use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\ExportDownloadController;
use App\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/documents/{version}/download', [DocumentDownloadController::class, 'download'])
    ->name('documents.download')
    ->middleware(['auth', 'signed']);

Route::get('/exports/{ulid}/download', [ExportDownloadController::class, 'download'])
    ->name('exports.download')
    ->middleware('auth');

Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle'])
    ->name('webhooks.stripe');
