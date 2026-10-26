<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuthenticatedSessionController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LogbookController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::resource('logbooks', LogbookController::class)
        ->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);

    Route::post('/logbooks/{logbook}/submit', [LogbookController::class, 'submit'])
        ->name('logbooks.submit');

    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');

    Route::get('/analytics', [AnalyticsController::class, 'index'])
        ->middleware('can:view-analytics')
        ->name('analytics.index');

    Route::middleware(['role:supervisor,manager,super_admin', 'throttle:review'])
        ->prefix('reviews')
        ->name('reviews.')
        ->controller(ReviewController::class)
        ->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/bulk-approve', 'bulkApprove')->middleware('throttle:review-bulk')->name('bulk-approve');
            Route::get('/{logbook}', 'show')->name('show');
            Route::post('/{logbook}/approve', 'approve')->name('approve');
            Route::post('/{logbook}/request-revision', 'requestRevision')->name('request-revision');
            Route::post('/{logbook}/reject', 'reject')->name('reject');
        });
});
