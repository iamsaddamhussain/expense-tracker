<?php

namespace App\Helpers;

use Illuminate\Contracts\Database\Eloquent\Builder;

class AppHelpers
{
    public static function keywordSearch($builder, array $searchFields, ?string $term, bool $leftAnchor = false, bool $splitSpaces = true)
    {
        // Do nothing if no term provided
        if (!$term) return $builder;

        // Attach a where filter for each keyword and search field.
        $builder->where(function ($builder) use ($searchFields, $term, $leftAnchor, $splitSpaces) {
            // Treat space as separate search tokens?
            $tokens = $splitSpaces ? explode(' ', $term) : [$term];

            foreach ($tokens as $token) {
                $keyword = (!$leftAnchor ? '%' : '') . $token . '%';
                $builder->where(function ($builder) use ($searchFields, $keyword) {
                    foreach ($searchFields as $field) {
                        $builder->orWhere($field, 'ilike', $keyword);
                    }
                });
            }
        });

        // Always include search on ID field.
        $builder->orWhereRaw($builder->qualifyColumn('id') . '::text = ?', [$term]);

        return $builder;
    }
}
