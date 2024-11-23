<?php

namespace Database\Factories;

use App\Models\categories;
use App\Models\product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    protected $model = product::class;

    public function definition()
    {

        $productNames = [
            1 => ['Classic White Shirt', 'Flannel Shirt', 'Denim Shirt', 'Polo Shirt', 'Oxford Shirt'],
            2 => ['Jeans', 'Chinos', 'Khakis', 'Sweatpants', 'Cargo Pants'],
            3 => ['Leather Jacket', 'Denim Jacket', 'Bomber Jacket', 'Blazer', 'Windbreaker'],
            4 => ['Sneakers', 'Boots', 'Loafers', 'Sandals', 'Running Shoes'],
            5 => ['Boxers', 'Briefs', 'Trunks', 'Bikini', 'Thermal Underwear'],
            6 => ['Sunglasses', 'Watch', 'Belt', 'Scarf', 'Hat']
        ];
        // Fetch the categories from the database
        $categories = categories::all();

        // Handle the case where no categories are found
        if ($categories->isEmpty()) {
            throw new \Exception("No categories found. Please ensure categories are seeded first.");
        }

        // Select a random category and corresponding product name
        $category = $categories->random();
        $categoryName = $category->id;
        $productName = $this->faker->randomElement($productNames[$categoryName]);

        return [
            'name' => $productName,
            'categories_id' =>  $category->id,
            'price' => $this->faker->randomFloat(2, 10, 200),
            'amount' => $this->faker->numberBetween(1, 100),
            'image' => $this->faker->imageUrl(),
        ];
    }
}
