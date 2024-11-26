<x-dashboardLayout>
        <form action="#" class="mx-auto max-w-screen-xl px-4 2xl:px-0">
          <div class="mx-auto max-w-3xl">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white sm:text-2xl">Order Details</h2>
      
            <div class="mt-6 space-y-4 border-b border-t border-gray-200 py-8 dark:border-gray-700 sm:mt-8">
              <h4 class="text-lg font-semibold text-gray-900 dark:text-white">User & Delivery information</h4>
      
              <dl>
                <dt class="text-base font-medium text-gray-900 dark:text-white">ID : {{ $orderDetails['id'] }}</dt>
                <dd class="mt-1 text-base font-normal text-gray-500 dark:text-gray-400">HAMAK / +963 951 456 811, Damascus, Syria, 3454, Airport Street</dd>
              </dl>
            </div>
      
            @php
                $subtotal = 0;
            @endphp
            <div class="mt-6 sm:mt-8">
              <div class="relative overflow-x-auto border-b border-gray-200 dark:border-gray-800">
                <table class="w-full text-left font-medium text-gray-900 dark:text-white md:table-fixed">
                  <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                    @foreach ($orderDetails['products'] as $product)
                    <tr>
                        <td class="whitespace-nowrap py-4 md:w-[384px]">
                          <div class="flex items-center gap-4">
                            <a href="#" class="flex items-center aspect-square w-10 h-10 shrink-0">
                              <img class="h-auto w-full max-h-full dark:hidden" src="{{ asset('storage/images/' . $product['image']) }}" alt="imac image" />
                            </a>
                            <a href="#" class="hover:underline">{{ $product['name'] }}”</a>
                          </div>
                        </td>
        
                        <td class="p-4 text-base font-normal text-gray-900 dark:text-white">${{ $product['price'] }}</td>
        
                        <td class="p-4 text-base font-normal text-gray-900 dark:text-white">x{{ $product['quantity'] }}</td>

                        <td class="p-4 text-right text-base font-bold text-gray-900 dark:text-white">${{ $product['price'] * $product['quantity'] }}</td>
                      </tr>
                      @php
                          $subtotal += $product['price'] * $product['quantity'];
                      @endphp
                    @endforeach
                  </tbody>
                </table>
              </div>
      
              <div class="mt-4 space-y-6">
                <h4 class="text-xl font-semibold text-gray-900 dark:text-white">Order summary</h4>
      
                <div class="space-y-4">
                  <div class="space-y-2">
                    <dl class="flex items-center justify-between gap-4">
                      <dt class="text-gray-500 dark:text-gray-400">SubTotal</dt>
                      <dd class="text-base font-medium text-gray-900 dark:text-white">${{ $subtotal }}</dd>
                    </dl>
      
                    <dl class="flex items-center justify-between gap-4">
                      <dt class="text-gray-500 dark:text-gray-400">Shipping</dt>
                      <dd class="text-base font-medium text-gray-900 dark:text-white">$5.00</dd>
                    </dl>
      
                    <dl class="flex items-center justify-between gap-4">
                      <dt class="text-gray-500 dark:text-gray-400">Tax</dt>
                      <dd class="text-base font-medium text-gray-900 dark:text-white">${{ round($subtotal * 2 /100, 2) }}</dd>
                    </dl>
                  </div>
      
                  <dl class="flex items-center justify-between gap-4 border-t border-gray-200 pt-2 dark:border-gray-700">
                    <dt class="text-lg font-bold text-gray-900 dark:text-white">Total</dt>
                    <dd class="text-lg font-bold text-gray-900 dark:text-white">${{ $orderDetails['price'] }}</dd>
                  </dl>
                </div>
              </div>
            </div>
          </div>
        </form>
</x-dashboardLayout>