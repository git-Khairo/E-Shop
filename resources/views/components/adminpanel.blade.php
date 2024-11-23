<x-layout>
    <div class="flex h-screen rounded-lg">
        <!-- Sidebar -->
        <aside class="w-64 bg-teal-600 text-white h-full rounded-md">
            <div class="p-4">
                <h2 class="text-xl font-bold mb-4">Admin Dashboard</h2>
                <nav>
                    <ul>
                        <li><a href="{{route('product.create')}}" class="block p-4 hover:bg-gray-800 rounded-md">Add a new product</a></li>
                        <li><a href="#" class="block p-4 hover:bg-gray-800 rounded-md">Orders</a></li>
                        <li><a href="#" class="block p-4 hover:bg-gray-800 rounded-md">Users</a></li>
                    </ul>
                </nav>
            </div>
        </aside>
        <!-- Main Content -->
        <div class="flex-1 p-4 overflow-auto">
         {{$slot}}
        </div>
    </div>
</x-layout>
