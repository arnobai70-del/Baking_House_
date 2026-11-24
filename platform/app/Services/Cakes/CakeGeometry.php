<?php

namespace App\Services\Cakes;

/**
 * Approximate cake sizes for the preview, the print-quality check and the baker's job sheet. Plain arithmetic,
 * mirrored in public/js/cake/geometry.js so the browser and the server agree.
 *
 * A one-tier 1 kg cake is about 20 cm across; area grows with weight (diameter ∝ √weight). On tiered cakes each
 * tier above is smaller (TIER_SCALE) and the weight is shared by tier area.
 */
final class CakeGeometry
{
    public const CM_PER_SQRT_KG = 20.0;

    /** @var list<float> diameter of each tier relative to the bottom tier */
    public const TIER_SCALE = [1.0, 0.72, 0.52];

    /** Print-quality thresholds in dots per inch of the printed photo. */
    public const DPI_GOOD = 150;

    public const DPI_FAIR = 100;

    public static function baseDiameterCm(int $grams, int $tiers = 1): float
    {
        $tiers = max(1, min(3, $tiers));
        $areaShare = array_sum(array_map(fn (float $s) => $s * $s, array_slice(self::TIER_SCALE, 0, $tiers)));
        $bottomGrams = max(1, $grams) / $areaShare;

        return round(self::CM_PER_SQRT_KG * sqrt($bottomGrams / 1000), 1);
    }

    /**
     * Width of the top surface (the top tier), where the hand-made design and photos go.
     */
    public static function topDiameterCm(int $grams, int $tiers = 1): float
    {
        $tiers = max(1, min(3, $tiers));

        return round(self::baseDiameterCm($grams, $tiers) * self::TIER_SCALE[$tiers - 1], 1);
    }

    /**
     * Effective resolution of a photo printed $printWidthCm wide from $sourcePixels pixels across.
     *
     * @return array{dpi: int, level: string}
     */
    public static function printQuality(float $sourcePixels, float $printWidthCm): array
    {
        $dpi = $printWidthCm <= 0 ? 0 : (int) floor($sourcePixels / ($printWidthCm / 2.54));

        return ['dpi' => $dpi, 'level' => $dpi >= self::DPI_GOOD ? 'good' : ($dpi >= self::DPI_FAIR ? 'fair' : 'low')];
    }
}
