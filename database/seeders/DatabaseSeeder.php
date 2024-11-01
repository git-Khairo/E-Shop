<?php

namespace Database\Seeders;

use App\Models\categories;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run()
    {
        DB::table('categories')->insert([
            ['type' => 'dress'],
            ['type' => 'jacket'],
            ['type' => 'underwear'],
        ]);

        $shoesId = Categories::where('type', 'dress')->first()->id;
        $tShirtId = Categories::where('type', 'jacket')->first()->id;
        $shortsId = Categories::where('type', 'underwear')->first()->id;

        DB::table('products')->insert([
            ['name' => 'black dress', 'price' => 49.99, 'amount' => 100, 'categories_id' => $shoesId],
            ['name' => 'green jacket', 'price' => 19.99, 'amount' => 200, 'categories_id' => $tShirtId],
            ['name' => 'string', 'price' => 29.99, 'amount' => 150, 'categories_id' => $shortsId],
        ]);
    }
}
