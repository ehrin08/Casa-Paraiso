<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/** Square portrait photos for staff accounts, shown as avatars and on the public team chart. */
class UserPhotoService
{
    private const SIZE = 480;

    public const RULES = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'];

    public function url(?string $path): ?string
    {
        if ($path && $this->isManagedPath($path) && Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->url($path);
        }

        return null;
    }

    /** @param array<string, mixed> $data */
    public function save(User $user, array $data, ?UploadedFile $photo, bool $removePhoto = false): User
    {
        $newPath = $photo ? $this->store($photo) : null;

        try {
            return DB::transaction(function () use ($user, $data, $newPath, $removePhoto): User {
                $record = User::query()->lockForUpdate()->findOrFail($user->getKey());
                $oldPath = $record->photo_path;
                $record->fill($data);

                if ($newPath !== null) {
                    $record->photo_path = $newPath;
                } elseif ($removePhoto) {
                    $record->photo_path = null;
                }

                if (! $record->save()) {
                    throw new RuntimeException('Unable to save the team card.');
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
        $path = 'user-photos/'.Str::uuid().'.webp';

        try {
            $contents = $this->cropToWebp($photo->getRealPath());

            if (! Storage::disk('public')->put($path, $contents)) {
                throw new RuntimeException('Unable to write the staff photo.');
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
                throw new RuntimeException('Unable to remove an unused staff photo.');
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function isManagedPath(string $path): bool
    {
        return preg_match('/\Auser-photos\/[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}\.webp\z/', $path) === 1;
    }

    /** Center-crop to a square and scale down to at most 480px, never upscaling. */
    private function cropToWebp(string $sourcePath): string
    {
        $info = @getimagesize($sourcePath);

        if ($info === false) {
            throw new RuntimeException('Unable to read the staff photo.');
        }

        $source = match ($info['mime']) {
            'image/jpeg' => @imagecreatefromjpeg($sourcePath),
            'image/png' => @imagecreatefrompng($sourcePath),
            'image/webp' => @imagecreatefromwebp($sourcePath),
            default => throw new RuntimeException('Unsupported staff photo format.'),
        };

        if ($source === false) {
            throw new RuntimeException('Unable to decode the staff photo.');
        }

        $cropSize = min($info[0], $info[1]);
        $cropX = intdiv($info[0] - $cropSize, 2);
        $cropY = intdiv($info[1] - $cropSize, 2);
        $size = max(1, min(self::SIZE, $cropSize));
        $image = imagecreatetruecolor($size, $size);

        if ($image === false) {
            imagedestroy($source);

            throw new RuntimeException('Unable to prepare the staff photo.');
        }

        try {
            imagealphablending($image, false);
            imagesavealpha($image, true);

            if (! imagecopyresampled($image, $source, 0, 0, $cropX, $cropY, $size, $size, $cropSize, $cropSize)) {
                throw new RuntimeException('Unable to resize the staff photo.');
            }

            ob_start();

            try {
                $encoded = imagewebp($image, null, 85);
                $contents = ob_get_contents();
            } finally {
                ob_end_clean();
            }

            if (! $encoded || $contents === false || $contents === '') {
                throw new RuntimeException('Unable to encode the staff photo.');
            }

            return $contents;
        } finally {
            imagedestroy($source);
            imagedestroy($image);
        }
    }
}
