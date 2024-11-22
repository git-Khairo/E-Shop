<x-layout>
    @php 
        $total = 0;
        $subtotal = 0;
    @endphp
    <div class="font-sans max-w-5xl max-md:max-w-xl mx-auto bg-white py-4">
        <h1 class="text-3xl font-bold text-gray-800 text-center">My Cart</h1>

        <div class="grid md:grid-cols-3 gap-8 mt-16">
            <div class="md:col-span-2 space-y-4">
                @foreach ($cart as $cartItem)
                    <x-cartItem :cartItem="$cartItem" />
                    @php 
                        $subtotal = $cartItem->price * $cartItem->quantity;
                        $total += $subtotal;
                    @endphp
                @endforeach
            </div>

            <div class="bg-gray-100 rounded-md p-4 h-max">
                <h3 class="text-lg max-sm:text-base font-bold text-gray-800 border-b border-gray-300 pb-2">Order Summary</h3>

                <ul class="text-gray-800 mt-6 space-y-3">
                    <li class="flex flex-wrap gap-4 text-sm">Subtotal <span class="ml-auto font-bold">${{ $total }}</span></li>
                    <li class="flex flex-wrap gap-4 text-sm">Shipping <span class="ml-auto font-bold">$5.00</span></li>
                    <li class="flex flex-wrap gap-4 text-sm">Tax <span class="ml-auto font-bold">${{ round($total * 2 /100, 2) }}</span></li>
                    <hr class="border-gray-300" />
                    <li class="flex flex-wrap gap-4 text-sm font-bold">Total <span class="ml-auto">${{ $total + round($total * 2 /100, 2) + 5 }}</span></li>
                </ul>

                <div class="mt-6 space-y-3">
                    <form action="{{ route('saveToDatabase') }}" method="post">
                        @csrf
                        <input type="hidden" name="cart" value="{{ $cart }}">
                        <button type="button" class="text-sm px-4 py-2.5 w-full font-semibold tracking-wide bg-teal-500 hover:bg-teal-700 text-white rounded-md" onclick="this.form.submit()">Checkout</button>
                    </form>

                    <form action="{{ route('products') }}" method="get">
                        @csrf
                        <button type="button" class="text-sm px-4 py-2.5 w-full font-semibold tracking-wide bg-transparent text-gray-800 border border-gray-300 rounded-md" onclick="this.form.submit()">Continue Shopping</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-layout>