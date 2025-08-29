<?php

namespace App\Builder;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\Support\Responsable;

class ResponseBuilder implements Responsable
{
    const PER_PAGE = 5;
    const REQUEST_KEY_QUICK_SEARCH = 'quicksearch';
    public function __construct(private Builder $sourceBuilder)
    {
        //
    }

    public function toResponse($request)
    {
        $this->applyQuickSearch($request);

        return response()->json($this->sourceBuilder->simplePaginate($request->get('per_page', self::PER_PAGE)));
    }

    private function applyQuickSearch($request)
    {
        if (!$request->get(self::REQUEST_KEY_QUICK_SEARCH)) return;

        throw_unless(method_exists($this->sourceBuilder->getModel(), 'scopeSearch'), 'Model does not implement scopeQuickSearch method.');
        $this->sourceBuilder->search($request->get(self::REQUEST_KEY_QUICK_SEARCH));
    }
}
