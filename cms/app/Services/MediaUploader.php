<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

class MediaUploader
{
    public function store(UploadedFile $file, string $name, string $alt = ''): Media
    {
        validator(['photo' => $file], ['photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:8192']])->validate();
        $size = @getimagesize($file->getRealPath());
        if (! $size || ! in_array($size['mime'], ['image/jpeg', 'image/png', 'image/webp']) || $size[0] * $size[1] > 24000000) {
            throw ValidationException::withMessages(['photo' => 'Выберите JPG, PNG или WebP не более 8 МБ и 24 мегапикселей.']);
        }
        $image = @imagecreatefromstring(file_get_contents($file->getRealPath()));
        if (! $image) {
            throw ValidationException::withMessages(['photo' => 'Не получилось открыть фотографию. Попробуйте другой файл.']);
        }
        // Re-encode instead of trusting the original file or extension.
        $width = imagesx($image);
        $height = imagesy($image);
        if (max($width, $height) > 2560) {
            $scale = 2560 / max($width, $height);
            $scaled = imagescale($image, (int) round($width * $scale), (int) round($height * $scale));
            imagedestroy($image);
            $image = $scaled;
        }
        $path = 'uploads/images/'.bin2hex(random_bytes(20)).'.webp';
        File::ensureDirectoryExists(public_path('uploads/images'));
        if (! imagewebp($image, public_path($path), 88)) {
            throw new \RuntimeException('Не удалось сохранить фотографию');
        }
        chmod(public_path('uploads'), 0755);
        chmod(public_path('uploads/images'), 0755);
        chmod(public_path($path), 0644);
        $media = Media::create(['name' => $name, 'alt' => $alt, 'path' => $path, 'width' => imagesx($image), 'height' => imagesy($image)]);
        imagedestroy($image);

        return $media;
    }
}
