<x-layout>
    @php 
        $total = 0;
        $subtotal = 0;
    @endphp


    @if (session('Empty'))
    <div id="default-modal" tabindex="-1" aria-hidden="true" 
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-50"
        data-modal-backdrop="static" data-modal-hide="default-modal">
        <div class="relative w-full max-w-md bg-white rounded-lg shadow dark:bg-gray-700">
            <div class="flex items-center justify-between p-4 border-b dark:border-gray-600">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                    Invalid Cart
                </h3>
                <button type="button" class="text-gray-400 hover:text-gray-900 dark:hover:text-white" 
                    data-modal-hide="default-modal">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="p-4">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ session('Empty') }}
                </p>
            </div>
            <div class="flex justify-end p-4 border-t dark:border-gray-600">
                <button type="button" 
                    class="px-4 py-2 text-sm font-medium text-white bg-teal-600 rounded-lg hover:bg-teal-700"
                    data-modal-hide="default-modal">Close</button>
            </div>
        </div>
    </div>
    @endif


    
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
                @php
                    $total = $total + round($total * 2 /100, 2) + 5;
                @endphp

                <div class="mt-6 space-y-3">
                    <form action="{{ route('saveToDatabase') }}" method="post">
                        @csrf
                        <input type="hidden" name="cart" value="{{ $cart }}">
                        <input type="hidden" name="total" value="{{ $total }}">
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