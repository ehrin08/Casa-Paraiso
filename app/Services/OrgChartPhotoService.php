<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class OrgChartPhotoService
{
    private const DISK = 'public';

    private const DIRECTORY = 'org-chart';

    private const SIZE = 200;

    private const QUALITY = 85;

    /**
     * Store an uploaded photo after resizing it to a 200×200 WebP thumbnail.
     *
     * @return string The relative storage path (e.g., "org-chart/owner.webp").
     */
    public function store(UploadedFile $file, string $slot): string
    {
        $path = self::DIRECTORY.'/'.$slot.'.webp';

        $webpData = $this->resizeToWebp($file->getRealPath());

        Storage::disk(self::DISK)->put($path, $webpData);

        return $path;
    }

    /** Delete a previously stored org chart photo. */
    public function delete(string $path): void
    {
        Storage::disk(self::DISK)->delete($path);
    }

    /** Check whether a stored photo exists. */
    public function exists(string $path): bool
    {
        return Storage::disk(self::DISK)->exists($path);
    }

    /** Get the public URL for a stored photo path. */
    public function url(string $path): string
    {
        return Storage::disk(self::DISK)->url($path);
    }

    /**
     * Resize an image to a 200×200 center-cropped WebP.
     *
     * @return string Raw WebP binary data.
     */
    private function resizeToWebp(string $sourcePath): string
    {
        $info = getimagesize($sourcePath);

        if ($info === false) {
            throw new \RuntimeException('Unable to read image dimensions.');
        }

        $mime = $info['mime'];
        $source = match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($sourcePath),
            'image/png' => imagecreatefrompng($sourcePath),
            'image/webp' => imagecreatefromwebp($sourcePath),
            default => throw new \RuntimeException("Unsupported image type: {$mime}"),
        };

        if ($source === false) {
            throw new \RuntimeException('Failed to create image resource.');
        }

        $origWidth = imagesx($source);
        $origHeight = imagesy($source);

        // Center-crop to square
        $cropSize = min($origWidth, $origHeight);
        $cropX = (int) (($origWidth - $cropSize) / 2);
        $cropY = (int) (($origHeight - $cropSize) / 2);

        $thumb = imagecreatetruecolor(self::SIZE, self::SIZE);

        if ($thumb === false) {
            imagedestroy($source);
            throw new \RuntimeException('Failed to create thumbnail canvas.');
        }

        // Preserve alpha for PNG sources
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);

        imagecopyresampled(
            $thumb, $source,
            0, 0,
            $cropX, $cropY,
            self::SIZE, self::SIZE,
            $cropSize, $cropSize
        );

        imagedestroy($source);

        ob_start();
        imagewebp($thumb, null, self::QUALITY);
        $webpData = ob_get_clean();
        imagedestroy($thumb);

        if ($webpData === false || $webpData === '') {
            throw new \RuntimeException('Failed to encode image as WebP.');
        }

        return $webpData;
    }
}
