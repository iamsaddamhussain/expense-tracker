<?php

namespace App\Builder;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResponseBuilder implements Responsable
{
    const PER_PAGE = 5;
    const REQUEST_KEY_QUICK_SEARCH = 'quicksearch';

    private ?\Closure $mappingCallback = null;
    private ?string $resourceClass = null;

    private array $totalFields = [];

    private array $additionalData = [];

    public function __construct(private Builder|EloquentBuilder|HasMany|MorphMany $sourceBuilder)
    {
        //
    }

    /**
     * Allows retruning of this class directly in the controller.
     * @param mixed $request
     * @return array{data: mixed, num_rows: mixed, total: array|array{data: mixed, num_rows: mixed, total: float[]}}
     */
    public function toResponse($request)
    {
        $this->applyQuickSearch($request);

        // Get total count before any pagination
        $totalResults = (clone $this->sourceBuilder)
            ->clearLateralJoins() // Laravel joins slow down count queries
            ->reorder()
            ->count();

        // Calculate totals before pagination
        $totals = [];
        foreach ($this->totalFields as $field => $totalField) {
            $totals[$field] = floatval(
                DB::query()->fromSub(clone $this->sourceBuilder, 'sub')
                    ->selectRaw("$totalField as value")
                    ->first()
                    ->value
            );
        }

        // Apply pagination
        $take = $request->get('per_page', self::PER_PAGE);
        $skip = $request->get('skip', 0);

        if ($totalResults > $take || $skip > 0) {
            $this->sourceBuilder->take($take)->skip($skip);
        }

        // Get paginated results
        $results = $this->sourceBuilder->get();

        // caller method to map each result?
        if ($this->mappingCallback) {
            $results = $results->map($this->mappingCallback);
        }

        // transform using a resource class?
        if ($this->resourceClass) {
            $results = ($this->resourceClass)::collection($results)->toArray($request);
        }

        return [
            'data' => $results,
            ...$this->additionalData,
            'num_rows' => $totalResults,
            'totals' => $totals,
        ];
    }

    /**
     * Apply quick search if the request has the quicksearch key.
     * @param mixed $request
     * @return void
     */
    private function applyQuickSearch(Request $request)
    {
        if (!$request->get(self::REQUEST_KEY_QUICK_SEARCH)) return;

        throw_unless(method_exists($this->sourceBuilder->getModel(), 'scopeSearch'), 'Model does not implement scopeQuickSearch method.');
        $this->sourceBuilder->search($request->get(self::REQUEST_KEY_QUICK_SEARCH));
    }

    /**
     * Apply a function to map each result item.
     * @param \Closure|callable $callback
     * @return ResponseBuilder
     */
    public function map(\Closure|callable $callback): self
    {
        $this->mappingCallback = $callback;
        return $this;
    }

    /**
     * Set the resource class to be used for transforming the result set.
     * @param string $resourceClass
     * @return ResponseBuilder
     */
    public function usingResource(string $resourceClass): self
    {
        $this->resourceClass = $resourceClass;
        return $this;
    }

    /**
     * Set some additional fields to be included from the result set.
     * @param array $additionalData
     * @return ResponseBuilder
     */
    public function with(array $additionalData): self
    {
        $this->additionalData = $additionalData;
        return $this;
    }

    /**
     * Set total fields to be totalled and include in the result set.
     * Array items should be in the form of
     *
     * e.g ['grand_total' => 'SUM(total_price)']
     * @param array $totalFields
     * @return ResponseBuilder
     */
    public function withTotal(array $totalFields): self
    {
        $this->totalFields = $totalFields;
        return $this;
    }
}
