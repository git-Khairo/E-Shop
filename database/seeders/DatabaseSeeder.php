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
            ['type' => 'shirts'],
            ['type' => 'pants'],
            ['type' => 'jacket'],
            ['type' => 'shoes'],
            ['type' => 'underwear'],
            ['type' => 'accessories'],
        ]);
 

        DB::table('products')->insert([
            ['name' => 'Black T-Shirt', 'price' => 69.99, 'image' => 'B-Tee.jpg', 'amount' => 50, 'categories_id' => 1],
            ['name' => 'White T-Shirt', 'price' => 59.99, 'image' => 'W-Tee.jpg', 'amount' => 50, 'categories_id' => 1],
            ['name' => 'Gray T-Shirt', 'price' => 49.99, 'image' => 'G-Tee.jpg', 'amount' => 50, 'categories_id' => 1],
            ['name' => 'Black Pants', 'price' => 49.99, 'image' => 'B-Pants.webp', 'amount' => 50, 'categories_id' => 2],
            ['name' => 'White Pants', 'price' => 54.99, 'image' => 'W-Pants.webp', 'amount' => 50, 'categories_id' => 2],
            ['name' => 'Gray Pants', 'price' => 44.99, 'image' => 'G-Pants.jpg', 'amount' => 50, 'categories_id' => 2],
            ['name' => 'Black Jacket', 'price' => 89.99, 'image' => 'B-Jacket.webp', 'amount' => 50, 'categories_id' => 3],
            ['name' => 'White Jacket', 'price' => 79.99, 'image' => 'W-Jacket.jpeg', 'amount' => 50, 'categories_id' => 3],
            ['name' => 'Gray Jacket', 'price' => 74.99, 'image' => 'G-Jacket.jpg', 'amount' => 50, 'categories_id' => 3],
            ['name' => 'Black Shoes', 'price' => 109.99, 'image' => 'B-Shoes.webp', 'amount' => 50, 'categories_id' => 4],
            ['name' => 'White Shoes', 'price' => 99.99, 'image' => 'W-Shoes.jpg', 'amount' => 50, 'categories_id' => 4],
            ['name' => 'Gray Shoes', 'price' => 94.99, 'image' => 'G-Shoes.webp', 'amount' => 50, 'categories_id' => 4],
            ['name' => 'Black Underwear', 'price' => 24.99, 'image' => 'B-Under.avif', 'amount' => 50, 'categories_id' => 5],
            ['name' => 'White Underwear', 'price' => 19.99, 'image' => 'W-Under.webp', 'amount' => 50, 'categories_id' => 5],
            ['name' => 'Gray Underwear', 'price' => 14.99, 'image' => 'G-Under.jpeg', 'amount' => 50, 'categories_id' => 5],
            ['name' => 'New York Hat', 'price' => 39.99, 'image' => 'hat.avif', 'amount' => 50, 'categories_id' => 6],
            ['name' => 'RayBan Glasses', 'price' => 79.99, 'image' => 'glasses.webp', 'amount' => 50, 'categories_id' => 6],
            ['name' => 'Boss Bracelet', 'price' => 29.99, 'image' => 'bracelet.jpg', 'amount' => 50, 'categories_id' => 6],
        ]);
    }
}
