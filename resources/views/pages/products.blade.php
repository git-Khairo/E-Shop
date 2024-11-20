<x-layout>
<div class="flex justify-center">
<aside id="default-sidebar" class="relative left-0 z-0 w-96 h-screen transition-transform -translate-x-full sm:translate-x-0" aria-label="Sidebar">
  <div class="h-full px-3 py-4 overflow-y-auto bg-white dark:bg-gray-800">
    <h1 class="my-5 text-xl ">Categories</h1>
    <form action="{{ route('showProducts') }}" method="post">
      @csrf
     <ul class="space-y-2 font-medium">
        <li class="flex items-center justify-start ml-3 hover:bg-gray-100 px-2 rounded-lg">
          <input type="radio" name="category" value="1" {{ $category == '1' ? 'checked' : '' }} onchange="this.form.submit()">
          <label for="shirts" class="mx-2 text-base flex items-center p-2 text-gray-900 rounded-lg">Shirts</label>
        </li>
        <li class="flex items-center justify-start ml-3 hover:bg-gray-100 px-2 rounded-lg">
          <input type="radio" name="category" value="2" {{ $category == '2' ? 'checked' : '' }} onchange="this.form.submit()">
          <label for="shirts" class="mx-2 text-base flex items-center p-2 text-gray-900 rounded-lg">Pants</label>
        </li>
        <li class="flex items-center justify-start ml-3 hover:bg-gray-100 px-2 rounded-lg">
          <input type="radio" name="category" value="3" {{ $category == '3' ? 'checked' : '' }} onchange="this.form.submit()">
          <label for="shirts" class="mx-2 text-base flex items-center p-2 text-gray-900 rounded-lg">Jackets</label>
        </li>
        <li class="flex items-center justify-start ml-3 hover:bg-gray-100 px-2 rounded-lg">
          <input type="radio" name="category" value="4" {{ $category == '4' ? 'checked' : '' }} onchange="this.form.submit()">
          <label for="shirts" class="mx-2 text-base flex items-center p-2 text-gray-900 rounded-lg">Shoes</label>
        </li>
        <li class="flex items-center justify-start ml-3 hover:bg-gray-100 px-2 rounded-lg">
          <input type="radio" name="category" value="5" {{ $category == '5' ? 'checked' : '' }} onchange="this.form.submit()">
          <label for="shirts" class="mx-2 text-base flex items-center p-2 text-gray-900 rounded-lg">Underwear</label>
        </li>
        <li class="flex items-center justify-start ml-3 hover:bg-gray-100 px-2 rounded-lg">
          <input type="radio" name="category" value="6" {{ $category == '6' ? 'checked' : '' }} onchange="this.form.submit()">
          <label for="shirts" class="mx-2 text-base flex items-center p-2 text-gray-900 rounded-lg">Accessories</label>
        </li>
     </ul>
    </form>
  </div>
</aside>

<div class="p-4 w-full">
  <div class="grid grid-cols-3">
      @foreach ($products as $product)
        <x-product :product="$product"></x-product>
      @endforeach
    </div>
</div>

</div>

<div class="flex items-center justify-center mb-4">
  {{ $products->links() }}
</div>
</x-layout>