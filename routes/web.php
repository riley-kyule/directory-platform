<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProviderOnboardingController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::prefix('onboarding')->name('onboarding.')->group(function () {
        Route::get('/', [ProviderOnboardingController::class, 'index'])->name('index');
        Route::post('/agency', [ProviderOnboardingController::class, 'storeAgency'])->name('agency.store');
        Route::get('/profiles/create', [ProviderOnboardingController::class, 'createProfile'])->name('profiles.create');
        Route::post('/profiles', [ProviderOnboardingController::class, 'storeProfile'])->name('profiles.store');
    });
});

require __DIR__.'/auth.php';
