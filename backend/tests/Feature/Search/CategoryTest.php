<?php

use App\Models\Category;
use Illuminate\Testing\Fluent\AssertableJson;

use function Pest\Laravel\getJson;

it('lists all active categories', function () {
    Category::create(['name' => 'Adventure', 'slug' => 'adventure', 'is_active' => true, 'display_order' => 1]);
    Category::create(['name' => 'Culture', 'slug' => 'culture', 'is_active' => true, 'display_order' => 2]);
    Category::create(['name' => 'Hidden', 'slug' => 'hidden', 'is_active' => false, 'display_order' => 3]);

    getJson('/api/public/categories?locale=en')
        ->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json->has('data', 2)
            ->etc()
        );
});

it('returns tours for a valid category slug', function () {
    $category = Category::create(['name' => 'Adventure', 'slug' => 'adventure', 'is_active' => true, 'display_order' => 1]);

    getJson('/api/public/categories/adventure/tours?locale=en')
        ->assertOk()
        ->assertJson(fn (AssertableJson $json) => $json->has('data')
            ->has('meta')
            ->has('filters')
            ->etc()
        );
});

it('returns 404 for invalid category slug', function () {
    getJson('/api/public/categories/nonexistent/tours?locale=en')
        ->assertStatus(404)
        ->assertJson(['message' => 'Category not found.']);
});

it('paginates tours within a category', function () {
    $category = Category::create(['name' => 'Culture', 'slug' => 'culture', 'is_active' => true, 'display_order' => 1]);

    getJson('/api/public/categories/culture/tours?locale=en&page=1')
        ->assertOk()
        ->assertJsonPath('meta.current_page', 1);
});

it('supports sorting within category tours', function () {
    $category = Category::create(['name' => 'Food', 'slug' => 'food', 'is_active' => true, 'display_order' => 1]);

    getJson('/api/public/categories/food/tours?locale=en&sort=price_asc')
        ->assertOk();
});

it('validates locale parameter for category tours', function () {
    getJson('/api/public/categories/adventure/tours')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['locale']);
});
