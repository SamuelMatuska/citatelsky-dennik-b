<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

// Vyhľadanie v Open Library (DNR S.10 – S.16).
class CatalogController extends Controller
{
    public function search(Request $request)
    {
        $title = trim((string) $request->query('title'));
        $isbn = strtoupper(preg_replace('/[\s-]/', '', (string) $request->query('isbn')));

        if ($isbn !== '' && ! preg_match('/^(\d{9}[\dX]|\d{13})$/', $isbn)) {
            return response()->json(['message' => 'ISBN musí mať 10 alebo 13 číslic.'], 422);
        }
        if ($isbn === '' && $title === '') {
            return response()->json(['message' => 'Zadajte názov alebo ISBN.'], 422);
        }

        try {
            $response = Http::timeout(5)
                ->withUserAgent('CitatelskyDennikB/1.0 (skolsky projekt)')
                ->get('https://openlibrary.org/search.json', [
                    ...($isbn !== '' ? ['isbn' => $isbn] : ['title' => $title]),
                    'limit' => 10,
                    'fields' => 'title,author_name,first_publish_year,number_of_pages_median,isbn',
                ]);
        } catch (ConnectionException) {
            return $this->unavailable();
        }

        if ($response->failed()) {
            return $this->unavailable();
        }

        $results = collect($response->json('docs', []))->take(10)->map(fn (array $doc) => [
            'title' => $doc['title'] ?? '',
            'author' => $doc['author_name'][0] ?? null,
            'publish_year' => $doc['first_publish_year'] ?? null,
            'pages' => $doc['number_of_pages_median'] ?? null,
            'isbn' => $isbn !== '' ? $isbn : $this->pickIsbn($doc['isbn'] ?? []),
        ])->values();

        if ($results->isEmpty()) {
            return response()->json(['message' => 'Katalóg nenašiel žiadnu knihu. Môžete ju pridať ručne.'], 404);
        }

        return ['results' => $results];
    }

    private function pickIsbn(array $isbns): ?string
    {
        return collect($isbns)->first(fn ($i) => strlen($i) === 13) ?? ($isbns[0] ?? null);
    }

    private function unavailable()
    {
        return response()->json(['message' => 'Katalóg Open Library neodpovedá. Skúste to neskôr alebo pridajte knihu ručne.'], 503);
    }
}
