<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DataController;
use App\Http\Controllers\DataPsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SalesCodesController;
use App\Http\Controllers\LandingPageController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Admin\UserController;

//Reigter Route
Route::prefix('admin')->group(function () {
    Route::post('/users', [UserController::class, 'store']); // Register a new user
    Route::post('/users/import', [UserController::class, 'import']); // Import users from Excel
});

Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:api');
Route::get('/profile', [AuthController::class, 'profile'])->middleware('auth:api');
Route::get('/sales-statistics', [LandingPageController::class, 'index']);
Route::get('/dashboard', [DashboardController::class, 'dashboard']);

//Data Route
Route::prefix('data')->group(function () {
    Route::get('/', [DataController::class, 'index']);
    Route::get('/{id}', [DataController::class, 'showDetails']);
});


// DataPs routes
Route::prefix('data-ps')->group(function () {
    Route::get('/', [DataPsController::class, 'index']);
    Route::get('/', [DataPsController::class, 'index']); // Get all data
    Route::get('/{id}', [DataPsController::class, 'show']); // Get a specific data by ID
    Route::post('/store', [DataPsController::class, 'store']); // Create new data
    Route::put('/{id}', [DataPsController::class, 'update']); // Update existing data
    Route::delete('/{id}', [DataPsController::class, 'destroy']); // Delete data

    //Data PS analisis Route
    Route::get('/analysis/sto', [DataPsController::class, 'analysisBySto']);
    Route::get('/analysis/month', [DataPsController::class, 'analysisByMonth']);
    Route::get('/analysis/code', [DataPsController::class, 'analysisByCode']);
    Route::get('/analysis/mitra', [DataPsController::class, 'analysisByMitra']);
    Route::get('/sto/chart', [DataPsController::class, 'stoChart']);
    Route::get('/sto/pie-chart', [DataPsController::class, 'stoPie']);
    Route::get('/mitra/bar-chart', [DataPsController::class, 'mitraBarChartAnalysis']);
    Route::get('/mitra/pie-chart', [DataPsController::class, 'mitraPieChartAnalysis']);
    Route::get('/day-analysis', [DataPsController::class, 'dayAnalysis']);
    Route::get('/target-tracking', [DataPsController::class, 'targetTrackingAndSalesChart']);

    //Data Ps import Route
    Route::post('/import', [DataPsController::class, 'importExcel']);
});


// SalesCode routes
Route::prefix('sales-codes')->group(function () {
    Route::get('/', [SalesCodesController::class, 'index']); // Get all sales codes
    Route::post('/store', [SalesCodesController::class, 'store']); // Create a new sales code
    Route::get('/{id}', [SalesCodesController::class, 'show']); // Get a specific sales code
    Route::put('/{id}', [SalesCodesController::class, 'update']); // Update a specific sales code
    Route::delete('/{id}', [SalesCodesController::class, 'destroy']); // Delete a specific sales code
    Route::post('/import', [SalesCodesController::class, 'importExcel']); // Import sales codes from Excel
});
