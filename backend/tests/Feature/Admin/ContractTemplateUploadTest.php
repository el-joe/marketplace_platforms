<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\ClassifiedContractTemplate;
use App\Services\Vendor\ContractFileService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Spatie\Permission\Models\Permission;
use Symfony\Component\Process\ExecutableFinder;
use Tests\Support\ContractDocumentFixtures;
use Tests\TestCase;

class ContractTemplateUploadTest extends TestCase
{
    use ContractDocumentFixtures;
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->fakePrivateDisk();
        Bus::fake();

        foreach (['classifieds.view', 'vendors.view', 'vendors.assigned_only'] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'admin']);
        }

        $this->admin = Admin::factory()->create();
        $this->admin->givePermissionTo(['classifieds.view', 'vendors.view', 'vendors.assigned_only']);
    }

    /**
     * @return array<string, mixed>
     */
    private function filePayload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Seller Agreement',
            'category_scope' => 'platform',
            'content_mode' => 'file',
            'acceptance_mode' => 'click_wrap',
            'file_ar' => $this->pdf('agreement-ar.pdf'),
        ];
    }

    public function test_create_form_offers_the_editor_and_upload_switch_and_the_platform_scope(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.contracts.templates.create'))
            ->assertOk()
            ->assertSee(__('admin.contracts.mode_editor'))
            ->assertSee(__('admin.contracts.mode_file'))
            ->assertSee('name="file_ar"', false)
            ->assertSee('value="platform"', false);
    }

    public function test_admin_uploads_a_pdf_as_a_draft_and_it_is_stored_privately_with_a_hash(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.contracts.templates.store'), $this->filePayload())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $template = ClassifiedContractTemplate::sole();

        $this->assertSame('file', $template->content_mode);
        $this->assertSame('platform', $template->category_scope);
        $this->assertFalse($template->is_published);
        $this->assertSame('', $template->content_ar);
        $this->assertSame('agreement-ar.pdf', $template->files['ar']['name']);
        $this->assertSame(64, strlen($template->files['ar']['sha256']));
        $this->assertSame($template->files['ar']['path'], $template->files['ar']['pdf_path']);
        Storage::disk(ContractFileService::DISK)->assertExists($template->files['ar']['path']);
        $this->assertStringStartsWith('contract-templates/'.$template->id, $template->files['ar']['path']);
    }

    public function test_file_mode_needs_at_least_one_document(): void
    {
        $payload = $this->filePayload();
        unset($payload['file_ar']);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.contracts.templates.store'), $payload)
            ->assertSessionHasErrors('file_ar');

        $this->assertSame(0, ClassifiedContractTemplate::count());
    }

    public function test_only_pdf_and_word_files_within_the_size_limit_are_accepted(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.contracts.templates.store'), $this->filePayload(['file_ar' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain')]))
            ->assertSessionHasErrors('file_ar');

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.contracts.templates.store'), $this->filePayload(['file_ar' => $this->pdf('huge.pdf', ContractFileService::MAX_KB + 1)]))
            ->assertSessionHasErrors('file_ar');

        $this->assertSame(0, ClassifiedContractTemplate::count());
    }

    public function test_editor_mode_still_requires_both_languages_and_stores_no_files(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.contracts.templates.store'), ['name' => 'Typed', 'category_scope' => 'product', 'content_mode' => 'editor', 'content_en' => '<p>EN</p>'])
            ->assertSessionHasErrors('content_ar');

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.contracts.templates.store'), ['name' => 'Typed', 'category_scope' => 'product', 'content_mode' => 'editor', 'content_en' => '<p>EN</p>', 'content_ar' => '<p>AR</p>'])
            ->assertSessionHasNoErrors();

        $template = ClassifiedContractTemplate::sole();
        $this->assertSame('editor', $template->content_mode);
        $this->assertNull($template->files);
    }

    public function test_switching_a_draft_back_to_the_editor_removes_its_files(): void
    {
        $template = $this->platformFileTemplate(published: false);
        $path = $template->files['ar']['path'];

        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.contracts.templates.update', $template), [
                'name' => 'Seller Agreement', 'content_mode' => 'editor', 'acceptance_mode' => 'click_wrap',
                'content_en' => '<p>EN</p>', 'content_ar' => '<p>AR</p>',
            ])
            ->assertSessionHasNoErrors();

        $template->refresh();
        $this->assertSame('editor', $template->content_mode);
        $this->assertNull($template->files);
        Storage::disk(ContractFileService::DISK)->assertMissing($path);
    }

    public function test_replacing_a_draft_file_deletes_the_old_one(): void
    {
        $template = $this->platformFileTemplate(published: false);
        $oldPath = $template->files['ar']['path'];

        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.contracts.templates.update', $template), [
                'name' => 'Seller Agreement', 'content_mode' => 'file', 'acceptance_mode' => 'click_wrap',
                'file_ar' => $this->pdf('replacement.pdf'),
            ])
            ->assertSessionHasNoErrors();

        $template->refresh();
        $this->assertSame('replacement.pdf', $template->files['ar']['name']);
        Storage::disk(ContractFileService::DISK)->assertMissing($oldPath);
        Storage::disk(ContractFileService::DISK)->assertExists($template->files['ar']['path']);
    }

    public function test_changing_a_published_file_creates_a_new_draft_version_and_keeps_the_published_one_untouched(): void
    {
        $published = $this->platformFileTemplate(published: true);
        $publishedPath = $published->files['ar']['path'];

        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.contracts.templates.update', $published), [
                'name' => 'Seller Agreement', 'content_mode' => 'file', 'acceptance_mode' => 'notice', 'notice_days' => 14,
                'file_en' => $this->pdf('agreement-en.pdf'),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, ClassifiedContractTemplate::count());

        $draft = ClassifiedContractTemplate::where('version', 2)->sole();
        $this->assertFalse($draft->is_published);
        $this->assertSame('notice', $draft->acceptance_mode);
        $this->assertSame(['ar', 'en'], array_keys($draft->files));
        $this->assertNotSame($publishedPath, $draft->files['ar']['path']);
        $this->assertStringStartsWith('contract-templates/'.$draft->id, $draft->files['ar']['path']);

        $published->refresh();
        $this->assertTrue($published->is_published);
        $this->assertSame(['ar'], array_keys($published->files));
        Storage::disk(ContractFileService::DISK)->assertExists($publishedPath);
    }

    public function test_admin_can_preview_and_download_a_template_document(): void
    {
        $template = $this->platformFileTemplate(published: false);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.contracts.templates.file', [$template, 'ar']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.contracts.templates.file', [$template, 'ar', 'download' => 1]))
            ->assertOk()
            ->assertDownload('agreement-ar.pdf');

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.contracts.templates.file', [$template, 'en']))
            ->assertNotFound();
    }

    public function test_template_documents_require_an_admin_with_permission(): void
    {
        $template = $this->platformFileTemplate(published: false);

        $this->get(route('admin.contracts.templates.file', [$template, 'ar']))->assertRedirect();

        $other = Admin::factory()->create();
        $this->actingAs($other, 'admin')
            ->get(route('admin.contracts.templates.file', [$template, 'ar']))
            ->assertForbidden();
    }

    public function test_a_draft_without_documents_cannot_be_published_and_a_draft_with_them_can(): void
    {
        $empty = ClassifiedContractTemplate::create([
            'name' => 'Empty', 'category_scope' => 'platform', 'content_mode' => 'file', 'version' => 1,
            'content_en' => '', 'content_ar' => '', 'is_active' => true, 'is_published' => false,
            'created_by_admin_id' => $this->admin->id,
        ]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.contracts.templates.publish', $empty))
            ->assertSessionHas('error');
        $this->assertFalse($empty->fresh()->is_published);

        $draft = $this->platformFileTemplate(published: false);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.contracts.templates.publish', $draft))
            ->assertSessionHas('success');

        $draft->refresh();
        $this->assertTrue($draft->is_published);
        $this->assertNotNull($draft->published_at);
        $this->assertFalse($draft->isScheduled());
    }

    public function test_deleting_a_draft_removes_its_files_but_a_published_one_cannot_be_deleted(): void
    {
        $draft = $this->platformFileTemplate(published: false);
        $path = $draft->files['ar']['path'];

        $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.contracts.templates.destroy', $draft))
            ->assertRedirect();

        $this->assertDatabaseMissing('classified_contract_templates', ['id' => $draft->id]);
        Storage::disk(ContractFileService::DISK)->assertMissing($path);

        $published = $this->platformFileTemplate(version: 2, published: true);

        $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.contracts.templates.destroy', $published))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('classified_contract_templates', ['id' => $published->id]);
    }

    public function test_index_shows_file_templates_with_their_language_links_and_scheduled_status(): void
    {
        $this->platformFileTemplate(published: true);
        $scheduled = $this->platformFileTemplate(version: 2, published: true, overrides: ['acceptance_mode' => 'notice']);
        $scheduled->update(['effective_at' => now()->addDays(14)]);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.contracts.templates.index', ['scope' => 'platform']))
            ->assertOk()
            ->assertSee(__('admin.contracts.mode_file_badge'))
            ->assertSee(route('admin.contracts.templates.file', [$scheduled, 'ar']), false)
            ->assertSee(__('admin.contracts.status_scheduled', ['date' => now()->addDays(14)->format('d/m/Y')]));
    }

    public function test_word_documents_get_a_pdf_preview_when_libreoffice_is_installed(): void
    {
        if (! (new ExecutableFinder)->find('soffice')) {
            $this->markTestSkipped('LibreOffice is not installed.');
        }

        $phpWord = new PhpWord;
        $phpWord->addSection()->addText('Seller Agreement test document');
        $path = tempnam(sys_get_temp_dir(), 'agreement').'.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);
        $upload = new UploadedFile($path, 'agreement.docx', null, null, true);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.contracts.templates.store'), $this->filePayload(['file_ar' => $upload]))
            ->assertSessionHasNoErrors();

        $file = ClassifiedContractTemplate::sole()->files['ar'];
        $this->assertSame('agreement.docx', $file['name']);
        $this->assertNotSame($file['path'], $file['pdf_path']);
        Storage::disk(ContractFileService::DISK)->assertExists($file['pdf_path']);
    }
}
