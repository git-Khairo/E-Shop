<?php

namespace App\Http\Controllers;

use App\Models\categories;
use App\Models\product;
use Illuminate\Http\Request;



class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('pages.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
       // dd($request);
        $fields=$request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric',
            'amount' => 'required|integer',
            'category' => 'required|string|max:255',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Find the category by name
        $category = categories::where('type', $request->category)->first();

        if (!$category) {
            return back()->withErrors(['category' => 'Category not found.'])->withInput();
        }

        // Store the uploaded image
        $imageName = $request->file('image')->store('products', 'public');

        // Create the product
        $product = new Product([
            'name' => $request->name,
            'price' => $request->price,
            'amount' => $request->amount,
            'image' => $imageName,
        ]);

        // Associate the product with the category
        $category->products()->save($product);

        return redirect()->route('showProducts')->with('success', 'Product added successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(product $product)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(product $product)
    {
         $category = categories::find($product->categories_id);
        return view('pages.Edit',compact('product','category'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, product $product)
    {

        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric',
            'amount' => 'required|integer',
            'category' => 'required|string|max:255',
            'image' => 'image|mimes:jpeg,png,jpg,gif|max:2048', // Image is optional during update
        ]);

        $category = categories::where('type', $request->category)->first();

        if (!$category) {
            return back()->withErrors(['category' => 'Category not found.'])->withInput();
        }

        if ($request->hasFile('image')) {
            $imageName = $request->file('image')->store('products', 'public');
            $product->image = $imageName;
        }
        else{
            $product->image = $product->image ;
        }

        $product->name = $request->name;
        $product->price = $request->price;
        $product->amount = $request->amount;
        $category->products()->save($product);

        return redirect()->route('AdminPanel')->with('success', 'Product updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(product $product)
    {
        $product->delete();

        return back();
    }
}
