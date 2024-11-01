<x-layout>

    <h1 class="text-3xl font-bold text-center my-6 text-green-500">Products in {{ $category->type }}</h1>
    <ul class="space-y-4">
        @foreach($products as $product)
            <li class="bg-white p-6 rounded-lg shadow flex justify-between items-center">
                <div>
                    <strong class="text-xl">{{ $product->name }}</strong>
                    <p class="text-lg text-gray-600">${{ $product->price }}</p>
                </div>
                <span class="text-gray-500">Amount: {{ $product->amount }}</span>
            </li>
        @endforeach
    </ul>

    <!-- Pagination Links -->
    <div class="pagination mt-8">
        {{ $products->links() }}
    </div>

    <a href="{{ url('/') }}">
        <button class="primary-btn mt-4 ">Back to home page</button>
    </a>
</x-layout>

