<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Services\PwaManifestService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class PwaIconController extends Controller
{
    public function __invoke(int $size): Response
    {
        if (! in_array($size, [192, 512], true)) {
            abort(404);
        }

        return response($this->renderIcon($size), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    protected function renderIcon(int $size): string
    {
        $sourcePath = PwaManifestService::iconSourcePath($size);

        if ($sourcePath !== null) {
            $bytes = Storage::disk('public')->get($sourcePath);
            $image = @imagecreatefromstring($bytes);

            if ($image !== false) {
                $png = $this->resizeSquare($image, $size);
                imagedestroy($image);

                if ($png !== null) {
                    return $png;
                }
            }
        }

        if (is_file(PwaManifestService::fallbackIconPath())) {
            $png = $this->brandedPlaceholder($size);

            if ($png !== '') {
                return $png;
            }
        }

        return $this->brandedPlaceholder($size);
    }

    protected function resizeSquare(\GdImage $source, int $size): ?string
    {
        $srcWidth = imagesx($source);
        $srcHeight = imagesy($source);

        if ($srcWidth < 1 || $srcHeight < 1) {
            return null;
        }

        $scale = min($size / $srcWidth, $size / $srcHeight);
        $targetWidth = max(1, (int) round($srcWidth * $scale));
        $targetHeight = max(1, (int) round($srcHeight * $scale));

        $canvas = imagecreatetruecolor($size, $size);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        $transparent = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
        imagefill($canvas, 0, 0, $transparent);
        imagealphablending($canvas, true);

        $offsetX = (int) floor(($size - $targetWidth) / 2);
        $offsetY = (int) floor(($size - $targetHeight) / 2);

        imagecopyresampled(
            $canvas,
            $source,
            $offsetX,
            $offsetY,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $srcWidth,
            $srcHeight,
        );

        ob_start();
        imagepng($canvas, null, 8);
        $png = ob_get_clean() ?: null;
        imagedestroy($canvas);

        return $png;
    }

    protected function brandedPlaceholder(int $size): string
    {
        $canvas = imagecreatetruecolor($size, $size);
        $navy = imagecolorallocate($canvas, 16, 42, 67);
        imagefilledrectangle($canvas, 0, 0, $size, $size, $navy);

        $amber = imagecolorallocate($canvas, 245, 158, 11);
        $pad = (int) round($size * 0.12);
        imagefilledrectangle($canvas, $pad, $pad, $size - $pad, $size - $pad, $amber);

        $textColor = imagecolorallocate($canvas, 16, 42, 67);
        $text = PwaManifestService::brandInitials();
        $font = 5;
        $textWidth = imagefontwidth($font) * strlen($text);
        $textHeight = imagefontheight($font);
        imagestring(
            $canvas,
            $font,
            (int) (($size - $textWidth) / 2),
            (int) (($size - $textHeight) / 2),
            $text,
            $textColor,
        );

        ob_start();
        imagepng($canvas, null, 8);
        $png = ob_get_clean() ?: '';
        imagedestroy($canvas);

        return $png;
    }
}
