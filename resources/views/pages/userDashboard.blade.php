<x-dashboardLayout>
        <div class="mx-auto max-w-screen-xl px-4 2xl:px-0">
          <div class="mx-auto max-w-5xl">
            <div class="gap-4 sm:flex sm:items-center sm:justify-between">
              <h2 class="text-xl font-semibold text-gray-900 dark:text-white sm:text-2xl">My orders</h2>
      
              <div class="mt-6 gap-4 space-y-4 sm:mt-0 sm:flex sm:items-center sm:justify-end sm:space-y-0">
                <div>
                  <form action="{{ route('option') }}" method="post">
                    @csrf
                    <label for="order-type" class="sr-only mb-2 block text-sm font-medium text-gray-900 dark:text-white">Select order type</label>
                    <select onchange="this.form.submit()" id="order-type" name="orderType" class="block w-full min-w-[8rem] rounded-lg border border-gray-300 bg-gray-50 p-2.5 text-sm text-gray-900 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder:text-gray-400 dark:focus:border-primary-500 dark:focus:ring-primary-500">
                      <option value="all" {{ $option == 'all' ? 'selected' : '' }}>All orders</option>
                      <option value="pending" {{ $option == 'pending' ? 'selected' : '' }}>Pending</option>
                      <option value="confirmed" {{ $option == 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                    </select>
                  </form>
                </div>
              </div>
            </div>
      
            <div class="mt-6 flow-root sm:mt-8">
              <div class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach ($userOrders as $userOrder)
                <div class="flex flex-wrap items-center gap-y-4 py-6">
                    <dl class="w-1/2 sm:w-1/4 lg:w-auto lg:flex-1">
                      <dt class="text-base font-medium text-gray-500 dark:text-gray-400">Order ID:</dt>
                      <dd class="mt-1.5 text-base font-semibold text-gray-900 dark:text-white">
                        <a href="{{ route('orderDetails', ['orderId' => $userOrder->id]) }}" class="hover:underline">#{{ $userOrder->id }}</a>
                      </dd>
                    </dl>
        
                    <dl class="w-1/2 sm:w-1/4 lg:w-auto lg:flex-1">
                      <dt class="text-base font-medium text-gray-500 dark:text-gray-400">Date:</dt>
                      <dd class="mt-1.5 text-base font-semibold text-gray-900 dark:text-white">{{ $userOrder->created_at->format('d-m-Y') }}</dd>
                    </dl>
        
                    <dl class="w-1/2 sm:w-1/4 lg:w-auto lg:flex-1">
                      <dt class="text-base font-medium text-gray-500 dark:text-gray-400">Price:</dt>
                      <dd class="mt-1.5 text-base font-semibold text-gray-900 dark:text-white">${{ $userOrder->price }}</dd>
                    </dl>
        
                    <dl class="w-1/2 sm:w-1/4 lg:w-auto lg:flex-1">
                      <dt class="text-base font-medium text-gray-500 dark:text-gray-400">Status:</dt>
                      <dd class="me-2 mt-1.5 inline-flex items-center rounded bg-primary-100 px-2.5 py-0.5 text-base font-medium text-primary-800 dark:bg-primary-900 dark:text-primary-300">
                        <svg class="me-1 h-3 w-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                          <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.5 4h-13m13 16h-13M8 20v-3.333a2 2 0 0 1 .4-1.2L10 12.6a1 1 0 0 0 0-1.2L8.4 8.533a2 2 0 0 1-.4-1.2V4h8v3.333a2 2 0 0 1-.4 1.2L13.957 11.4a1 1 0 0 0 0 1.2l1.643 2.867a2 2 0 0 1 .4 1.2V20H8Z" />
                        </svg>
                        {{ $userOrder->status }}
                      </dd>
                    </dl>
        
                    @if ($userOrder->status == 'pending')
                    <div class="w-full grid sm:grid-cols-2 lg:flex lg:w-64 lg:items-center lg:justify-end gap-4">
                    <form action="{{ route('deleteOrder', ['orderId' => $userOrder->id]) }}" method="post">
                        @csrf
                        @method('DELETE')
                        <button type="button" onclick="this.form.submit()" class="w-full rounded-lg border border-red-700 px-3 py-2 text-center text-sm font-medium text-red-700 hover:bg-red-700 hover:text-white focus:outline-none focus:ring-4 focus:ring-red-300 dark:border-red-500 dark:text-red-500 dark:hover:bg-red-600 dark:hover:text-white dark:focus:ring-red-900 lg:w-auto">Cancel order</button>
                    </form>
                      <a href="{{ route('orderDetails', ['orderId' => $userOrder->id]) }}" class="w-full inline-flex justify-center rounded-lg  border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-900 hover:bg-gray-100 hover:text-primary-700 focus:z-10 focus:outline-none focus:ring-4 focus:ring-gray-100 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white dark:focus:ring-gray-700 lg:w-auto">View details</a>
                    </div>
                    @endif
                  </div>
                @endforeach
              </div>
            </div>
          </div>
        </div>
</x-dashboardLayout>