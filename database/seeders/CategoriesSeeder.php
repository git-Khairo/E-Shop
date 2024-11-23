<?php

namespace Database\Seeders;

use App\Models\categories;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $categories = ['Shirts', 'Pants', 'Jackets', 'Shoes', 'Underwear', 'Accessories'];

        foreach ($categories as $category) {
            categories::firstOrCreate(['type' => $category]);
        }
    }
}
