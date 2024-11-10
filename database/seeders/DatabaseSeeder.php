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
    }
}
