<?php

namespace App\Http\Controllers;

use App\Models\orders;
use App\Models\product;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function index(){
        $products = product::paginate(12);
        //dd( $orders);
        return view('pages.admin',compact('products'));
    }
}
