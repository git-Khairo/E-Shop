<?php

namespace App\Http\Controllers;

use App\Models\orders;
use App\Models\product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class Dashboardcontroller extends Controller
{
    public function viewUserOrders(){
        $user_id = Auth::user()->id;
        $userOrders = orders::where('user_id', $user_id)->latest()->get();
        return view('pages.userDashboard', ['userOrders' => $userOrders, 'option' => 'all']);
    }

    public function viewUserOrdersOption(Request $request){
        $user_id = Auth::user()->id;

        $status = $request->orderType;
    
        $userOrders = orders::where('user_id', $user_id)
            ->when($status !== 'all', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->latest()
            ->get();
    
        return view('pages.userDashboard', ['userOrders' => $userOrders, 'option' => $status]);
    }

    public function viewUserProfile(){
        $user_id = Auth::user()->id;
        $user = User::find($user_id);
        $orderCount = $user->orders->count();
        return view('pages.userProfile', ['user' => $user, 'orderCount' => $orderCount]);
    }

    public function updateUserProfile(Request $request)
    {
        $fields = $request->validate([
            'username' => ['required', 'max:255'],
            'email' => ['required', 'max:255', 'email', 'unique:users,email,' . Auth::id()],
            'password' => ['nullable', 'min:3'],
        ]);

        $user_id = Auth::user()->id;
        $user = User::find($user_id);
        $orderCount = $user->orders->count();

        $user->update([
            'username' => $request->username,
            'email' => $request->email,
        ]);
    
        if ($request->has('password') && !empty($request->password)) {
            $userPassword = Hash::make($fields['password']);

            $user->update([
                'password' => $userPassword,
            ]);
        }
    
        return view('pages.userProfile', ['user' => $user, 'orderCount' => $orderCount]);
    }
    

    public function orderDetails($orderId)
    {
        $order = orders::find($orderId);
    
        $products = json_decode($order->products, true);
    
        if (!is_array($products)) {
            return null;
        }
    
        $detailedProducts = [];
        foreach ($products as $product) {
            $productDetails = product::find($product['id']);
            if ($productDetails) {
                $detailedProducts[] = [
                    'product_id' => $productDetails->id,
                    'name'       => $productDetails->name,
                    'price'      => $productDetails->price,
                    'image'      => $productDetails->image,
                    'quantity'   => $product['quantity'],
                ];
            }
        }
    
        $orderDetails = [
            'id'    => $order->id,
            'user_id'     => $order->user_id,
            'status'      => $order->status,
            'price'       => $order->price,
            'created_at'  => $order->created_at,
            'products'    => $detailedProducts,
        ];
    
        return view('components.orderDetails', ['orderDetails' => $orderDetails]);
    }

    public function deleteOrder($orderId){
        $order = orders::find($orderId);

        if ($order) {
            $order->delete();
            Dashboardcontroller::viewUserOrders();
        }
        return redirect()->route('dashboard')->withErrors(['Delete' => 'no such Order']);
    }
}
