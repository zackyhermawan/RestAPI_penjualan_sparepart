<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'sku',
        'name',
        'variant',
        'description',
        'short_description',
        'price',
        'stock',
        'is_active',
        'is_featured',
        'image'
    ];

    protected $appends = ['image_url'];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function getImageUrlAttribute()
    {
        if ($this->image) {
            return asset('storage/' . $this->image);
        }
        return asset('images/no-image.jpg'); // fallback opsional
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    // Fungsi bantu stok otomatis
    public static function reduceStock($productId, $qty)
    {
        $product = self::find($productId);
        if ($product) {
            $product->decrement('stock', $qty);
        }
    }

    public static function increaseStock($productId, $qty)
    {
        $product = self::find($productId);
        if ($product) {
            $product->increment('stock', $qty);
        }
    }
}
