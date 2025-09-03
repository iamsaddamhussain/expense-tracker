<?php

namespace App\Models;

use App\Helpers\AppHelpers;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'quantity',
        'unit_price',
        'unit',
        'total_price'
    ];
    protected $casts = [
        'quantity' => 'double',
        'unit_price' => 'double',
        'total_price' => 'double'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeSearch(Builder $q, ?string $term, ?array $columns = null)
    {
        return AppHelpers::keywordSearch($q, $columns ?? ['title', 'description'], $term);
    }
}
