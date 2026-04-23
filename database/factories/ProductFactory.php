<?php

namespace Database\Factories;

use App\Models\categories;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = $this->faker->words(2, true);
        $slug = Str::slug($name).'-'.Str::lower(Str::random(4));

        return [
            'sku'           => strtoupper(Str::random(3)).'-'.strtoupper(Str::random(6)),
            'name'          => Str::title($name),
            'slug'          => $slug,
            'description'   => $this->faker->sentence(12),
            'price'         => $this->faker->randomFloat(2, 20, 500),
            'image'         => "https://picsum.photos/seed/{$slug}/800/800",
            'stock'         => $this->faker->numberBetween(10, 100),
            'stock_version' => 0,
            'is_active'     => true,
            'categories_id' => categories::inRandomOrder()->value('id') ?? categories::factory(),
            'amount'        => 0,
        ];
    }
}
