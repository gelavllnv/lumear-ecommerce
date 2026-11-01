<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Seller;
use App\Models\SellerStoreCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SellerStoreCategoryController extends Controller
{
    public function index(Request $request)
    {
        $seller = $this->seller($request);
        $categories = $seller->storeCategories()->with(['children', 'products'])->whereNull('parent_id')->get();
        $products = $seller->products()->orderBy('name')->get(['id', 'name']);
        return view('seller.store-categories.index', compact('categories', 'products'));
    }

    public function store(Request $request)
    {
        $seller = $this->seller($request);
        $data = $this->validated($request, $seller);
        SellerStoreCategory::create([
            'seller_id' => $seller->id,
            'parent_id' => $data['parent_id'] ?? null,
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($seller, $data['name']),
            'is_visible' => true,
            'position' => ((int) $seller->storeCategories()->max('position')) + 1,
        ]);
        return back()->with('status', 'Store category created.');
    }

    public function update(Request $request, SellerStoreCategory $storeCategory)
    {
        $seller = $this->seller($request);
        $this->owns($seller, $storeCategory);
        $data = $this->validated($request, $seller, $storeCategory);
        DB::transaction(function () use ($request, $seller, $storeCategory, $data): void {
            $storeCategory->update([
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($seller, $data['name'], $storeCategory->id),
                'parent_id' => $data['parent_id'] ?? null,
                'is_visible' => $request->boolean('is_visible'),
                'position' => $request->integer('position', $storeCategory->position),
            ]);
            foreach ($request->input('product_ids', []) as $productId) {
                $product = Product::where('seller_id', $seller->id)->findOrFail($productId);
                if (! $product->storeCategories()->whereKey($storeCategory->id)->exists() && $product->storeCategories()->count() >= 3) {
                    throw ValidationException::withMessages(['product_ids' => "{$product->name} already belongs to the maximum of 3 store categories."]);
                }
                $product->storeCategories()->syncWithoutDetaching([$storeCategory->id]);
            }
        });
        return back()->with('status', 'Store category updated.');
    }

    public function destroy(Request $request, SellerStoreCategory $storeCategory)
    {
        $this->owns($this->seller($request), $storeCategory);
        $count = $storeCategory->products()->count() + $storeCategory->children()->withCount('products')->get()->sum('products_count');
        $storeCategory->delete();
        return back()->with('status', "Store category deleted. {$count} affected product(s) are now uncategorized in this store.");
    }

    private function validated(Request $request, Seller $seller, ?SellerStoreCategory $ignore = null): array
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:1', 'max:50'],
            'parent_id' => ['nullable', 'integer'],
            'position' => ['nullable', 'integer', 'min:0'],
            'is_visible' => ['nullable', 'boolean'],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer'],
        ]);
        $duplicate = $seller->storeCategories()->whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])->when($ignore, fn ($query) => $query->whereKeyNot($ignore->id))->exists();
        if ($duplicate) throw ValidationException::withMessages(['name' => 'A store category with that name already exists.']);
        if (filled($data['parent_id'] ?? null)) {
            $parent = $seller->storeCategories()->findOrFail($data['parent_id']);
            if ($parent->parent_id) throw ValidationException::withMessages(['parent_id' => 'Store categories can have at most two levels.']);
            if ($ignore && $parent->id === $ignore->id) throw ValidationException::withMessages(['parent_id' => 'A category cannot be its own parent.']);
        }
        return $data;
    }

    private function uniqueSlug(Seller $seller, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'category'; $slug = $base; $number = 2;
        while ($seller->storeCategories()->where('slug', $slug)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) $slug = $base.'-'.$number++;
        return $slug;
    }

    private function seller(Request $request): Seller { abort_unless($request->user()?->isSeller(), 403); return $request->user()->seller ?? abort(403); }
    private function owns(Seller $seller, SellerStoreCategory $category): void { abort_unless($category->seller_id === $seller->id, 403); }
}
