<?php

namespace App;

use App\Model\CustomerAddress;
use App\Model\FavoriteProduct;
use App\Model\Mentor\Mentor;
use App\Model\Mentor\MentorBooking;
use App\Model\Order;
use App\Model\SearchedKeywordUser;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name','f_name', 'l_name', 'phone', 'email', 'password', 'loyalty_point', 'wallet_balance', 'referral_code', 'referred_by',
        'account_type', 'last_login_as', 'last_login_at', 'login_medium',
    ];

    protected $appends = [
        'has_mentor_profile',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'is_phone_verified' => 'integer',
        'loyalty_point' => 'float',
        'wallet_balance' => 'float',
    ];

    public function orders(){
        return $this->hasMany(Order::class,'user_id');
    }

    public function mentorProfile()
    {
        // Mentor profile exists when this user has created a mentor page.
        return $this->hasOne(Mentor::class, 'user_id');
    }

    public function menteeBookings()
    {
        return $this->hasMany(MentorBooking::class, 'mentee_user_id');
    }

    public function getHasMentorProfileAttribute(): bool
    {
        if ($this->relationLoaded('mentorProfile')) {
            return $this->mentorProfile !== null;
        }

        return $this->mentorProfile()->exists();
    }

    public function visited_products(): HasMany
    {
        return $this->hasMany(VisitedProduct::class, 'user_id', 'id');
    }

    public function addresses(){
        return $this->hasMany(CustomerAddress::class,'user_id');
    }

    public function favorite_products(){
        return $this->hasMany(FavoriteProduct::class,'user_id');
    }

    public function getImageFullPathAttribute(): string
    {
        $image = $this->image ?? null;
        $path = asset('public/assets/admin/img/160x160/2.png');

        if (!is_null($image) && Storage::disk('public')->exists('profile/' . $image)) {
            $path = asset('storage/app/public/profile/' . $image);
        }
        return $path;
    }

    static function total_order_amount($customer_id)
    {
        $customer = User::where(['id' => $customer_id])->first();
        if (!$customer) {
            return 0;
        }

        return \App\CentralLogics\CustomerBookingStats::forUser((int) $customer_id)['amount'];
    }

    public function search_volume()
    {
        return $this->hasMany(SearchedKeywordUser::class, 'user_id', 'id');
    }
}
