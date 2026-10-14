<?php

use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\CatalogController;
use Illuminate\Support\Facades\Route;

Route::apiResource('books', BookController::class)->except('show');
Route::get('/catalog/search', [CatalogController::class, 'search']);
