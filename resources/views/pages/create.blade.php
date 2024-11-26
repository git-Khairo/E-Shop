<x-adminpanel>
    <div class="container mx-auto p-4">
        <h2 class="text-2xl font-bold mb-4">Add Product</h2>
        <form action="{{ route('product.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="mb-4">
                <label for="name" class="block text-gray-700">Product Name</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" class="w-full px-4 py-2 border rounded">
            </div>
            <div class="mb-4">
                <label for="price" class="block text-gray-700">Price</label>
                <input type="text" id="price" name="price" value="{{ old('price') }}" class="w-full px-4 py-2 border rounded">
            </div>
            <div class="mb-4">
                <label for="amount" class="block text-gray-700">Stock Amount</label>
                <input type="text" id="amount" name="amount" value="{{ old('amount') }}" class="w-full px-4 py-2 border rounded">
            </div>
            <div class="mb-4">
                <label for="category" class="block text-gray-700">Category</label>
                <select type="text" id="category" name="category" class="w-full px-4 py-2 border rounded">
                    <option value="shirts">Shirts</option>
                    <option value="pants">Pants</option>
                    <option value="jacket">Jackets</option>
                    <option value="shoes">Shoes</option>
                    <option value="underwear">Underwear</option>
                    <option value="accessories">Accessories</option>
                </select>
            </div>
            <div class="mb-4">
                <label for="image" class="block text-gray-700">Product Image</label>
                <input type="file" id="image" name="image" class="w-full px-4 py-2 border rounded">
            </div>
            <div class="flex justify-end">
                <button type="submit" class="bg-teal-500 text-white px-4 py-2 rounded hover:bg-teal-600">Add Product</button>
            </div>
        </form>
    </div>
</x-adminpanel>
