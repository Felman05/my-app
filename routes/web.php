<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\Admin\ActivityModerationController;
use App\Http\Controllers\Api\MapDataController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'landing'])->middleware('guest')->name('landing');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::middleware('role:tourist')->group(function () {
        Route::get('/tourist/dashboard', [DashboardController::class, 'tourist'])->name('tourist.dashboard');
        Route::get('/tourist/activities', [ActivityController::class, 'touristIndex'])->name('tourist.activities.index');
    });

    Route::middleware('role:provider')->group(function () {
        Route::get('/provider/dashboard', [DashboardController::class, 'provider'])->name('provider.dashboard');
        Route::get('/provider/activities', [ActivityController::class, 'providerIndex'])->name('provider.activities.index');
        Route::get('/provider/activities/create', [ActivityController::class, 'create'])->name('provider.activities.create');
        Route::post('/provider/activities', [ActivityController::class, 'store'])->name('provider.activities.store');
    });

    Route::middleware('role:lgu')->group(function () {
        Route::get('/lgu/dashboard', [DashboardController::class, 'lgu'])->name('lgu.dashboard');
    });

    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/dashboard', [DashboardController::class, 'admin'])->name('admin.dashboard');
        Route::get('/admin/moderation/activities', [ActivityModerationController::class, 'index'])->name('admin.moderation.activities.index');
        Route::post('/admin/moderation/activities/{activityId}/approve', [ActivityModerationController::class, 'approve'])->name('admin.moderation.activities.approve');
        Route::post('/admin/moderation/activities/{activityId}/reject', [ActivityModerationController::class, 'reject'])->name('admin.moderation.activities.reject');
    });

    Route::get('/api/map/points', [MapDataController::class, 'points'])->name('api.map.points');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
