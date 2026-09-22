<?php

namespace App\Services\Referral\Commission;

use App\Models\Organisation;

/**
 * Which kinds of BRIX revenue earn commission for an agency. It is
 * agency-level configuration (organisation_settings.commission_revenue_source)
 * — never set by, or overridable from, a referral link. A missing settings
 * row means the default, 'both'.
 */
final class CommissionSourceMode
{
    public const SUBSCRIPTION = 'subscription';

    public const USAGE = 'usage';

    public const BOTH = 'both';

    public const DEFAULT = self::BOTH;

    public const ALL = [self::SUBSCRIPTION, self::USAGE, self::BOTH];

    /** The raw configured value for an agency (normalized, not validated). */
    public static function forAgency(int $agencyId): string
    {
        $organisation = Organisation::where('brix_agency_id', $agencyId)->with('settings')->first();

        return self::normalize($organisation?->settings?->commission_revenue_source);
    }

    public static function normalize(?string $value): string
    {
        $value = strtolower(trim((string) $value));

        return $value === '' ? self::DEFAULT : $value;
    }

    public static function isValid(string $mode): bool
    {
        return in_array($mode, self::ALL, true);
    }

    /** Whether the given mode makes the given revenue type (subscription|usage) commissionable. */
    public static function includes(string $mode, string $revenueType): bool
    {
        return $mode === self::BOTH || $mode === $revenueType;
    }
}
