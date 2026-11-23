<?php

namespace App\Services\Admin;

use App\Enums\MediaContext;
use App\Models\File;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Shared storage for the admin media field (page builder, custom pages, static pages).
 *
 * Files land on the `public` disk under the context's directory with a sanitized,
 * collision-free name ("{slug-of-original-name}-{random}.{server-detected-ext}") and a
 * `files` row whose `file_type` matches the older per-screen endpoints, so existing
 * blocks (which store either the public URL or the file id) keep working unchanged.
 */
class MediaLibraryService
{
    private const DISK = 'public';

    public function store(UploadedFile $upload, MediaContext $context, ?string $device = null): File
    {
        $extension = $this->safeExtension($upload);
        $path = $upload->storeAs($context->directory(), $this->safeBasename($upload).'-'.Str::lower(Str::random(10)).'.'.$extension, self::DISK);

        return File::create([
            'key' => $path,
            'path' => $path,
            'storage_type' => self::DISK,
            'file_type' => $context->fileType($device),
            'mime_type' => $upload->getMimeType(),
            'extension' => $extension,
            'size' => $upload->getSize(),
        ]);
    }

    /** Recent uploads for the context's "choose from library" picker. */
    public function recent(MediaContext $context, ?string $search = null, int $perPage = 24): LengthAwarePaginator
    {
        return File::query()
            ->whereIn('file_type', $context->libraryFileTypes())
            ->where('storage_type', self::DISK)
            ->when($search, fn ($query) => $query->where('path', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%'))
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * @return array{id: int, url: string, name: string, size: int|null, mime_type: string|null, created_at: string|null}
     */
    public function present(File $file): array
    {
        return [
            'id' => $file->id,
            'url' => $this->publicUrl($file),
            'name' => basename((string) $file->path),
            'size' => $file->size,
            'mime_type' => $file->mime_type,
            'created_at' => admin_tz($file->created_at)?->format('Y-m-d H:i'),
        ];
    }

    /** Same URL shape the page-builder upload endpoints have always returned. */
    public function publicUrl(File $file): string
    {
        return Storage::disk($file->disk())->url($file->path);
    }

    /**
     * Make `$file` the primary image of `$owner`. A library file that already belongs to
     * another record is copied first, so deleting one owner never removes another's image.
     */
    public function attachAsPrimary(File $file, Model $owner, string $directory): File
    {
        $belongsToOther = $file->model_id !== null
            && ($file->model_type !== $owner->getMorphClass() || (string) $file->model_id !== (string) $owner->getKey());

        if ($belongsToOther) {
            $copyPath = $directory.'/'.pathinfo((string) $file->path, PATHINFO_FILENAME).'-'.Str::lower(Str::random(6)).'.'.$file->extension;
            Storage::disk($file->disk())->copy($file->path, $copyPath);
            $file = $file->replicate(['model_type', 'model_id']);
            $file->path = $copyPath;
            $file->key = $copyPath;
        }

        $file->forceFill([
            'model_type' => $owner->getMorphClass(),
            'model_id' => $owner->getKey(),
            'is_primary' => 1,
            'position' => 0,
        ])->save();

        return $file;
    }

    private function safeExtension(UploadedFile $upload): string
    {
        $guessed = Str::lower((string) $upload->guessExtension());
        $guessed = $guessed === 'jpeg' ? 'jpg' : $guessed;

        return in_array($guessed, MediaContext::EXTENSIONS, true) ? $guessed : 'jpg';
    }

    private function safeBasename(UploadedFile $upload): string
    {
        $slug = Str::slug(Str::limit(pathinfo($upload->getClientOriginalName(), PATHINFO_FILENAME), 60, ''));

        return $slug !== '' ? Str::limit($slug, 60, '') : 'image';
    }
}
