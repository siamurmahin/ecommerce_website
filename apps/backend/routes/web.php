<?php

use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\FeedController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/admin/invoices', InvoiceController::class)
    ->middleware('auth')
    ->name('admin.invoices');

Route::get('/feeds/{channel}.xml', FeedController::class)->name('feeds.show');
