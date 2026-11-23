<?php

namespace App\Http\Requests\Admin;

use App\Enums\MediaContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $context = $this->mediaContext();

        // Unknown context → let validation answer with a 422 instead of a misleading 403.
        return $context === null || (bool) $this->user('admin')?->canAny($context->permissions());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $maxKilobytes = $this->mediaContext()?->maxKilobytes() ?? 8192;

        return [
            'context' => ['required', Rule::enum(MediaContext::class)],
            'device' => ['nullable', 'in:desktop,mobile'],
            'image' => ['required', 'file', 'mimes:'.implode(',', MediaContext::EXTENSIONS), 'max:'.$maxKilobytes],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'image.mimes' => __('media_library.error_type', ['types' => 'JPG, PNG, WEBP, GIF, AVIF']),
            'image.max' => __('media_library.error_size', ['max' => $this->maxMegabytesLabel()]),
            'image.uploaded' => __('media_library.error_size', ['max' => $this->maxMegabytesLabel()]),
            'image.required' => __('media_library.error_missing'),
        ];
    }

    public function mediaContext(): ?MediaContext
    {
        return MediaContext::tryFrom((string) $this->input('context'));
    }

    private function maxMegabytesLabel(): string
    {
        return rtrim(rtrim(number_format(($this->mediaContext()?->maxKilobytes() ?? 8192) / 1024, 1), '0'), '.').' MB';
    }
}
