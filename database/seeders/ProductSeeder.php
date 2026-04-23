<?php

namespace Database\Seeders;

use App\Models\categories;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Seed ~30 realistic products across the six core categories.
     * Each product gets a stable SKU, a slug, a stock buffer, and a
     * deterministic Unsplash-style image so the UI always looks polished.
     */
    public function run(): void
    {
        $byCategory = [
            'Shirts' => [
                ['name' => 'Oxford Button-Down Shirt',  'price' => 68.00, 'desc' => 'Classic oxford cloth, tailored fit, mother-of-pearl buttons.'],
                ['name' => 'Slim-Fit Cotton Poplin',    'price' => 72.00, 'desc' => 'Crisp cotton poplin with a subtle sheen. Perfect for the office.'],
                ['name' => 'Linen Camp Collar Shirt',   'price' => 89.00, 'desc' => 'Breezy European linen with a vintage camp collar.'],
                ['name' => 'Merino Polo',               'price' => 110.00,'desc' => 'Extra-fine Merino wool polo; temperature regulating.'],
                ['name' => 'Japanese Flannel',          'price' => 128.00,'desc' => 'Heavyweight brushed flannel from a Kyoto mill.'],
            ],
            'Pants' => [
                ['name' => 'Selvedge Denim Jeans',      'price' => 148.00,'desc' => '13oz Japanese selvedge, straight leg, raw finish.'],
                ['name' => 'Stretch Chino',             'price' => 92.00, 'desc' => 'Four-way stretch fabric with a clean silhouette.'],
                ['name' => 'Wool Trousers',             'price' => 175.00,'desc' => 'Italian wool twill trousers, pleated front.'],
                ['name' => 'Tech Cargo Pant',           'price' => 138.00,'desc' => 'Water-resistant ripstop with reinforced pockets.'],
                ['name' => 'Jogger Sweatpant',          'price' => 78.00, 'desc' => 'Heavy French terry joggers with elastic cuffs.'],
            ],
            'Jackets' => [
                ['name' => 'Italian Leather Jacket',    'price' => 598.00,'desc' => 'Full-grain Italian lambskin, satin lined.'],
                ['name' => 'Waxed Field Jacket',        'price' => 248.00,'desc' => 'British-milled waxed cotton with brass hardware.'],
                ['name' => 'Quilted Bomber',            'price' => 215.00,'desc' => 'Recycled down insulation, ribbed cuffs and hem.'],
                ['name' => 'Wool Topcoat',              'price' => 420.00,'desc' => 'Double-face Italian wool. Minimalist silhouette.'],
                ['name' => 'Shell Parka',               'price' => 340.00,'desc' => '3-layer waterproof shell with storm hood.'],
            ],
            'Shoes' => [
                ['name' => 'Goodyear-Welted Derby',     'price' => 295.00,'desc' => 'Calf leather upper, cork footbed, leather sole.'],
                ['name' => 'Italian Sneaker',           'price' => 185.00,'desc' => 'Handcrafted in Le Marche; minimalist court silhouette.'],
                ['name' => 'Chelsea Boot',              'price' => 275.00,'desc' => 'Burnished leather with elastic side gores.'],
                ['name' => 'Hiking Boot',               'price' => 220.00,'desc' => 'Gore-Tex lined, Vibram sole, welted construction.'],
                ['name' => 'Canvas Low-Top',            'price' => 65.00, 'desc' => 'Heavyweight canvas with vulcanized rubber sole.'],
            ],
            'Underwear' => [
                ['name' => 'Supima Cotton Boxer Brief', 'price' => 28.00, 'desc' => 'Premium long-staple cotton; pack of one.'],
                ['name' => 'Merino Wool Base Layer',    'price' => 84.00, 'desc' => 'Lightweight Merino for cold-weather layering.'],
                ['name' => 'Performance Trunk',         'price' => 32.00, 'desc' => 'Moisture-wicking microfiber; flat-lock seams.'],
                ['name' => 'Silk-Blend Undershirt',     'price' => 58.00, 'desc' => 'Silk-cotton blend for an invisible fit under dress shirts.'],
                ['name' => 'Pima Lounge Set',           'price' => 115.00,'desc' => 'Two-piece Pima cotton set; ideal for travel.'],
            ],
            'Accessories' => [
                ['name' => 'Aviator Sunglasses',        'price' => 165.00,'desc' => 'Italian acetate frame with polarized lenses.'],
                ['name' => 'Leather Bifold Wallet',     'price' => 98.00, 'desc' => 'Vegetable-tanned leather that patinas beautifully.'],
                ['name' => 'Silk Tie',                  'price' => 85.00, 'desc' => 'Woven in Como, Italy. Classic 3.25" blade.'],
                ['name' => 'Wool Scarf',                'price' => 72.00, 'desc' => '100% lambswool, hand-fringed edges.'],
                ['name' => 'Leather Belt',              'price' => 88.00, 'desc' => 'Full-grain leather with solid-brass buckle.'],
            ],
        ];

        foreach ($byCategory as $categoryName => $products) {
            $category = categories::where('type', $categoryName)->first();
            if (! $category) continue;

            foreach ($products as $p) {
                $slug = Str::slug($p['name']);
                $sku  = strtoupper(Str::substr($categoryName, 0, 3)).'-'.Str::upper(Str::random(6));

                // Deterministic, clean-looking placeholder image per product.
                $image = "https://picsum.photos/seed/{$slug}/800/800";

                Product::updateOrCreate(
                    ['slug' => $slug],
                    [
                        'sku'           => $sku,
                        'name'          => $p['name'],
                        'description'   => $p['desc'],
                        'price'         => $p['price'],
                        'image'         => $image,
                        'stock'         => random_int(15, 60),
                        'stock_version' => 0,
                        'is_active'     => true,
                        'categories_id' => $category->id,
                        'amount'        => 0,
                    ]
                );
            }
        }
    }
}
