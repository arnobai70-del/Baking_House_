<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The storefront serves a copy of the prototype's weight-pricing engine for live previews.
 * Both copies must stay identical so the preview never disagrees with the tested engine.
 */
class PricingEngineParityTest extends TestCase
{
    public function test_storefront_engine_matches_the_tested_prototype_engine(): void
    {
        $prototype = dirname(__DIR__, 3).'/prototype/assets/js/weight-pricing.js';
        $storefront = dirname(__DIR__, 2).'/public/js/weight-pricing.js';

        if (! is_file($prototype)) {
            $this->markTestSkipped('Prototype directory not present in this checkout.');
        }

        $normalise = fn (string $path) => str_replace("\r\n", "\n", (string) file_get_contents($path));

        $this->assertSame($normalise($prototype), $normalise($storefront), 'Copy prototype/assets/js/weight-pricing.js to platform/public/js/.');
    }
}
