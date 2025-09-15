<?php

namespace App\Http\Controllers;

use App\Helpers\ExpenseExtractor;
use App\Http\Requests\ExpenseFormRequest;
use App\Http\Requests\ExpenseListingFormRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Category;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class ExpenseController extends Controller
{
    public function index(ExpenseListingFormRequest $request)
    {
        $query = Expense::search($request->get('quicksearch'))
            ->with('category')
            ->where('user_id', auth()->id())
            ->latest();

        // Apply filters (but keep base query untouched for totals)
        $expenses = $request->applyListingFilters(clone $query)
            ->simplePaginate($request->get('per_page', 15));

        // Calculate totals
        $dailyTotal = $request->applyListingFilters(clone $query)
            ->whereDate('created_at', today())
            ->sum('total_price');

        $monthlyTotal = $request->applyListingFilters(clone $query)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('total_price');

        return response()->json([
            'data' => ExpenseResource::collection($expenses),
            'meta' => [
                'daily_total' => $dailyTotal,
                'monthly_total' => $monthlyTotal,
            ],
        ]);
    }

    public function store(ExpenseFormRequest $request)
    {
        $expenseAI = new ExpenseExtractor($request->input('prompt'));
        $models = $expenseAI->extract(); // may return array or object

        // Always ensure it's an array of models
        $models = Arr::isAssoc($models) ? [$models] : $models;

        $expenses = [];
        foreach ($models as $model) {
            $expense = new Expense();
            $expense->title = $model['title'];
            $expense->quantity = $model['quantity'];
            $expense->unit_price = $model['unit_price'];
            $expense->unit = $model['unit'];
            $expense->total_price = $model['total_price'];
            $expense->user()->associate($request->user());
            $expense->category()->associate(
                Category::firstOrCreate(['name' => $model['category']])
            );
            $expense->save();

            $expenses[] = $expense->load('category');
        }

        return ExpenseResource::collection($expenses)
            ->additional([
                'message' => 'Expenses created successfully'
            ])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Expense $expense)
    {
        // update of expense

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Expense $expense)
    {
        $expense->delete();
        return response()->noContent();
    }
}
