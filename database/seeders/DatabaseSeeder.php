<?php

namespace Database\Seeders;

use App\Models\Book;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $books = [
            ['title' => 'Dune', 'author' => 'Frank Herbert', 'publish_year' => 1965, 'genre' => 'sci-fi', 'pages' => 412],
            ['title' => 'Hobit', 'author' => 'J. R. R. Tolkien', 'publish_year' => 1937, 'genre' => 'fantasy', 'pages' => 310],
            ['title' => '1984', 'author' => 'George Orwell', 'publish_year' => 1949, 'genre' => 'beletria', 'pages' => 328],
            ['title' => 'Rysavá jalovica', 'author' => 'Martin Kukučín', 'publish_year' => 1885, 'genre' => 'beletria', 'pages' => 96],
            ['title' => 'Saturnin', 'author' => 'Zdeněk Jirotka', 'publish_year' => 1942, 'genre' => 'beletria', 'pages' => 224],
        ];

        // Idempotentné: seed beží pri každom štarte kontajnera.
        foreach ($books as $book) {
            Book::updateOrCreate(['title' => $book['title']], $book);
        }
    }
}
