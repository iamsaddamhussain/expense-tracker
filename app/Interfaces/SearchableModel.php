<?php

namespace App\Interfaces;

use Illuminate\Contracts\Database\Eloquent\Builder;

interface SearchableModel
{
    public function scopeQuickSearch(Builder $q, ?string $term, ?array $columns = null);
}
