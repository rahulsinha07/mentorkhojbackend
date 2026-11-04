<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class ZohoSetting extends Model
{
    protected $table = 'zoho_settings';

    protected $fillable = [
        'client_id',
        'client_secret',
        'access_token',
        'refresh_token',
        'access_token_expires_at',
        'zoho_account_id',
        'zoho_presenter_zuid',
        'data_center',
        'status',
        'last_error',
        'connected_at',
        'connected_by_admin_id',
    ];

    protected $casts = [
        'client_secret' => 'encrypted',
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'access_token_expires_at' => 'datetime',
        'connected_at' => 'datetime',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1]);
    }

    public function isConfigured(): bool
    {
        return !empty($this->client_id) && !empty($this->client_secret);
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected' && !empty($this->refresh_token);
    }
}
