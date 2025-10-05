<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\HelpRequestController;
use App\Http\Controllers\Api\SalaryController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {
    Route::prefix('attendance')->group(function () {
        Route::post('login', [AttendanceController::class, 'login']);
        Route::post('logout', [AttendanceController::class, 'logout']);
        Route::get('status', [AttendanceController::class, 'status']);
    });

    Route::prefix('activity')->group(function () {
        Route::post('heartbeat', [ActivityController::class, 'heartbeat']);
        Route::post('samples', [ActivityController::class, 'storeSamples']);
    });

    Route::prefix('help-requests')->group(function () {
        Route::get('current', [HelpRequestController::class, 'current']);
        Route::post('/', [HelpRequestController::class, 'store']);
        Route::post('{helpRequest}/accept', [HelpRequestController::class, 'accept']);
        Route::post('{helpRequest}/start', [HelpRequestController::class, 'start']);
        Route::post('{helpRequest}/finish', [HelpRequestController::class, 'finish']);
        Route::post('{helpRequest}/escalate', [HelpRequestController::class, 'escalate']);
        Route::post('{helpRequest}/cancel', [HelpRequestController::class, 'cancel']);
    });

    Route::prefix('salary')->group(function () {
        Route::get('summary', [SalaryController::class, 'summary']);
        Route::get('runs', [SalaryController::class, 'runs']);
    });
});