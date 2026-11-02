<?php

namespace Tests\Support;

use App\Models\Admin;
use App\Models\ClassifiedContractTemplate;
use App\Models\Vendor;
use App\Models\VendorAdmin;
use App\Services\Vendor\ContractFileService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Builders for uploaded-document contract templates and the vendors who sign them.
 */
trait ContractDocumentFixtures
{
    /**
     * Points the private disk at a throwaway directory (Storage::fake needs storage/framework/testing, which is not writable everywhere).
     */
    protected function fakePrivateDisk(): void
    {
        $root = sys_get_temp_dir().'/contract-tests-'.Str::random(8);

        config(['filesystems.disks.'.ContractFileService::DISK.'.root' => $root]);
        Storage::forgetDisk(ContractFileService::DISK);

        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($root));
    }

    protected function pdf(string $name = 'seller-agreement.pdf', int $kilobytes = 120): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kilobytes, 'application/pdf');
    }

    /**
     * A platform Seller Agreement template backed by a stored pdf (Arabic) and optionally an English one.
     */
    protected function platformFileTemplate(int $version = 1, bool $published = true, array $overrides = [], bool $withEnglish = false): ClassifiedContractTemplate
    {
        $template = ClassifiedContractTemplate::create($overrides + [
            'name' => 'Seller Agreement',
            'category_scope' => 'platform',
            'content_mode' => 'file',
            'version' => $version,
            'content_en' => '',
            'content_ar' => '',
            'is_active' => true,
            'is_published' => false,
            'acceptance_mode' => 'click_wrap',
            'notice_days' => 14,
            'created_by_admin_id' => Admin::factory()->create()->id,
        ]);

        $files = app(ContractFileService::class);
        $stored = ['ar' => $files->store($this->pdf('agreement-ar.pdf'), $template->id, 'ar')];

        if ($withEnglish) {
            $stored['en'] = $files->store($this->pdf('agreement-en.pdf'), $template->id, 'en');
        }

        $template->update(['files' => $stored]);

        if ($published) {
            $template->update(['is_published' => true, 'published_at' => now(), 'effective_at' => now()->subMinute()]);
        }

        return $template->fresh();
    }

    protected function ownerOf(Vendor $vendor): VendorAdmin
    {
        return VendorAdmin::create([
            'vendor_id' => $vendor->id, 'name' => 'Vendor Owner',
            'email' => 'owner-'.Str::random(6).'@example.test', 'password' => bcrypt('password'),
            'role' => 'owner', 'is_owner' => true, 'is_active' => true,
        ]);
    }
}
