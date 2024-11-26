<?php

namespace App\Http\Controllers;

use App\Models\orders;
use App\Models\product;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function viewAllOrders(){
        $Orders = orders::latest()->get();
        return view('pages.admin',compact('Orders'));
    }

    public function viewAllProducts(){
        $products = product::paginate(12);
        return view('pages.adminProducts',compact('products'));
    }

    public function confirmOrder($orderId){
        $order = orders::find($orderId);
        $order->status = 'confirmed';
        $order->save();
        $Orders = orders::latest()->get();
        return view('pages.admin',compact('Orders'));
    }

    public function deleteOrder($orderId){
        $order = orders::find($orderId);
        $order->status = 'cancelled';
        $order->save();
        $Orders = orders::latest()->get();
        return view('pages.admin',compact('Orders'));
    }
}
