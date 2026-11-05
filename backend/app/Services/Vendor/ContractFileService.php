<?php

namespace App\Services\Vendor;

use App\Models\CustomerContract;
use App\Models\VendorContract;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Stores uploaded contract documents (pdf, doc, docx) on the private disk.
 *
 * Word files also get a PDF preview when LibreOffice is installed. The preview is optional:
 * without it the original stays downloadable and viewers fall back to a download button.
 *
 * @phpstan-type FileMeta array{path: string, name: string, mime: string, size: int, sha256: string, pdf_path: ?string}
 */
class ContractFileService
{
    public const DISK = 'private';

    public const MAX_KB = 20480;

    public const EXTENSIONS = ['pdf', 'doc', 'docx'];

    private const MIMES = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    /**
     * @return FileMeta
     */
    public function store(UploadedFile $file, string $templateId, string $language): array
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'pdf');
        $path = $this->directory($templateId).'/'.$language.'-'.Str::random(10).'.'.$extension;

        Storage::disk(self::DISK)->put($path, file_get_contents($file->getRealPath()));

        return [
            'path' => $path,
            'name' => $file->getClientOriginalName(),
            'mime' => self::MIMES[$extension] ?? 'application/octet-stream',
            'size' => (int) $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()),
            'pdf_path' => $extension === 'pdf' ? $path : $this->convertToPdf($path),
        ];
    }

    /**
     * Copies every file of a template into another template's folder, so versions never share storage.
     *
     * @param  array<string, FileMeta>|null  $files
     * @return array<string, FileMeta>
     */
    public function copyFiles(?array $files, string $toTemplateId): array
    {
        $copied = [];

        foreach ($files ?? [] as $language => $meta) {
            $copy = $this->copyTo($meta['path'], $this->directory($toTemplateId));
            $pdfPath = $meta['pdf_path'] === null
                ? null
                : ($meta['pdf_path'] === $meta['path'] ? $copy : $this->copyTo($meta['pdf_path'], $this->directory($toTemplateId)));

            $copied[$language] = ['path' => $copy, 'pdf_path' => $pdfPath] + $meta;
        }

        return $copied;
    }

    /**
     * Frozen private copy of the document a vendor signed.
     *
     * @param  FileMeta  $meta
     * @return array{path: string, name: string, mime: string}
     */
    public function copyForSignature(array $meta, string $vendorId): array
    {
        return [
            'path' => $this->copyTo($meta['path'], 'vendor-contracts/'.$vendorId),
            'name' => $meta['name'],
            'mime' => $meta['mime'],
        ];
    }

    /**
     * Frozen private copy of the document a customer accepted.
     *
     * @param  FileMeta  $meta
     * @return array{path: string, name: string, mime: string}
     */
    public function copyForCustomerSignature(array $meta, string $customerId): array
    {
        return [
            'path' => $this->copyTo($meta['path'], 'customer-contracts/'.$customerId),
            'name' => $meta['name'],
            'mime' => $meta['mime'],
        ];
    }

    public function deleteTemplateFiles(string $templateId): void
    {
        Storage::disk(self::DISK)->deleteDirectory($this->directory($templateId));
    }

    /**
     * @param  FileMeta|null  $meta
     */
    public function delete(?array $meta): void
    {
        foreach (array_unique(array_filter([$meta['path'] ?? null, $meta['pdf_path'] ?? null])) as $path) {
            Storage::disk(self::DISK)->delete($path);
        }
    }

    /**
     * Streams a template document: the PDF preview inline when there is one, otherwise the original as a download.
     *
     * @param  FileMeta  $meta
     */
    public function respond(array $meta, bool $download = false): StreamedResponse
    {
        $disk = Storage::disk(self::DISK);

        if (! $download && $meta['pdf_path'] !== null && $disk->exists($meta['pdf_path'])) {
            return $disk->response($meta['pdf_path'], pathinfo($meta['name'], PATHINFO_FILENAME).'.pdf', ['Content-Type' => 'application/pdf']);
        }

        abort_unless($disk->exists($meta['path']), 404);

        return $disk->download($meta['path'], $meta['name'], ['Content-Type' => $meta['mime']]);
    }

    /**
     * Streams the frozen copy of the document a vendor signed.
     */
    public function respondToSigned(VendorContract $contract): StreamedResponse
    {
        abort_unless($contract->hasSignedFile() && $this->exists($contract->signed_file_path), 404);

        return Storage::disk(self::DISK)->download(
            $contract->signed_file_path,
            $contract->signed_file_name,
            ['Content-Type' => $contract->signed_file_mime ?? 'application/octet-stream'],
        );
    }

    /**
     * Streams the frozen copy of the document a customer accepted.
     */
    public function respondToCustomerSigned(CustomerContract $contract): StreamedResponse
    {
        abort_unless($contract->hasSignedFile() && $this->exists($contract->signed_file_path), 404);

        return Storage::disk(self::DISK)->download(
            $contract->signed_file_path,
            $contract->signed_file_name,
            ['Content-Type' => $contract->signed_file_mime ?? 'application/octet-stream'],
        );
    }

    public function exists(?string $path): bool
    {
        return $path !== null && Storage::disk(self::DISK)->exists($path);
    }

    private function directory(string $templateId): string
    {
        return 'contract-templates/'.$templateId;
    }

    private function copyTo(string $path, string $directory): string
    {
        $target = $directory.'/'.Str::random(10).'-'.basename($path);
        Storage::disk(self::DISK)->copy($path, $target);

        return $target;
    }

    private function convertToPdf(string $path): ?string
    {
        $binary = (new ExecutableFinder)->find('soffice') ?? (new ExecutableFinder)->find('libreoffice');

        if (! $binary) {
            return null;
        }

        $disk = Storage::disk(self::DISK);
        $source = $disk->path($path);
        $outDir = dirname($source);

        try {
            $process = new Process([
                $binary, '--headless', '--norestore',
                '-env:UserInstallation=file://'.sys_get_temp_dir().'/lo-contracts',
                '--convert-to', 'pdf', '--outdir', $outDir, $source,
            ]);
            $process->setTimeout(90)->run();

            $pdfPath = dirname($path).'/'.pathinfo($path, PATHINFO_FILENAME).'.pdf';

            return $process->isSuccessful() && $disk->exists($pdfPath) ? $pdfPath : null;
        } catch (\Throwable $e) {
            Log::warning('Contract document PDF preview failed: '.$e->getMessage());

            return null;
        }
    }
}
