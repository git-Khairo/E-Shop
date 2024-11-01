<x-layout>

    <h1 class="title">Most Popular Categories</h1>

    <div class="grid grid-cols-2 gap-6">
    @foreach($categories as $category)
        <div class="card">
                <a href="{{ route('categories.show', $category->id) }}" class="font-bold text-xl text-green-500 text-center">
                    <button> {{ $category->type }}</button>
                </a>
        </div>
    @endforeach
    </div>
    <div class="pagination">
        {{ $categories->links() }}
    </div>
</x-layout>
