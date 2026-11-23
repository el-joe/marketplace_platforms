<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MediaContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UploadMediaRequest;
use App\Services\Admin\MediaLibraryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Upload + "recent uploads" endpoints behind the shared admin media field
 * (`<x-form.media-field>`) and the static-page rich-text image button.
 */
class MediaLibraryController extends Controller
{
    public function __construct(private MediaLibraryService $media) {}

    public function upload(UploadMediaRequest $request): JsonResponse
    {
        $file = $this->media->store($request->file('image'), $request->mediaContext(), $request->input('device'));
        $item = $this->media->present($file);
        [$width, $height] = @getimagesize($request->file('image')->getRealPath()) ?: [null, null];

        return response()->json([
            'file_id' => $file->id,
            'url' => $item['url'],
            'name' => $item['name'],
            'size' => $item['size'],
            'width' => $width,
            'height' => $height,
            // Summernote/rich-editor callback shape.
            'data' => ['url' => $item['url']],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'context' => ['required', Rule::enum(MediaContext::class)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $context = MediaContext::from($data['context']);

        abort_unless($request->user('admin')?->canAny($context->permissions()), 403);

        $page = $this->media->recent($context, $data['q'] ?? null);

        return response()->json([
            'items' => collect($page->items())->map(fn ($file) => $this->media->present($file))->values(),
            'next_page' => $page->hasMorePages() ? $page->currentPage() + 1 : null,
        ]);
    }
}
