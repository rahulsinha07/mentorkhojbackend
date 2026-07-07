<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class FlashDeal extends Model
{
    protected $table = 'flash_deals';
    protected $fillable = [
        'title',
        'start_date',
        'end_date',
        'deal_type',
        'status',
        'featured',
        'image',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'status' => 'integer',
        'featured' => 'integer',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function products(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(FlashDealProduct::class, 'flash_deal_id');
    }

    public function scopeActive($query)
    {
        return $query->where(['status' => 1])->whereDate('start_date', '<=', date('Y-m-d'))->whereDate('end_date', '>=', date('Y-m-d'));
    }

    public function getImageFullPathAttribute(): string
    {
        $image = $this->image ?? null;
        $path = asset('public/assets/admin/img/160x160/2.png');

        if (!is_null($image) && Storage::disk('public')->exists('offer/' . $image)) {
            $path = asset('storage/app/public/offer/' . $image);
        }
        return $path;
    }

    public function getIdentityImageFullPathAttribute()
    {
        $value = $this->image ?? [];
        $imageUrlArray = is_array($value) ? $value : json_decode($value, true);
        if (is_array($imageUrlArray)) {
            foreach ($imageUrlArray as $key => $item) {
                if (Storage::disk('public')->exists('product/' . $item)) {
                    $imageUrlArray[$key] = asset('storage/app/public/product/'. $item) ;
                } else {
                    $imageUrlArray[$key] = asset('public/assets/admin/img/160x160/2.png');
                }
            }
            return isset($imageUrlArray[0]) ? $imageUrlArray[0] : '';
        }
        return '';
    }

}
