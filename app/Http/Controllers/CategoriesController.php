<?php

namespace App\Http\Controllers;

use App\Models\categories;
use App\Models\product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoriesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $products = product::paginate(12);
        $categories = categories::get();

        return view('pages.products',['products'=> $products, 'category' => 0, 'categories' => $categories]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request){
        $categoryId = $request->category;

        $products = product::where('categories_id', $categoryId)->paginate(12);

        return view('pages.products',['products'=> $products, 'category' => $categoryId]);
    }

}
