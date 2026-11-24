<?php

namespace App\Services\Cakes;

use App\Models\CakeDesign;
use App\Models\CakeUpload;
use App\Models\User;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Private image files for custom cakes, all on the private "local" disk:
 *  - customer photos for edible prints (re-encoded JPEG, metadata removed, at most MAX_EDGE px), and
 *  - the 3D snapshot and the top-design picture saved with a design for the order and baker's job sheet.
 * Nothing here is ever written to the public disk.
 */
class CakeImageStore
{
    public const MIN_EDGE = 300;

    public const MAX_EDGE = 4000;

    public const MAX_DATA_URL_BYTES = 3_000_000;

    /**
     * @throws ValidationException
     */
    public function storeUpload(UploadedFile $file, ?User $user, ?string $cartToken): CakeUpload
    {
        $bytes = (string) file_get_contents($file->getRealPath());
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw ValidationException::withMessages(['photo' => __('Upload a JPEG, PNG or WebP photo.')]);
        }
        $source = @imagecreatefromstring($bytes);
        if (! $source instanceof GdImage) {
            throw ValidationException::withMessages(['photo' => __('This file is not a readable photo.')]);
        }
        [$width, $height] = [imagesx($source), imagesy($source)];
        if (min($width, $height) < self::MIN_EDGE) {
            throw ValidationException::withMessages(['photo' => __('This photo is too small to print well (at least :px pixels on each side).', ['px' => self::MIN_EDGE])]);
        }

        $scale = min(1, self::MAX_EDGE / max($width, $height));
        $outW = (int) round($width * $scale);
        $outH = (int) round($height * $scale);
        $canvas = imagecreatetruecolor($outW, $outH);
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $outW, $outH, $width, $height);
        $jpeg = $this->encodeJpeg($canvas, 90);

        $upload = new CakeUpload;
        $upload->forceFill([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user?->id,
            'owner_token_hash' => $user === null && $cartToken !== null ? hash('sha256', $cartToken) : null,
            'path' => 'cakes/uploads/'.Str::uuid().'.jpg',
            'width' => $outW, 'height' => $outH, 'bytes' => strlen($jpeg), 'sha256' => hash('sha256', $jpeg),
            'rights_confirmed_at' => now(),
        ]);
        Storage::disk('local')->put($upload->path, $jpeg);
        $upload->save();

        return $upload;
    }

    /**
     * Save a browser-made PNG / JPEG (3D snapshot or top design) for a design, re-encoded on the server.
     * Invalid or oversized data is ignored: these pictures help staff but never block an order.
     */
    public function storeDesignImage(CakeDesign $design, string $kind, ?string $dataUrl): void
    {
        if ($dataUrl === null || strlen($dataUrl) > self::MAX_DATA_URL_BYTES * 4 / 3 + 64
            || ! preg_match('#^data:image/(png|jpeg);base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $match)) {
            return;
        }
        $bytes = base64_decode($match[2], true);
        $image = $bytes === false ? false : @imagecreatefromstring($bytes);
        if (! $image instanceof GdImage || imagesx($image) > 2048 || imagesy($image) > 2048 || imagesx($image) < 64) {
            return;
        }
        imagesavealpha($image, true);
        ob_start();
        imagepng($image, null, 6);
        $png = (string) ob_get_clean();

        $column = $kind === 'snapshot' ? 'snapshot_path' : 'top_path';
        $old = $design->{$column};
        $path = 'cakes/designs/'.$design->uuid.'-'.$kind.'-'.Str::random(6).'.png';
        Storage::disk('local')->put($path, $png);
        $design->forceFill([$column => $path])->save();
        if ($old && $old !== $path) {
            Storage::disk('local')->delete($old);
        }
    }

    private function encodeJpeg(GdImage $image, int $quality): string
    {
        ob_start();
        imagejpeg($image, null, $quality);

        return (string) ob_get_clean();
    }
}
