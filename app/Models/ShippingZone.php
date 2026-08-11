<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

#[Fillable([
    'name',
    'is_active',
])]
class ShippingZone extends Model
{
    /**
     * Casts array.
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get locations associated with this shipping zone.
     */
    public function locations(): HasMany
    {
        return $this->hasMany(ShippingZoneLocation::class, 'shipping_zone_id');
    }

    /**
     * Get methods associated with this shipping zone.
     */
    public function methods(): HasMany
    {
        return $this->hasMany(ShippingMethod::class, 'shipping_zone_id');
    }

    /**
     * Clear cached shipping zones.
     */
    public static function clearCache(): void
    {
        Cache::forget('active_shipping_zones');
    }

    /**
     * Find matching shipping zone based on priority fallback logic.
     * Priority: Upazila > District > Division > Country
     */
    public static function findMatchingZone(string $country, ?string $division, ?string $district, ?string $upazila): ?self
    {
        $zones = Cache::rememberForever('active_shipping_zones', function () {
            return self::with('locations', 'methods.weightRates')
                ->where('is_active', true)
                ->get();
        });

        $matchedZone = null;
        $bestScore = 0; // 1 = country, 2 = division, 3 = district, 4 = upazila

        foreach ($zones as $zone) {
            if (!is_object($zone) || !isset($zone->locations)) {
                self::clearCache();
                continue;
            }
            foreach ($zone->locations as $loc) {
                // Country comparison
                if (strtolower($loc->country_code) !== strtolower($country)) {
                    continue;
                }

                // 1. Upazila match (exact upazila, district, division, country)
                if ($upazila && $loc->upazila && strtolower($loc->upazila) === strtolower($upazila) &&
                    $district && $loc->district && strtolower($loc->district) === strtolower($district) &&
                    $division && $loc->division && strtolower($loc->division) === strtolower($division)) {
                    return $zone; // Most specific match immediately returns
                }

                // 2. District match (exact district, division, country, upazila is null)
                if ($district && $loc->district && strtolower($loc->district) === strtolower($district) &&
                    $division && $loc->division && strtolower($loc->division) === strtolower($division) &&
                    ! $loc->upazila) {
                    if ($bestScore < 3) {
                        $matchedZone = $zone;
                        $bestScore = 3;
                    }
                }

                // 3. Division match (exact division, country, district/upazila are null)
                if ($division && $loc->division && strtolower($loc->division) === strtolower($division) &&
                    ! $loc->district && ! $loc->upazila) {
                    if ($bestScore < 2) {
                        $matchedZone = $zone;
                        $bestScore = 2;
                    }
                }

                // 4. Country match (country only)
                if (! $loc->division && ! $loc->district && ! $loc->upazila) {
                    if ($bestScore < 1) {
                        $matchedZone = $zone;
                        $bestScore = 1;
                    }
                }
            }
        }

        if (!$matchedZone && !empty($zones)) {
            self::clearCache();
            $freshZones = self::with('locations', 'methods.weightRates')
                ->where('is_active', true)
                ->get();

            foreach ($freshZones as $zone) {
                if (!is_object($zone) || !isset($zone->locations)) {
                    continue;
                }
                foreach ($zone->locations as $loc) {
                    if (strtolower($loc->country_code) !== strtolower($country)) {
                        continue;
                    }
                    if (!$loc->division && !$loc->district && !$loc->upazila) {
                        return $zone;
                    }
                }
            }
        }

        return $matchedZone;
    }
}
