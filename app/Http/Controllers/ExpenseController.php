<?php

namespace App\Http\Controllers;

use App\Builder\ResponseBuilder;
use App\Helpers\ExpenseExtractor;
use App\Http\Requests\ExpenseFormRequest;
use App\Http\Requests\ExpenseListingFormRequest;
use App\Models\Category;
use App\Models\Expense;
use App\Models\User;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $model = $expenseAI->extract();

        $expense = new Expense();
        $expense->title = $model['title'];
        $expense->quantity = $model['quantity'];
        $expense->unit_price = $model['unit_price'];
        $expense->unit = $model['unit'];
        $expense->total_price = $model['total_price'];
        $expense->user()->associate(User::findOrFail($request->user()->id));
        $expense->category()->associate(
            Category::firstOrCreate(['name' => $model['category']])
        );
        $expense->save();

        return response()->json([
            'message' => 'Expense created successfully',
            'data' => $expense->load('category')
        ], 201);
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
