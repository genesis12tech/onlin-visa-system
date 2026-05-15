<?php

use App\Http\Controllers\DocumentDownloadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/documents/{version}/download', [DocumentDownloadController::class, 'download'])
    ->name('documents.download')
    ->middleware(['auth', 'signed']);
