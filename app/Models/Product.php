<?php

namespace App\Models;

// use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    // use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'product_type',
        'stock',
        'price',
        'special_price',
        'status',
        'is_favorites'
    ];

    public function images()
    {
        // Product has many images
        return $this->hasMany(ProductImage::class);
    }
}
