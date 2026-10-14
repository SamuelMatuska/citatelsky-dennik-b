<?php

namespace Tests\Feature;

use App\Models\Book;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Dune', 'author' => 'Frank Herbert', 'publish_year' => 1965,
            'genre' => 'sci-fi', 'pages' => 400,
        ], $overrides);
    }

    public function test_new_book_defaults_to_unread(): void
    {
        $this->postJson('/api/books', $this->payload())
            ->assertCreated()
            ->assertJson(['status' => 'unread', 'pages_read' => 0, 'percent' => 0]);
    }

    public function test_genre_must_be_from_fixed_list(): void
    {
        $this->postJson('/api/books', $this->payload(['genre' => 'horor']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('genre');
    }

    public function test_isbn_is_normalized_and_validated(): void
    {
        $this->postJson('/api/books', $this->payload(['isbn' => '978-0-441-17271-9']))
            ->assertCreated()->assertJson(['isbn' => '9780441172719']);
        $this->postJson('/api/books', $this->payload(['isbn' => '12345']))
            ->assertUnprocessable()->assertJsonValidationErrors('isbn');
    }

    public function test_reading_pages_switches_status_and_computes_percent(): void
    {
        $book = Book::create($this->payload(['status' => 'to_read']));

        $this->putJson("/api/books/{$book->id}", ['pages_read' => 133])
            ->assertOk()->assertJson(['status' => 'reading', 'percent' => 33]);

        $this->putJson("/api/books/{$book->id}", ['pages_read' => 400])
            ->assertOk()->assertJson(['status' => 'read', 'percent' => 100]);
    }

    public function test_pages_read_cannot_exceed_pages(): void
    {
        $book = Book::create($this->payload());

        $this->putJson("/api/books/{$book->id}", ['pages_read' => 401])
            ->assertUnprocessable()->assertJsonValidationErrors('pages_read');
        $this->putJson("/api/books/{$book->id}", ['pages' => 10, 'pages_read' => 0])->assertOk();
    }

    public function test_status_change_sets_pages(): void
    {
        $book = Book::create($this->payload(['status' => 'reading', 'pages_read' => 50]));

        $this->putJson("/api/books/{$book->id}", ['status' => 'read'])
            ->assertJson(['pages_read' => 400, 'percent' => 100]);
        $this->putJson("/api/books/{$book->id}", ['status' => 'unread'])
            ->assertJson(['pages_read' => 0, 'status' => 'unread']);
    }

    public function test_read_book_stays_read_when_page_count_is_edited(): void
    {
        $book = Book::create($this->payload(['status' => 'read', 'pages_read' => 400]));

        $this->putJson("/api/books/{$book->id}", ['pages' => 420])
            ->assertJson(['status' => 'read', 'pages_read' => 420]);
    }

    public function test_index_and_delete(): void
    {
        $book = Book::create($this->payload());

        $this->getJson('/api/books')->assertOk()->assertJsonCount(1);
        $this->deleteJson("/api/books/{$book->id}")->assertNoContent();
        $this->getJson('/api/books')->assertJsonCount(0);
    }
}
