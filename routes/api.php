<?php

use App\Models\Book;
use Illuminate\Support\Facades\Route;

Route::get('/books', fn () => Book::all());
