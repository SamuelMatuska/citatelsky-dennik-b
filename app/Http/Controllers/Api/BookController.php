<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BookController extends Controller
{
    public function index()
    {
        return Book::orderByDesc('updated_at')->orderByDesc('id')->get();
    }

    public function store(Request $request)
    {
        $book = new Book($this->validated($request));
        $book->status ??= 'unread';
        $book->pages_read ??= 0;
        $book->syncProgress($request->has('status'), $request->has('pages_read'));
        $book->save();

        return response()->json($book->refresh(), 201);
    }

    public function update(Request $request, Book $book)
    {
        $book->fill($this->validated($request, $book));
        $book->syncProgress($book->isDirty('status'), $book->isDirty('pages_read'));
        $book->save();

        return $book;
    }

    public function destroy(Book $book)
    {
        $book->delete();

        return response()->noContent();
    }

    private function validated(Request $request, ?Book $book = null): array
    {
        if ($request->filled('isbn')) {
            $request->merge(['isbn' => strtoupper(preg_replace('/[\s-]/', '', $request->input('isbn')))]);
        }
        $required = $book ? 'sometimes' : 'required';
        $pages = (int) $request->input('pages', $book?->pages);

        return $request->validate([
            'title' => [$required, 'string', 'max:255'],
            'author' => [$required, 'string', 'max:255'],
            'publish_year' => [$required, 'integer', 'min:0', 'max:'.now()->year],
            'genre' => [$required, Rule::in(Book::GENRES)],
            'pages' => [$required, 'integer', 'min:1', 'max:100000'],
            'isbn' => ['nullable', 'regex:/^(\d{9}[\dX]|\d{13})$/'],
            'status' => ['sometimes', Rule::in(Book::STATUSES)],
            'pages_read' => ['sometimes', 'integer', 'min:0', 'max:'.max($pages, 0)],
        ], [
            'required' => 'Pole :attribute je povinné.',
            'string' => 'Pole :attribute musí byť text.',
            'integer' => 'Pole :attribute musí byť celé číslo.',
            'min' => 'Pole :attribute musí byť aspoň :min.',
            'max' => 'Pole :attribute môže byť najviac :max.',
            'in' => 'Pole :attribute má neplatnú hodnotu.',
            'genre.in' => 'Žáner musí byť z pevného zoznamu.',
            'isbn.regex' => 'ISBN musí mať 10 alebo 13 číslic.',
            'pages_read.max' => 'Prečítané strany nemôžu presiahnuť počet strán knihy.',
        ], [
            'title' => 'názov', 'author' => 'autor', 'publish_year' => 'rok vydania', 'genre' => 'žáner',
            'pages' => 'počet strán', 'isbn' => 'ISBN', 'status' => 'stav', 'pages_read' => 'prečítané strany',
        ]);
    }
}
