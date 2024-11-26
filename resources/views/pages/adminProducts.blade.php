<x-adminpanel>
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        @foreach ($products as $product)
            <x-product :product="$product" admin></x-product>
        @endforeach
    </div>

    <div class="flex items-center justify-center mt-4">
        {{ $products->links() }}
    </div>
</x-adminpanel>