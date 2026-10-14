<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    public function test_search_by_title_maps_results(): void
    {
        Http::fake(['openlibrary.org/*' => Http::response(['docs' => [[
            'title' => 'Dune', 'author_name' => ['Frank Herbert'], 'first_publish_year' => 1965,
            'number_of_pages_median' => 412, 'isbn' => ['0441172717', '9780441172719'],
        ]]])]);

        $this->getJson('/api/catalog/search?title=dune')
            ->assertOk()
            ->assertExactJson(['results' => [[
                'title' => 'Dune', 'author' => 'Frank Herbert', 'publish_year' => 1965,
                'pages' => 412, 'isbn' => '9780441172719',
            ]]]);
    }

    public function test_results_are_limited_to_ten(): void
    {
        Http::fake(['openlibrary.org/*' => Http::response(['docs' => array_fill(0, 15, ['title' => 'X'])])]);

        $this->getJson('/api/catalog/search?title=x')->assertOk()->assertJsonCount(10, 'results');
    }

    public function test_invalid_isbn_length_is_rejected(): void
    {
        Http::fake();

        $this->getJson('/api/catalog/search?isbn=123456789')->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_isbn_with_hyphens_is_accepted(): void
    {
        Http::fake(['openlibrary.org/*' => Http::response(['docs' => [['title' => 'Dune']]])]);

        $this->getJson('/api/catalog/search?isbn=978-0-441-17271-9')
            ->assertOk()->assertJsonPath('results.0.isbn', '9780441172719');
    }

    public function test_no_results_returns_404(): void
    {
        Http::fake(['openlibrary.org/*' => Http::response(['docs' => []])]);

        $this->getJson('/api/catalog/search?title=nic')->assertNotFound();
    }

    public function test_unavailable_catalog_returns_503(): void
    {
        Http::fake(fn () => throw new ConnectionException('timeout'));

        $this->getJson('/api/catalog/search?title=dune')->assertStatus(503);
    }
}
