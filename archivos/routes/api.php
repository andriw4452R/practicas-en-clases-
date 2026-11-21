<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PracticaReportesController;

Route::prefix('p31/reportes')->group(function () {
    Route::post('/', [PracticaReportesController::class, 'publicar']);
    Route::post('/sincrono', [PracticaReportesController::class, 'sincrono']);
    Route::get('/{correlationId}', [PracticaReportesController::class, 'consultar']);
});
