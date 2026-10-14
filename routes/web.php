<?php

use Illuminate\Support\Facades\Route;

// React build (frontend/) leží v public/index.html.
Route::get('/', fn () => response()->file(public_path('index.html')));
