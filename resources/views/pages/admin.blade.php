<x-adminpanel>
    <div class="mx-auto max-w-screen-xl px-4 2xl:px-0">
        <div class="mx-auto max-w-5xl">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white sm:text-2xl">All orders</h2>
            <div class="mt-6 flow-root sm:mt-8">
                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach ($Orders as $Order)
                <div class="flex flex-wrap items-center gap-y-4 py-6">
                    <dl class="w-1/2 sm:w-1/4 lg:w-auto lg:flex-1">
                        <dt class="text-base font-medium text-gray-500 dark:text-gray-400">Order ID:</dt>
                        <dd class="mt-1.5 text-base font-semibold text-gray-900 dark:text-white">
                        <a href="" class="hover:underline">#{{ $Order->id }}</a>
                        </dd>
                    </dl>

                    <dl class="w-1/2 sm:w-1/4 lg:w-auto lg:flex-1">
                        <dt class="text-base font-medium text-gray-500 dark:text-gray-400">User ID:</dt>
                        <dd class="mt-1.5 text-base font-semibold text-gray-900 dark:text-white">{{ $Order->user_id }}</dd>
                    </dl>
        
                    <dl class="w-1/2 sm:w-1/4 lg:w-auto lg:flex-1">
                        <dt class="text-base font-medium text-gray-500 dark:text-gray-400">Date:</dt>
                        <dd class="mt-1.5 text-base font-semibold text-gray-900 dark:text-white">{{ $Order->created_at->format('d-m-Y') }}</dd>
                    </dl>
        
                    <dl class="w-1/2 sm:w-1/4 lg:w-auto lg:flex-1">
                        <dt class="text-base font-medium text-gray-500 dark:text-gray-400">Price:</dt>
                        <dd class="mt-1.5 text-base font-semibold text-gray-900 dark:text-white">${{ $Order->price }}</dd>
                    </dl>
        
                    <dl class="w-1/2 sm:w-1/4 lg:w-auto lg:flex-1">
                        <dt class="text-base font-medium text-gray-500 dark:text-gray-400">Status:</dt>
                        <dd class="me-2 mt-1.5 inline-flex items-center rounded bg-primary-100 px-2.5 py-0.5 text-base font-medium text-primary-800 dark:bg-primary-900 dark:text-primary-300">
                        <svg class="me-1 h-3 w-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.5 4h-13m13 16h-13M8 20v-3.333a2 2 0 0 1 .4-1.2L10 12.6a1 1 0 0 0 0-1.2L8.4 8.533a2 2 0 0 1-.4-1.2V4h8v3.333a2 2 0 0 1-.4 1.2L13.957 11.4a1 1 0 0 0 0 1.2l1.643 2.867a2 2 0 0 1 .4 1.2V20H8Z" />
                        </svg>
                        {{ $Order->status }}
                        </dd>
                    </dl>
        
                    @if ($Order->status == 'pending')
                        <div class="w-full grid sm:grid-cols-2 lg:flex lg:w-64 lg:items-center lg:justify-end gap-4">
                            <form action="{{ route('Delete', ['orderId' => $Order->id]) }}" method="post">
                                @csrf
                                @method('DELETE')
                                <button type="button" onclick="this.form.submit()" class="w-full rounded-lg border border-red-700 px-3 py-2 text-center text-sm font-medium text-red-700 hover:bg-red-700 hover:text-white focus:outline-none focus:ring-4 focus:ring-red-300 dark:border-red-500 dark:text-red-500 dark:hover:bg-red-600 dark:hover:text-white dark:focus:ring-red-900 lg:w-auto">Cancel order</button>
                            </form>
                            <form action="{{ route('Confirm', ['orderId' => $Order->id]) }}" method="post">
                                @csrf
                                <button type="button" onclick="this.form.submit()" class="w-full rounded-lg border border-green-500 px-3 py-2 text-center text-sm font-medium text-green-500 hover:bg-green-600 hover:text-white focus:outline-none focus:ring-4 focus:ring-red-300 dark:border-red-500 dark:text-red-500 dark:hover:bg-red-600 dark:hover:text-white dark:focus:ring-red-900 lg:w-auto">Confirm order</button>
                            </form>
                        </div>
                    @endif
                </div>
                @endforeach
                </div>
            </div>
        </div>
    </div>
</x-adminpanel>
