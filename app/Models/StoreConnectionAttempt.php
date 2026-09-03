<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreConnectionAttempt extends Model
{
    public const STATUSES = [
        'STARTED', 'AUTHORIZING', 'INSTALL_REQUIRED', 'INSTALLING',
        'AUTHORIZED', 'COMPLETED', 'FAILED', 'EXPIRED',
    ];

    protected $fillable = [
        'organisation_id',
        'state_token',
        'shop_domain',
        'status',
        'failure_reason',
        'store_id',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Normalized status for the frontend (waiting screen + JSON poll):
     * {status, installation, authorization, agency_connected, message}.
     * Never exposes Shopify tokens or raw session data.
     */
    public function connectionStatus(): array
    {
        if ($this->store) {
            $installation = $this->store->installation_status;
            $authorization = $this->store->authorization_status;

            $status = match (true) {
                $installation === 'UNINSTALLED' => 'UNINSTALLED',
                $installation === 'INSTALLED' && $authorization === 'AUTHORIZED' => 'CONNECTED',
                $installation === 'ERROR' => 'FAILED',
                $installation === 'INSTALLING' => 'INSTALLING',
                default => 'CHECKING',
            };

            return [
                'status' => $status,
                'installation' => $installation,
                'authorization' => $authorization,
                'agency_connected' => true,
                'message' => self::message($status),
                'shop_domain' => $this->shop_domain,
                'store_slug' => $this->store_id,
            ];
        }

        $map = [
            'STARTED' => 'CHECKING',
            'AUTHORIZING' => 'AUTHORIZING',
            'INSTALL_REQUIRED' => 'INSTALL_REQUIRED',
            'INSTALLING' => 'INSTALLING',
            'AUTHORIZED' => 'INSTALLING',
            'COMPLETED' => 'CONNECTED',
            'FAILED' => 'FAILED',
            'EXPIRED' => 'FAILED',
        ];
        $status = $map[$this->status] ?? 'CHECKING';

        return [
            'status' => $status,
            'installation' => $status === 'CONNECTED' ? 'INSTALLED' : 'NOT_INSTALLED',
            'authorization' => $status === 'CONNECTED' ? 'AUTHORIZED' : 'NOT_AUTHORIZED',
            'agency_connected' => false,
            'message' => $this->status === 'EXPIRED'
                ? 'This connection attempt expired. Please try again.'
                : ($this->failure_reason ?? self::message($status)),
            'shop_domain' => $this->shop_domain,
            'store_slug' => null,
        ];
    }

    public static function message(string $status): string
    {
        return match ($status) {
            'CHECKING' => 'Checking your BRIX connection...',
            'INSTALL_REQUIRED' => 'BRIX needs to be installed on this store.',
            'AUTHORIZING' => 'Waiting for Shopify authorization...',
            'INSTALLING' => 'Installing BRIX...',
            'CONNECTED' => 'Store connected successfully.',
            'FAILED' => "We couldn't connect this store.",
            'UNINSTALLED' => 'BRIX is no longer installed on this store.',
            default => '',
        };
    }
}
