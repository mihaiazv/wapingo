<?php
use Addons\KeywordRouting\Controllers\KeywordRoutingController;

Route::middleware(['web', 'auth'])->prefix('keyword-routing')->group(function() {
    Route::get('/', [KeywordRoutingController::class, 'index'])->name('keyword.routing.index');
    Route::post('/store', [KeywordRoutingController::class, 'store'])->name('keyword.routing.store');
    Route::delete('/delete/{id}', [KeywordRoutingController::class, 'destroy'])->name('keyword.routing.delete');
});
