<?php

namespace App\Services;

use App\Models\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class FileService
{
    /**
     * Store an uploaded file and create the corresponding File record.
     *
     * @param  UploadedFile  $file
     * @param  string  $modelType  e.g. 'App\Models\SliderSlide'
     * @param  string  $modelId    the model UUID / id (stored as varchar36)
     * @param  string  $fileType   e.g. 'slide_desktop', 'slide_mobile', 'ad_image'
     * @param  string  $disk       storage disk name (default 'public')
     * @return File
     */
    public function store(
        UploadedFile $file,
        string $modelType,
        string $modelId,
        string $fileType = 'image',
        string $disk = 'public'
    ): File {
        $path = $file->store(
            strtolower($fileType) . '/' . $modelId,
            $disk
        );

        return File::create([
            'key' => $path,
            'path' => $path,
            'storage_type' => $disk,
            'file_type' => $fileType,
            'mime_type' => $file->getMimeType(),
            'extension' => $file->getClientOriginalExtension(),
            'size' => $file->getSize(),
            'model_type' => $modelType,
            'model_id' => $modelId,
            'is_primary' => 1,
        ]);
    }

    /**
     * Delete a file from storage and remove its DB record.
     */
    public function delete(File $file): void
    {
        Storage::disk($file->storage_type)->delete($file->path);
        $file->forceDelete(); // File model auto-deletes on ::deleting, but be explicit
    }

    /**
     * Return the public URL for a file.
     */
    public function getUrl(File $file): string
    {
        return Storage::disk($file->storage_type)->url($file->path);
    }
}
