<?php

namespace Database\Seeders;

use App\Models\categories;
use Illuminate\Database\Seeder;

class CategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $types = ['Shirts', 'Pants', 'Jackets', 'Shoes', 'Underwear', 'Accessories'];

        foreach ($types as $type) {
            categories::firstOrCreate(['type' => $type]);
        }
    }
}
