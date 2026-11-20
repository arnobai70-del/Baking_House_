<?php

namespace App\Console\Commands;

use App\Services\Media\ProductImageService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('media:withdraw-expired')]
#[Description('Take published product photos whose licence has expired off the website')]
class WithdrawExpiredImageLicences extends Command
{
    public function handle(ProductImageService $images): int
    {
        $count = $images->withdrawExpiredLicences();
        $this->info("Withdrew {$count} photo(s) with an expired licence.");

        return self::SUCCESS;
    }
}
