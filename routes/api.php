<?php

use App\Http\Controllers\Api\V1\StationSummaryController;
use App\Http\Controllers\Api\V1\TransferController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:100,1')->group(function (): void {
    Route::post('/transfers', [TransferController::class, 'store']);
    Route::get('/stations/{station_id}/summary', [StationSummaryController::class, 'show']);
    Route::get('/stations/{station_id}/summary-report', [StationSummaryController::class, 'summaryReport']);
});
