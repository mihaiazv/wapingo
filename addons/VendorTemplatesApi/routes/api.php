<?php

use Addons\VendorTemplatesApi\Controllers\VendorTemplatesApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->middleware('api')->group(function () {
    Route::prefix('{vendorUid}')->middleware('api.vendor.authenticate')->group(function () {
        Route::get('/templates', [VendorTemplatesApiController::class, 'index'])
            ->name('api.vendor.templates.list');
    });
});
