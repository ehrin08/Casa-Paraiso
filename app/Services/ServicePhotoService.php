<?php

namespace App\Services;

use App\Models\Service;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ServicePhotoService
{
    private const SAMPLE_IMAGES = [
        'gaia-touch' => 'images/spa/casa-massage-720.webp',
        'tethys-flow' => 'images/spa/casa-hot-compress-720.webp',
        'hestia-warmth' => 'images/spa/casa-hot-stone-720.webp',
        'aurora-breeze' => 'images/spa/casa-treatment-room-800.webp',
    ];

    public function url(?string $path, string $slug): string
    {
        if ($path && $this->isManagedPath($path) && Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->url($path);
        }

        return asset(self::SAMPLE_IMAGES[$slug] ?? 'images/spa/casa-towel-detail-720.webp');
    }

    /** @param array<string, mixed> $data */
    public function save(Service $service, array $data, ?UploadedFile $photo, bool $removePhoto = false): Service
    {
        $newPath = $photo ? $this->store($photo) : null;

        try {
            return DB::transaction(function () use ($service, $data, $newPath, $removePhoto): Service {
                $record = $service->exists
                    ? Service::query()->lockForUpdate()->findOrFail($service->getKey())
                    : $service;
                $oldPath = $record->photo_path;
                $record->fill($data);

                if ($newPath !== null) {
                    $record->photo_path = $newPath;
                } elseif ($removePhoto) {
                    $record->photo_path = null;
                }

                if (! $record->save()) {
                    throw new RuntimeException('Unable to save service details.');
                }

                if ($oldPath && $oldPath !== $record->photo_path) {
                    DB::afterCommit(fn () => $this->delete($oldPath));
                }

                return $record;
            });
        } catch (Throwable $exception) {
            $this->delete($newPath);

            throw $exception;
        }
    }

    private function store(UploadedFile $photo): string
    {
        $path = 'service-photos/'.Str::uuid().'.webp';

        try {
            $contents = $this->resizeToWebp($photo->getRealPath());

            if (! Storage::disk('public')->put($path, $contents)) {
                throw new RuntimeException('Unable to write the service photo.');
            }
        } catch (Throwable $exception) {
            $this->delete($path);
            report($exception);

            throw ValidationException::withMessages([
                'photo' => __('The photo could not be saved. Please select another image or try again.'),
            ]);
        }

        return $path;
    }

    private function delete(?string $path): void
    {
        if (! $path || ! $this->isManagedPath($path)) {
            return;
        }

        try {
            if (! Storage::disk('public')->delete($path)) {
                throw new RuntimeException('Unable to remove an unused service photo.');
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function isManagedPath(string $path): bool
    {
        return preg_match('/\Aservice-photos\/[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}\.webp\z/', $path) === 1;
    }

    private function resizeToWebp(string $sourcePath): string
    {
        $info = @getimagesize($sourcePath);

        if ($info === false) {
            throw new RuntimeException('Unable to read the service photo.');
        }

        $source = match ($info['mime']) {
            'image/jpeg' => @imagecreatefromjpeg($sourcePath),
            'image/png' => @imagecreatefrompng($sourcePath),
            'image/webp' => @imagecreatefromwebp($sourcePath),
            default => throw new RuntimeException('Unsupported service photo format.'),
        };

        if ($source === false) {
            throw new RuntimeException('Unable to decode the service photo.');
        }

        $scale = min(1, 1200 / max($info[0], $info[1]));
        $width = max(1, (int) round($info[0] * $scale));
        $height = max(1, (int) round($info[1] * $scale));
        $image = imagecreatetruecolor($width, $height);

        if ($image === false) {
            imagedestroy($source);

            throw new RuntimeException('Unable to prepare the service photo.');
        }

        try {
            imagealphablending($image, false);
            imagesavealpha($image, true);

            if (! imagecopyresampled($image, $source, 0, 0, 0, 0, $width, $height, $info[0], $info[1])) {
                throw new RuntimeException('Unable to resize the service photo.');
            }

            ob_start();

            try {
                $encoded = imagewebp($image, null, 85);
                $contents = ob_get_contents();
            } finally {
                ob_end_clean();
            }

            if (! $encoded || $contents === false || $contents === '') {
                throw new RuntimeException('Unable to encode the service photo.');
            }

            return $contents;
        } finally {
            imagedestroy($source);
            imagedestroy($image);
        }
    }
}
