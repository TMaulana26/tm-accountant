<?php

namespace App\Services\Accounting;

use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReceiptImageService
{
    /**
     * Compress an image binary using native PHP GD and store it to public storage.
     *
     * @param  string  $imageBytes  Raw binary data of the image
     * @param  int  $maxWidth  Maximum width in pixels (proportional resize)
     * @param  int  $quality  JPEG/WebP compression quality (1-100)
     * @return string|null Relative storage path (e.g. 'receipts/2026/08/receipt_xyz.jpg')
     */
    public function compressAndStore(string $imageBytes, int $maxWidth = 1200, int $quality = 75): ?string
    {
        if (empty($imageBytes)) {
            return null;
        }

        try {
            // 1. Create GD image resource from binary string
            $sourceImage = @imagecreatefromstring($imageBytes);

            if (! $sourceImage) {
                Log::warning('ReceiptImageService: Failed to parse image binary with GD.');

                return null;
            }

            // 2. Get original dimensions
            $origWidth = imagesx($sourceImage);
            $origHeight = imagesy($sourceImage);

            // 3. Calculate target dimensions if larger than maxWidth
            if ($origWidth > $maxWidth) {
                $targetWidth = $maxWidth;
                $targetHeight = (int) round(($origHeight / $origWidth) * $maxWidth);

                $resizedImage = imagecreatetruecolor($targetWidth, $targetHeight);

                // Preserve transparency for PNG/GIF if converted
                imagealphablending($resizedImage, false);
                imagesavealpha($resizedImage, true);

                imagecopyresampled(
                    $resizedImage,
                    $sourceImage,
                    0, 0, 0, 0,
                    $targetWidth,
                    $targetHeight,
                    $origWidth,
                    $origHeight
                );

                imagedestroy($sourceImage);
                $finalImage = $resizedImage;
            } else {
                $finalImage = $sourceImage;
            }

            // 4. Determine relative path & ensure directory exists
            $yearMonth = now()->format('Y/m');
            $dirPath = "receipts/{$yearMonth}";
            Storage::disk('public')->makeDirectory($dirPath);

            $filename = 'receipt_'.now()->format('Ymd_His').'_'.Str::random(6).'.jpg';
            $relativePath = "{$dirPath}/{$filename}";
            $fullDiskPath = Storage::disk('public')->path($relativePath);

            // 5. Compress and save JPEG
            imagejpeg($finalImage, $fullDiskPath, $quality);
            imagedestroy($finalImage);

            return $relativePath;
        } catch (Exception $e) {
            Log::error('ReceiptImageService compression error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }
}
