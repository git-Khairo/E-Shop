<?php

namespace App\Http\Controllers;

use App\Models\orders;
use App\Models\product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    public function AddtoCart(Request $request){
        $request->validate([
            'ProductId' => 'required|integer',
        ]);

        $cart = session()->get('cart', []);

        $productKey = 'product_' . $request->ProductId;

        if (isset($cart[$productKey])) {
            $cart[$productKey]['quantity'] += 1;
        } else {
            $cart[$productKey] = [
                'id' => $request->ProductId,
                'quantity' => 1,
            ];
        }

        session(['cart' => $cart]);

        return redirect()->back();
    }

    public function viewCart(){
        $cart = session()->get('cart', []);

        $productIds = array_column($cart, 'id');

        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        foreach ($cart as $cartItem) {
            if (isset($products[$cartItem['id']])) {
                $products[$cartItem['id']]->quantity = $cartItem['quantity'];
            }
        }

        return view('pages.cart', ['cart' => $products]);
    }

    public function increaseQuantity($productId){
        $cart = session('cart', []);
        $productName = 'product_' . $productId;

        if (isset($cart[$productName])) {
            $cart[$productName]['quantity']++;
            session(['cart' => $cart]);
            CartController::viewCart();
        }
    
        return redirect()->route('cart');
    }

    public function decreaseQuantity($productId){
        $cart = session('cart', []);
        $productName = 'product_' . $productId;

        if (isset($cart[$productName])) {
            if ($cart[$productName]['quantity'] > 1) {
                $cart[$productName]['quantity']--;
            } else {
                unset($cart[$productName]);
            }

            session(['cart' => $cart]);
            CartController::viewCart();
        }

        return redirect()->route('cart');
    }

    public function removeFromCart($productId){
        $productName = 'product_' . $productId;

        $cart = session('cart', []);
        unset($cart[$productName]);
        session(['cart' => $cart]);

        return redirect()->back();
    }

    public function saveToDatabase(Request $request){

        $cart = json_decode($request->cart,true);

        if(!empty($cart)){
    
            $processedCart = array_map(function ($item) {
                return [
                    'id' => $item['id'],
                    'quantity' => $item['quantity'],
                ];
            }, $cart);

            $order = orders::create([
                'user_id' => Auth::User()->id,
                'products' => json_encode($processedCart),
                'status' => 'pending',
                'price' => $request->total,
            ]);

            session()->forget('cart');

            return redirect()->route('home')->with('success', 'Your Payment was Successfully Stored');
        }

        return redirect()->route('cart')->with('Empty', 'Your Cart is empty, please fill your cart first');

    }
}
