<?php

namespace App\Services\Marketer;

use App\Models\File;
use App\Models\Marketer;
use App\Models\MarketerDocument;
use App\Models\MarketerJob;
use App\Models\MarketerProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarketerOnboardingService
{
    public const SOCIAL_PLATFORMS = ['instagram', 'snapchat', 'tiktok', 'twitter', 'youtube', 'facebook'];

    /** "broker" is the public name of the affiliate job. */
    private const KEY_ALIASES = ['broker' => 'affiliate'];

    public const DOC_MIMES = 'pdf,doc,docx,jpg,jpeg,png';

    public const DOC_MAX_KB = 5120;

    /** @return list<string> normalised, de-duplicated job keys that exist and are active */
    public function validJobKeys(array $keys): array
    {
        $keys = collect($keys)->map(fn ($k) => self::KEY_ALIASES[$k] ?? $k)->unique()->values()->all();

        return MarketerJob::whereIn('key', $keys)->where('is_active', true)->pluck('key')->all();
    }

    public function availableJobKeys(): array
    {
        return MarketerJob::where('is_active', true)->orderBy('sort_order')->pluck('key')->all();
    }

    public function normaliseKeys(array $keys): array
    {
        return collect($keys)->map(fn ($k) => self::KEY_ALIASES[$k] ?? $k)->unique()->values()->all();
    }

    public function setJobTypes(Marketer $marketer, array $keys): void
    {
        $ids = MarketerJob::whereIn('key', $this->normaliseKeys($keys))->where('is_active', true)->pluck('id')->all();
        $marketer->marketerJobs()->sync($ids);
    }

    public function hasJob(Marketer $marketer, string $key): bool
    {
        return $marketer->marketerJobs()->where('key', $key)->exists();
    }

    /** @param array<string,mixed> $data validated profile data */
    public function saveProfile(Marketer $marketer, array $data, ?UploadedFile $avatar, ?UploadedFile $banner): MarketerProfile
    {
        return DB::transaction(function () use ($marketer, $data, $avatar, $banner) {
            $profile = $marketer->marketerProfile()->firstOrCreate(['marketer_id' => $marketer->id]);

            $fill = array_intersect_key($data, array_flip(['specialty_ar', 'specialty_en', 'bio_ar', 'bio_en']));

            if (array_key_exists('social_links', $data)) {
                $fill['social_links'] = array_filter(
                    array_intersect_key((array) $data['social_links'], array_flip(self::SOCIAL_PLATFORMS))
                );
            }

            if ($this->hasJob($marketer, 'affiliate')) {
                $serveAll = (bool) ($data['broker_serves_all_cities'] ?? false);
                $fill['broker_category_id'] = $data['broker_category_id'] ?? null;
                $fill['broker_serves_all_cities'] = $serveAll;
                $fill['broker_city_id'] = $serveAll ? null : ($data['broker_city_id'] ?? null);
            }

            $profile->fill($fill);

            if ($avatar) {
                $profile->avatar_file_id = $this->storeImage($avatar, 'marketer-avatars', $profile)->id;
            }
            if ($banner) {
                $profile->banner_file_id = $this->storeImage($banner, 'marketer-banners', $profile)->id;
            }
            $profile->save();

            return $profile;
        });
    }

    public function storeDocument(Marketer $marketer, UploadedFile $file, string $type): MarketerDocument
    {
        $path = $file->store("marketer-documents/{$marketer->id}", 'private');

        return MarketerDocument::create([
            'marketer_id' => $marketer->id,
            'type' => $type,
            'disk' => 'private',
            'path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName(), 200, ''),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize(),
        ]);
    }

    /** CV is single: replacing removes the old one. */
    public function replaceCv(Marketer $marketer, UploadedFile $file): void
    {
        $old = $marketer->documents()->where('type', MarketerDocument::TYPE_CV)->get();
        $this->storeDocument($marketer, $file, MarketerDocument::TYPE_CV);
        foreach ($old as $doc) {
            \Illuminate\Support\Facades\Storage::disk($doc->disk)->delete($doc->path);
            $doc->delete();
        }
    }

    /** @return list<string> human-readable missing requirements; empty = ready to complete */
    public function missingSteps(Marketer $marketer): array
    {
        $missing = [];
        $marketer->unsetRelation('marketerJobs');
        $keys = $marketer->marketerJobs()->where('is_active', true)->pluck('key')->all();
        $profile = $marketer->marketerProfile()->first();

        if (! $keys) {
            return ['job_type'];
        }
        if (! $profile || (blank($profile->bio_ar) && blank($profile->bio_en))) {
            $missing[] = 'bio';
        }
        if (in_array('influencer', $keys, true) && ! collect($profile?->social_links ?? [])->filter()->isNotEmpty()) {
            $missing[] = 'social_links';
        }
        if (in_array('affiliate', $keys, true)) {
            if (! $profile?->broker_category_id) {
                $missing[] = 'broker_category_id';
            }
            if (! $profile?->broker_serves_all_cities && ! $profile?->broker_city_id) {
                $missing[] = 'broker_city_id';
            }
        }

        return $missing;
    }

    /** @return list<string> missing steps; empty means onboarding was stamped */
    public function complete(Marketer $marketer): array
    {
        $missing = $this->missingSteps($marketer);
        if (! $missing && $marketer->onboarding_completed_at === null) {
            $marketer->forceFill(['onboarding_completed_at' => now()])->save();
        }

        return $missing;
    }

    /** Validation rules for the profile step, conditional on the marketer's jobs. */
    public function profileRules(Marketer $marketer): array
    {
        $rules = [
            'specialty_ar' => ['nullable', 'string', 'max:255'],
            'specialty_en' => ['nullable', 'string', 'max:255'],
            'bio_ar' => ['nullable', 'string', 'max:1000', 'required_without:bio_en'],
            'bio_en' => ['nullable', 'string', 'max:1000'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'banner' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];

        if ($this->hasJob($marketer, 'influencer')) {
            $rules['social_links'] = ['required', 'array', function ($attr, $value, $fail) {
                if (! collect($value)->only(self::SOCIAL_PLATFORMS)->filter()->isNotEmpty()) {
                    $fail(__('marketer.social_link_required'));
                }
            }];
            $rules['social_links.*'] = ['nullable', 'url', 'max:500'];
        }

        if ($this->hasJob($marketer, 'affiliate')) {
            $rules['broker_category_id'] = ['required', 'uuid', 'exists:categories,id'];
            $rules['broker_serves_all_cities'] = ['nullable', 'boolean'];
            $rules['broker_city_id'] = ['nullable', 'required_without:broker_serves_all_cities', 'uuid', 'exists:cities,id'];
            $rules['cv'] = ['nullable', 'file', 'mimes:'.self::DOC_MIMES, 'max:'.self::DOC_MAX_KB];
            $rules['certifications'] = ['nullable', 'array', 'max:5'];
            $rules['certifications.*'] = ['file', 'mimes:'.self::DOC_MIMES, 'max:'.self::DOC_MAX_KB];
        }

        return $rules;
    }

    public function storeDocumentsFromRequest(Marketer $marketer, ?UploadedFile $cv, array $certs): void
    {
        if ($cv) {
            $this->replaceCv($marketer, $cv);
        }
        foreach ($certs as $c) {
            $this->storeDocument($marketer, $c, MarketerDocument::TYPE_CERTIFICATION);
        }
    }

    private function storeImage(UploadedFile $upload, string $dir, MarketerProfile $profile): File
    {
        $path = $upload->store($dir.'/'.$profile->marketer_id, 'public');

        return File::create([
            'key' => Str::uuid(),
            'path' => $path,
            'storage_type' => 'public',
            'file_type' => 'image',
            'mime_type' => $upload->getMimeType(),
            'extension' => $upload->extension(),
            'size' => $upload->getSize(),
            'model_type' => MarketerProfile::class,
            'model_id' => $profile->id,
        ]);
    }
}
