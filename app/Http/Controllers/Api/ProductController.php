<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 12);
        $search = $request->input('search'); 
        $categoryId = $request->input('filter_id');

        $query = Product::with('category')
            ->where('is_active', true);

        $query->when($categoryId, function ($q, $categoryId) {
            return $q->where('category_id', $categoryId);
        });

        $query->when($search, function ($q, $search) {
            return $q->where('name', 'like', '%' . $search . '%');
            
        });
        
        $products = $query->paginate($perPage);
        return response()->json($products);
    }

    public function adminIndex(Request $request)
    {
        $perPage = $request->input('per_page', 5);
        $search = $request->input('search'); 
        $categoryId = $request->input('filter_id');

        $query = Product::with('category');

        $query->when($categoryId, function ($q, $categoryId) {
            return $q->where('category_id', $categoryId);
        });

        $query->when($search, function ($q, $search) {
            return $q->where('name', 'like', '%' . $search . '%');
            
        });
        
        $products = $query->paginate($perPage);
        return response()->json($products);
    }

    public function featured()
    {
        $products = Product::with('category')->where('is_active', true)->where('is_featured', true)->take(4)->get();
        return response()->json($products);
    }

    public function toggleIsActive($id)
    {
        $product = Product::findOrFail($id);
        $product->is_active = !$product->is_active;
        $product->save();
        return response()->json([
            'message' => 'Status aktif produk berhasil diubah',
            'data' => $product,
        ]);
    }

    public function togggleIsFeatured($id)
    {
        $product = Product::findOrFail($id);
        $product->is_featured = !$product->is_featured;
        $product->save();
        return response()->json([
            'message' => 'Status fitur produk berhasil diubah',
            'data' => $product,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'name' => 'required|string|max:255',
            'variant' => 'required|string|max:255',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048', 
        ]);

        $data = $request->only(['category_id', 'name', 'variant', 'description', 'short_description', 'price', 'stock']);

        $prefix = strtoupper(substr($request->name, 0, 3)); // 3 huruf pertama nama produk
        $timestamp = now()->format('YmdHis'); // contoh: 20251106114530
        $data['sku'] = "{$prefix}-{$timestamp}";
        
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('products', 'public');
            $data['image'] = $path;
        }

        $product = Product::create($data);

        return response()->json([
            'message' => 'Produk berhasil ditambahkan',
            'data' => $product
        ], 201);
    }

    public function show($id)
    {
        $product = Product::with('category')->findOrFail($id);

        // Ambil produk lain dengan kategori yang sama (kecuali dirinya sendiri)
        $relatedProducts = Product::with('category')
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->take(4)
            ->get();

        // Tambahkan URL gambar dan produk terkait
        $product->image_url = $product->image ? asset('storage/products/' . $product->image) : null;

        foreach ($relatedProducts as $related) {
            $related->image_url = $related->image ? asset('storage/products/' . $related->image) : null;
        }

        return response()->json([
            'product' => $product,
            'related_products' => $relatedProducts,
        ]);
    }


    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        if (!$product) {
            return response()->json(['message' => 'Produk tidak ditemukan'], 404);
        }

        $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'name' => 'required|string|max:255',
            'variant' => 'required|string|max:255',
            'description' => 'nullable|string',
            'short_description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'image' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        $data = $request->only(['category_id', 'name', 'variant', 'description', 'short_description', 'price', 'stock', 'is_active', 'is_featured']);

        if ($request->hasFile('image')) {
            if ($product->image && Storage::disk('public')->exists($product->image)) {
                Storage::disk('public')->delete($product->image);
            }
            $path = $request->file('image')->store('products', 'public');
            $data['image'] = $path;
        }

        $product->update($data);

        return response()->json([
            'message' => 'Produk berhasil diperbarui',
            'data' => $product,
        ]);
    }

    public function destroy($id)
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json(['message' => 'Produk tidak ditemukan'], 404);
        }

        if ($product->image && Storage::disk('public')->exists($product->image)) {
            Storage::disk('public')->delete($product->image);
        }

        $product->delete();

        return response()->json(['message' => 'Produk dihapus']);
    }
}
