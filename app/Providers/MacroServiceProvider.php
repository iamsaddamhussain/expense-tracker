<?php

namespace App\Providers;

use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class MacroServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->bootCollectionMacros();
        $this->bootQueryBuilderMacros();
    }

    private function bootCollectionMacros()
    {
        // Produce a RAW Where In SQL query. This should only be used for vision queries to help with performance of large wherein's.
        Collection::macro('whereInRaw', function (string $field, bool $strings = true, bool $notIn = false) {
            /** @var Collection */
            $collection = $this;

            if ($strings) {
                $collection = $collection->map(fn($val) => "'" . $val . "'");
            }

            return sprintf('%s %s (%s)', $field, $notIn ? 'NOT IN' : 'IN', $collection->implode(','));
        });

        /**
         * Similar to Collection::pluck() but can pass an array of multiple fields to pluck in one go.
         * Duplicates are removed as the returned collection is keyed by the found values.
         */
        Collection::macro('pluckMany', function (array $keys) {
            /** @var Collection */
            $collection = $this;

            $items = collect();
            foreach ($keys as $key) {
                $items = $items->merge($collection->pluck($key, $key));
            }

            return $items;
        });
    }

    private function bootQueryBuilderMacros()
    {
        $this->makeQueryBuilderMacro('ddCount', function (Builder $builder) {
            dd($builder->count());
        });

        // Determine whether the builder has a join already applied
        $this->makeQueryBuilderMacro('hasJoin', function (Builder $builder, $table) {
            $joins = collect($builder->joins);
            return $joins->filter(fn($join) => Str::contains($join->table, $table))->isNotEmpty();
        });

        // Custom order by to matches a provided id array.
        // Useful when you want to perform a whereIn and ensure that the results are returned in the same order as specified by the whereIn
        $this->makeQueryBuilderMacro('orderByIds', function (Builder $builder, Collection $ids) {
            if ($ids->isEmpty()) return; // noop when empty otherwise sql error

            return $builder->orderBy(DB::raw(sprintf("array_position(ARRAY[%s], id::int)", $ids->implode(','))));
        });

        /**
         * Query the database to see if the given column exists on this model
         */
        $this->makeQueryBuilderMacro('hasColumn', function (Builder $builder, ?string $column) {
            if (!$column) return false;

            /** @var \Illuminate\Database\PostgresConnection */
            $conn = $builder->getConnection();
            return $conn->getSchemaBuilder()->hasColumn($builder->from, $column);
        });

        // A way to retrieve or check if a builder has orders applied
        $this->makeQueryBuilderMacro('getOrders', function (Builder $builder) {
            $orders = $builder->orders ?: [];
            $unionOrders = $builder->unionOrders ?: [];
            return array_merge($orders, $unionOrders);
        });

        // Provide a way to clear only lateral joins from the given builder
        $this->makeQueryBuilderMacro('clearLateralJoins', function (Builder $builder) {
            $builder->joins = collect($builder->joins)
                ->filter(function ($join) {
                    // Only care about expressions (will have a getValue function)
                    if (!method_exists($join->table, 'getValue')) return true;

                    $joinStr = $join->table->getValue($join->getGrammar());
                    return !is_string($joinStr) || !Str::contains($joinStr, 'lateral');
                })
                ->toArray();
            return $builder;
        });
    }

    /**
     * Register a macro for both the eloquent builder and query builder at the same time
     */
    private function makeQueryBuilderMacro(string $name, callable $macroBuilder)
    {
        // Attach the macro to the base query builder
        QueryBuilder::macro($name, function (...$params) use ($macroBuilder) {
            /** @var Builder */
            $builder = $this;
            return $macroBuilder($builder, ...$params);
        });

        // Also create a macro for any eloquent query builders (which just call into the base query builder macro)
        EloquentBuilder::macro($name, function (...$params) use ($macroBuilder) {
            /** @var EloquentBuilder */
            $eloquentBuilder = $this;
            $result = $macroBuilder($eloquentBuilder->toBase(), ...$params);

            // If the macro produced a builder, return the original eloquent version, otherwise return the macro result
            return $result instanceof Builder
                ? $eloquentBuilder
                : $result;
        });
    }
}
