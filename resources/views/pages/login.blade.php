<x-layout>

<div class="mx-auto max-w-screen-xl px-4 py-16 sm:px-6 lg:px-8">
    <div class="mx-auto max-w-lg text-center">
      <h1 class="text-2xl font-bold sm:text-3xl">Welcome Back!</h1>
  
      <p class="mt-4 text-gray-500">
        Lorem ipsum dolor sit amet consectetur adipisicing elit. Et libero nulla eaque error neque
        ipsa culpa autem, at itaque nostrum!
      </p>
    </div>
  
    <form action="{{ route('login') }}" class="mx-auto mb-0 mt-8 max-w-md space-y-4" method="post">
      @csrf
      <div>
        <label for="email" class="sr-only">Email</label>
  
        <div class="relative">
          <input
            type="text"
            class="w-full rounded-lg border-gray-200 p-4 pe-12 text-sm shadow-sm"
            placeholder="Enter email"
            name="email"
          />
          @error('email')
                <p class="error text-red-600">{{ $message }}</p>
                @enderror
        </div>
      </div>
  
      <div>
        <label for="password" class="sr-only">Password</label>
  
        <div class="relative">
          <input
            type="password"
            class="w-full rounded-lg border-gray-200 p-4 pe-12 text-sm shadow-sm"
            placeholder="Enter password"
            name="password"
          />
          @error('password')
                <p class="error text-red-600">{{ $message }}</p>
                @enderror
        </div>
      </div>

      @error('failed')
        <p class="error text-red-600">{{ $message }}</p>
      @enderror

      <div class="flex items-center justify-center">
        <button
          type="submit"
          class="inline-block rounded-lg bg-teal-600 px-12 py-3 text-sm font-medium text-white hover:bg-teal-700"
        >
          Login
        </button>
      </div>
    </form>
  </div>
</x-layout>