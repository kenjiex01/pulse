<?php

namespace App\Support;

use Illuminate\Support\Str;

class CompanyDocumentMemoDompdfPreparer
{
    /**
     * Writes memo images to disk before PDF HTML is built so Dompdf never parses huge data URLs.
     *
     * @param  array{
     *     form: mixed,
     *     elements: list<array<string, mixed>>,
     *     preview_values: array<string, mixed>
     * }  $preview
     * @return array{
     *     preview: array<string, mixed>,
     *     chroot: string,
     *     cleanup: string
     * }
     */
    public function materializePreviewImages(array $preview): array
    {
        $directory = storage_path('app/temp/memo-pdf/'.Str::uuid());
        mkdir($directory, 0755, true);

        $index = 0;
        $elements = $preview['elements'];

        foreach ($elements as &$element) {
            if (($element['type'] ?? '') !== 'image') {
                continue;
            }

            $settings = is_array($element['settings'] ?? null) ? $element['settings'] : [];
            $dataUrl = trim((string) ($settings['image_data_url'] ?? ''));
            if ($dataUrl === '') {
                continue;
            }

            $index++;
            $filename = 'image-'.$index.'.png';
            $opacityPercent = max(0, min(100, (int) ($settings['image_opacity'] ?? 100)));
            $rotateDegrees = (int) ($settings['image_rotate'] ?? 0);
            $boxWidth = max(40, (int) ($settings['image_width'] ?? 240));
            $boxHeight = max(40, (int) ($settings['image_height'] ?? 160));
            $this->writeOptimizedImage(
                $dataUrl,
                $directory.'/'.$filename,
                $opacityPercent,
                $rotateDegrees,
                $boxWidth,
                $boxHeight,
            );
            $settings['image_file'] = $filename;
            $settings['image_opacity_baked'] = true;
            unset($settings['image_data_url']);
            $element['settings'] = $settings;
        }
        unset($element);

        $previewValues = $preview['preview_values'];
        foreach ($previewValues as &$value) {
            if (! is_array($value)) {
                continue;
            }

            $dataUrl = trim((string) ($value['dataUrl'] ?? ''));
            if ($dataUrl === '' || ! str_starts_with($dataUrl, 'data:image/')) {
                continue;
            }

            $index++;
            $filename = 'image-'.$index.'.png';
            $this->writeOptimizedImage($dataUrl, $directory.'/'.$filename);
            $value['imageFile'] = $filename;
            unset($value['dataUrl']);
        }
        unset($value);

        return [
            'preview' => array_merge($preview, [
                'elements' => $elements,
                'preview_values' => $previewValues,
            ]),
            'chroot' => $directory,
            'cleanup' => $directory,
        ];
    }

    /**
     * @return array{html: string, chroot: string, cleanup: string}
     */
    public function prepare(string $html): array
    {
        $directory = storage_path('app/temp/memo-pdf/'.Str::uuid());
        mkdir($directory, 0755, true);

        $index = 0;
        $prepared = preg_replace_callback(
            '/src="(data:image\/[^;]+;base64,[^"]+)"/',
            function (array $matches) use ($directory, &$index): string {
                $index++;
                $filename = 'image-'.$index.'.png';
                $absolutePath = $directory.'/'.$filename;
                $this->writeOptimizedImage($matches[1], $absolutePath);

                return 'src="'.$filename.'"';
            },
            $html,
        );

        return [
            'html' => is_string($prepared) ? $prepared : $html,
            'chroot' => $directory,
            'cleanup' => $directory,
        ];
    }

    public function cleanup(string $directory): void
    {
        if (! is_dir($directory) || ! str_starts_with($directory, storage_path('app/temp/memo-pdf/'))) {
            return;
        }

        foreach (glob($directory.'/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        rmdir($directory);
    }

    private function writeOptimizedImage(
        string $dataUrl,
        string $absolutePath,
        ?int $opacityPercent = null,
        int $rotateDegrees = 0,
        ?int $boxWidth = null,
        ?int $boxHeight = null,
    ): void {
        if (! preg_match('#^data:image/(png|jpeg|webp);base64,(.+)$#', $dataUrl, $matches)) {
            throw new \InvalidArgumentException('Invalid image data URL for memo PDF.');
        }

        $binary = base64_decode($matches[2], true);
        if ($binary === false || $binary === '') {
            throw new \InvalidArgumentException('Invalid image encoding for memo PDF.');
        }

        if (function_exists('imagecreatefromstring')) {
            $image = @imagecreatefromstring($binary);
            if ($image !== false) {
                if ($rotateDegrees % 360 !== 0) {
                    $image = $this->rotateImage($image, $rotateDegrees);
                }

                if ($boxWidth !== null && $boxHeight !== null) {
                    $image = $this->fitContain($image, $boxWidth, $boxHeight);
                }

                if ($opacityPercent !== null && $opacityPercent < 100) {
                    $image = $this->applyOpacityPercent($image, $opacityPercent);
                }

                imagealphablending($image, false);
                imagesavealpha($image, true);
                imagepng($image, $absolutePath);

                return;
            }
        }

        file_put_contents($absolutePath, $binary);
    }

    /**
     * CSS rotate() is clockwise; GD imagerotate() is counter-clockwise.
     */
    private function rotateImage(\GdImage $image, int $cssDegrees): \GdImage
    {
        $cssDegrees = ((int) $cssDegrees % 360 + 360) % 360;
        if ($cssDegrees === 0) {
            return $image;
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        $rotated = imagerotate($image, -$cssDegrees, $transparent);
        if ($rotated === false) {
            return $image;
        }

        imagealphablending($rotated, false);
        imagesavealpha($rotated, true);

        return $rotated;
    }

    /**
     * Browser preview uses object-fit:contain. Dompdf stretches — bake contain into the PNG.
     */
    private function fitContain(\GdImage $image, int $boxWidth, int $boxHeight): \GdImage
    {
        $srcWidth = imagesx($image);
        $srcHeight = imagesy($image);
        if ($srcWidth < 1 || $srcHeight < 1) {
            return $image;
        }

        $scale = min($boxWidth / $srcWidth, $boxHeight / $srcHeight);
        $drawWidth = max(1, (int) round($srcWidth * $scale));
        $drawHeight = max(1, (int) round($srcHeight * $scale));
        $offsetX = (int) floor(($boxWidth - $drawWidth) / 2);
        $offsetY = (int) floor(($boxHeight - $drawHeight) / 2);

        $canvas = imagecreatetruecolor($boxWidth, $boxHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $boxWidth, $boxHeight, $transparent);
        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $image, $offsetX, $offsetY, 0, 0, $drawWidth, $drawHeight, $srcWidth, $srcHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        return $canvas;
    }

    /**
     * Dompdf ignores CSS opacity on wrapper divs — bake transparency into the PNG instead.
     */
    private function applyOpacityPercent(\GdImage $image, int $opacityPercent): \GdImage
    {
        $opacityPercent = max(0, min(100, $opacityPercent));
        if ($opacityPercent >= 100) {
            return $image;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $factor = $opacityPercent / 100;

        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $width, $height, $transparent);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgba = imagecolorat($image, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;
                $red = ($rgba >> 16) & 0xFF;
                $green = ($rgba >> 8) & 0xFF;
                $blue = $rgba & 0xFF;
                $newAlpha = (int) round(127 - ((127 - $alpha) * $factor));
                $newAlpha = max(0, min(127, $newAlpha));
                $color = imagecolorallocatealpha($canvas, $red, $green, $blue, $newAlpha);
                imagesetpixel($canvas, $x, $y, $color);
            }
        }

        return $canvas;
    }
}
