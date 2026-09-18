<?php

namespace App\Models\Partners;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * `activity_logs` — the audit trail for the store connection flow (and
 * reused by anything else in this app that needs one; there is no
 * separate audit_logs table).
 *
 * `user_id` references admin_users, which has no relationship to this
 * app's own logged-in User — so it is always left null here. The acting
 * dashboard user is instead recorded by email inside `metadata` for
 * traceability. Never write tokens/secrets into metadata.
 */
class ActivityLog extends Model
{
    protected $table = 'activity_logs';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'agency_id',
        'store_id',
        'action',
        'metadata',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Best-effort audit write. Failures are reported, not thrown — a
     * database hiccup must never break the store connection flow itself.
     */
    public static function record(
        string $action,
        ?int $agencyId,
        ?int $storeId = null,
        array $metadata = [],
        ?Request $request = null,
    ): void {
        try {
            static::create([
                'agency_id' => $agencyId,
                'store_id' => $storeId,
                'action' => $action,
                'metadata' => $metadata,
                'ip_address' => $request?->ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
