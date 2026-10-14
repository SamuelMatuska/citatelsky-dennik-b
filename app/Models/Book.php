<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    public const GENRES = [
        'beletria', 'detektívka', 'fantasy', 'sci-fi', 'romantika', 'historická literatúra',
        'biografia', 'poézia', 'odborná literatúra', 'detská literatúra', 'iný',
    ];

    // neprečítaná, čítam, prečítaná, na zozname na prečítanie
    public const STATUSES = ['unread', 'reading', 'read', 'to_read'];

    protected $fillable = ['title', 'author', 'publish_year', 'genre', 'pages', 'isbn', 'status', 'pages_read'];

    protected $appends = ['percent'];

    protected $casts = ['publish_year' => 'integer', 'pages' => 'integer', 'pages_read' => 'integer'];

    public function getPercentAttribute(): int
    {
        return $this->pages > 0 ? intdiv($this->pages_read * 100, $this->pages) : 0;
    }

    /**
     * Zladí stav a prečítané strany podľa DNR S.25, S.26, S.38, S.39.
     * Zmena stavu má prednosť pred zadanými stranami.
     */
    public function syncProgress(bool $statusChanged, bool $pagesReadChanged): void
    {
        if ($statusChanged && $this->status === 'unread') {
            $this->pages_read = 0;
        } elseif ($this->status === 'read' && ($statusChanged || ! $pagesReadChanged)) {
            $this->pages_read = $this->pages;
        } elseif ($this->pages_read >= $this->pages) {
            $this->status = 'read';
        } elseif ($this->pages_read >= 1 && in_array($this->status, ['unread', 'to_read', 'read'], true)) {
            $this->status = 'reading';
        }
    }
}
