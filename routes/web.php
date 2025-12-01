<?php

use App\Http\Controllers\Admin\MigrationController;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('profile.edit');
    Volt::route('settings/password', 'settings.password')->name('user-password.edit');
    Volt::route('settings/appearance', 'settings.appearance')->name('appearance.edit');

    Volt::route('settings/two-factor', 'settings.two-factor')
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                    && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('two-factor.show');
});

// Admin-only migration routes (for free tier deployments without shell access)
Route::middleware(['auth', EnsureUserIsAdmin::class])->prefix('admin/migrations')->name('admin.migrations.')->group(function () {
    Route::get('/status', [MigrationController::class, 'status'])->name('status');
    Route::post('/system', [MigrationController::class, 'runSystemMigrations'])->name('system');
    Route::post('/all', [MigrationController::class, 'runAllMigrations'])->name('all');
});
