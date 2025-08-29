<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;

class ExpenseListingFormRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'quicksearch' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'filter' => ['nullable', 'in:daily,monthly'],
            'per_page' => ['nullable', 'numeric', 'max:100']
        ];
    }

    public function applyListingFilters(Builder $builder): Builder
    {
        if ($this->filled('category')) {
            $builder->whereHas('category', function ($q) {
                $q->where('name', 'ilike', '%' . $this->category . '%');
            });
        }

        if ($this->filled('name')) {
            $builder->where('title', 'like', '%' . $this->name . '%');
        }

        if ($this->filter === 'monthly') {
            $builder->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year);
        } else {
            $builder->whereDate('created_at', today());
        }

        return $builder;
    }
}
