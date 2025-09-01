<?php

namespace App\Http\Controllers;

use App\Builder\ResponseBuilder;
use App\Helpers\ExpenseExtractor;
use App\Http\Requests\ExpenseFormRequest;
use App\Http\Requests\ExpenseListingFormRequest;
use App\Models\Category;
use App\Models\Expense;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(ExpenseListingFormRequest $request): ResponseBuilder
    {
        $builder = new ResponseBuilder(
            $request->applyListingFilters(
                Expense::search($request->get('quicksearch'))
                    ->with('category')
                    ->where('user_id', auth()->id())
                    ->latest()
            ),
        );
        $builder->withTotal(['SUM(total_price)']);
        return $builder;
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ExpenseFormRequest $request)
    {
        $expenseAI = new ExpenseExtractor($request->input('prompt'));
        $test = $expenseAI->extract();
        $expense = new Expense();

        $expense->user_id = $request->user()->id;
        // I need to assign the extracted values to the $expense model here
        $expense->title = $test['title'];
        $expense->quantity = $test['quantity'];
        $expense->unit_price = $test['unit_price'];
        $expense->total_price = $test['total_price'];
        // Assuming category is a string and you have a method to get category_id from category name if there's no category in the database just create it
        $expense->category_id = Category::where('name', $test['category'])->first()->id ?? Category::create(['name' => $test['category']])->id;
        $expense->save();

        return response()->json($expense);
    }

    /**
     * Display the specified resource.
     */
    public function show(Expense $expense)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Expense $expense)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Expense $expense)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Expense $expense)
    {
        //
    }
}
