<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class CartController extends Controller
{
    public function addToCart(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'qty' => 'required|integer|min:1',
        ]);

        $product = Product::find($request->product_id);
        if ($product->stock < $request->qty) {
            return response()->json(['error' => 'Stok tidak cukup'], 400);
        }

        $cart = Session::get('cart', []);
        $cart[$request->product_id] = [
            'product_id' => $request->product_id,
            'name' => $product->name,
            'price' => $product->price,
            'qty' => ($cart[$request->product_id]['qty'] ?? 0) + $request->qty,
            'sub_total' => (($cart[$request->product_id]['qty'] ?? 0) + $request->qty) * $product->price,
        ];
        Session::put('cart', $cart);

        return response()->json(['message' => 'Produk ditambahkan ke keranjang', 'cart' => $cart]);
    }

    public function viewCart()
    {
        $cart = Session::get('cart', []);
        return response()->json($cart);
    }

    public function updateCart(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'qty' => 'required|integer|min:0',
        ]);

        $cart = Session::get('cart', []);
        if ($request->qty == 0) {
            unset($cart[$request->product_id]);
        } else {
            $product = Product::find($request->product_id);
            if ($product->stock < $request->qty) {
                return response()->json(['error' => 'Stok tidak cukup'], 400);
            }
            $cart[$request->product_id]['qty'] = $request->qty;
            $cart[$request->product_id]['sub_total'] = $request->qty * $product->price;
        }
        Session::put('cart', $cart);

        return response()->json(['message' => 'Keranjang diperbarui', 'cart' => $cart]);
    }

    public function removeFromCart(Request $request)
    {
        $request->validate(['product_id' => 'required|exists:products,id']);
        $cart = Session::get('cart', []);
        unset($cart[$request->product_id]);
        Session::put('cart', $cart);

        return response()->json(['message' => 'Produk dihapus dari keranjang', 'cart' => $cart]);
    }
}