<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $search = $request->input('search');
        $query =  Category::query();

        $query->when($search, function($q, $search){
            return $q->where('name', 'like', '%' . $search . '%');
        });

        $category = $query->paginate($perPage);
        return response()->json($category);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:categories,id'
        ],[
            'name.unique' => 'Nama kategori tidak boleh sama.'
        ]);

        $category = Category::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
        ]);

        return response()->json(['message' => 'Kategori berhasil ditambahkan', 'data' => $category], 201);
    }

    // ✅ Detail kategori
    public function show($id)
    {
        $category = Category::find($id);
        if (!$category) {
            return response()->json(['message' => 'Kategori tidak ditemukan'], 404);
        }
        return response()->json($category);
    }

    // ✅ Update kategori
    public function update(Request $request, $id)
    {
        $category = Category::find($id);
        if (!$category) {
            return response()->json(['message' => 'Kategori tidak ditemukan'], 404);
        }

        $request->validate(['name' => 'required|string|max:100']);
        $category->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
        ]);

        return response()->json(['message' => 'Kategori diperbarui', 'data' => $category]);
    }

    // ✅ Hapus kategori
    public function destroy($id)
    {
        $category = Category::find($id);
        if (!$category) {
            return response()->json(['message' => 'Kategori tidak ditemukan'], 404);
        }
        $category->delete();

        return response()->json(['message' => 'Kategori dihapus']);
    }
}
