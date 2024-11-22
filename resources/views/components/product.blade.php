@props(['product'])

<div class="group my-10 flex w-full max-w-xs flex-col overflow-hidden border border-gray-100 bg-white rounded-lg">
    <a class="relative flex h-60 overflow-hidden m-3 rounded-md" href="#">
      <img class="absolute top-0 right-0 object-cover" src="{{ asset('storage/images/' . $product->image) }}" alt="product image" />
    </a>
    <div class="mt-4 px-5 pb-5">
      <a href="#">
        <h5 class="text-xl tracking-tight text-slate-900">{{ $product->name }}</h5>
      </a>
      <div class="mt-2 mb-5 flex items-center justify-between">
        <p>
          <span class="text-3xl font-bold text-slate-900">{{ $product->price }}</span>
          <span class="text-lg font-bold text-slate-900 line-through">{{ $product->price + 20 }}</span>
        </p>
      </div>
      @auth
      <form action="{{ route('AddtoCart') }}" method="post">
        @csrf
        <input type="hidden" name="ProductId" value="{{ $product->id }}">
        <button class="flex items-center justify-center w-full bg-teal-600 px-2 py-3 rounded-lg text-sm text-white transition hover:bg-teal-700">
          <svg xmlns="http://www.w3.org/2000/svg" class="mr-2 h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
            <path d="M3 1a1 1 0 000 2h1.22l.305 1.222a.997.997 0 00.01.042l1.358 5.43-.893.892C3.74 11.846 4.632 14 6.414 14H15a1 1 0 000-2H6.414l1-1H14a1 1 0 00.894-.553l3-6A1 1 0 0017 3H6.28l-.31-1.243A1 1 0 005 1H3zM16 16.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zM6.5 18a1.5 1.5 0 100-3 1.5 1.5 0 000 3z" />
          </svg>
          Add to cart
        </button>
      </form>
      @endauth
    </div>
  </div>