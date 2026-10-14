<?php

namespace Database\Seeders;

use App\Models\Book;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Ukážkové knihy len do prázdnej databázy, aby seed pri štarte neprepisoval zmeny používateľa.
        if (Book::exists()) {
            return;
        }

        $books = [
            ['title' => 'Dune', 'author' => 'Frank Herbert', 'publish_year' => 1965, 'genre' => 'sci-fi', 'pages' => 412, 'isbn' => '9780441172719', 'status' => 'reading', 'pages_read' => 156],
            ['title' => 'Hobit', 'author' => 'J. R. R. Tolkien', 'publish_year' => 1937, 'genre' => 'fantasy', 'pages' => 310, 'isbn' => '9780547928227', 'status' => 'read', 'pages_read' => 310],
            ['title' => '1984', 'author' => 'George Orwell', 'publish_year' => 1949, 'genre' => 'beletria', 'pages' => 328, 'isbn' => '9780451524935', 'status' => 'to_read', 'pages_read' => 0],
            ['title' => 'Rysavá jalovica', 'author' => 'Martin Kukučín', 'publish_year' => 1885, 'genre' => 'beletria', 'pages' => 96, 'isbn' => null, 'status' => 'unread', 'pages_read' => 0],
            ['title' => 'Saturnin', 'author' => 'Zdeněk Jirotka', 'publish_year' => 1942, 'genre' => 'beletria', 'pages' => 224, 'isbn' => null, 'status' => 'reading', 'pages_read' => 61],
        ];

        foreach ($books as $book) {
            Book::create($book);
        }
    }
}
