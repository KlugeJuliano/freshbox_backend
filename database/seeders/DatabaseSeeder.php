<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Company;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::create([
            'name' => 'Hortifruti do João',
            'slug' => 'hortifruti-joao',
            'plan' => 'pedidos',
            'is_active' => true,
        ]);

        User::create([
            'company_id' => $company->id,
            'name' => 'João Silva',
            'email' => 'admin@hortifruti.test',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        Store::create([
            'company_id' => $company->id,
            'name' => 'Hortifruti do João - Centro',
            'whatsapp' => '5541999999999',
            'delivery_fee' => 5,
            'min_order_value' => 30,
            'is_active' => true,
            'accepts_delivery' => true,
            'accepts_pickup' => true,
            'business_hours' => [
                'mon' => ['open' => '08:00', 'close' => '18:00'],
                'tue' => ['open' => '08:00', 'close' => '18:00'],
                'wed' => ['open' => '08:00', 'close' => '18:00'],
                'thu' => ['open' => '08:00', 'close' => '18:00'],
                'fri' => ['open' => '08:00', 'close' => '18:00'],
                'sat' => ['open' => '08:00', 'close' => '14:00'],
                'sun' => null,
            ],
        ]);

        $categories = [];

        foreach (['Frutas', 'Verduras', 'Legumes', 'Orgânicos', 'Promoções'] as $index => $name) {
            $categories[$name] = Category::create([
                'company_id' => $company->id,
                'name' => $name,
                'slug' => Str::slug($name),
                'sort_order' => $index,
                'is_active' => true,
            ]);
        }

        $products = [
            ['Banana Nanica', 'Frutas', 'kg', 4.99, null, true],
            ['Maçã Fuji', 'Frutas', 'kg', 8.99, 6.99, true],
            ['Alface Americana', 'Verduras', 'un', 3.49, null, false],
            ['Tomate Italiano', 'Legumes', 'kg', 7.99, null, false],
            ['Morango Bandeja', 'Frutas', 'un', 9.99, 7.99, true],
        ];

        foreach ($products as [$name, $categoryName, $unit, $price, $promo, $featured]) {
            Product::create([
                'company_id' => $company->id,
                'category_id' => $categories[$categoryName]->id,
                'name' => $name,
                'slug' => Str::slug($name),
                'unit' => $unit,
                'price' => $price,
                'promo_price' => $promo,
                'promo_ends_at' => $promo ? now()->addWeek() : null,
                'is_active' => true,
                'is_featured' => $featured,
                'is_available' => true,
                'sort_order' => 0,
            ]);
        }
    }
}
