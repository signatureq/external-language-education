<?php

namespace Tests\Feature;

use App\Models\ContentDocument;
use App\Models\User;
use App\Services\ContentEditor;
use App\Services\MediaUploader;
use App\Services\SiteImporter;
use App\Services\SitePublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ContentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private string $output;

    protected function setUp(): void
    {
        parent::setUp();
        $this->output = sys_get_temp_dir().'/ele-test-'.bin2hex(random_bytes(6));
        File::makeDirectory($this->output, 0755, true);
        $this->app->usePublicPath($this->output);
        app(SiteImporter::class)->import(dirname(base_path()));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->output);
        parent::tearDown();
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->is_admin = true;
        $user->save();

        return $user;
    }

    private function page(): ContentDocument
    {
        return ContentDocument::where('key', 'index.html')->firstOrFail();
    }

    public function test_import_covers_real_pages_and_shared_content_without_touching_public_files(): void
    {
        $this->assertEquals(20, ContentDocument::count());
        $this->assertEquals(7, ContentDocument::where('kind', 'language')->count());
        $this->assertEmpty(File::files($this->output));
        $html = app(SitePublisher::class)->render($this->page());
        $this->assertStringContainsString('class="site-header"', $html);
        $this->assertStringContainsString('class="site-footer"', $html);
        $this->assertStringNotContainsString('CMSVALUE_', $html);
        $this->assertStringContainsString('<svg', $html);
    }

    public function test_draft_preview_is_separate_from_published_content_and_escapes_markup(): void
    {
        $page = $this->page();
        $key = 'f3';
        $draft = $page->draft;
        $draft[$key] = 'Новый заголовок <script>alert(1)</script>';
        $page = app(ContentEditor::class)->save($page, $draft, $page->version);
        $this->assertTrue($page->hasDraft());
        $this->assertStringNotContainsString('Новый заголовок', app(SitePublisher::class)->render($page));
        $preview = app(SitePublisher::class)->render($page, $page);
        $this->assertStringContainsString('Новый заголовок &lt;script&gt;', $preview);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $preview);
    }

    public function test_publish_writes_the_page_and_keeps_an_undo_revision(): void
    {
        $page = $this->page();
        $draft = $page->draft;
        $draft['seo_title'] = 'Новое название';
        $page = app(ContentEditor::class)->save($page, $draft, $page->version);
        $oldMask = umask(0077);
        try {
            app(SitePublisher::class)->publish($page, $page->version, 'Редактор');
        } finally {
            umask($oldMask);
        }
        $this->assertEquals(0644, fileperms($this->output.'/index.html') & 0777);
        $this->assertStringContainsString('<title>Новое название</title>', File::get($this->output.'/index.html'));
        $this->assertFalse($page->fresh()->hasDraft());
        $this->assertEquals(1, $page->revisions()->count());
        app(ContentEditor::class)->save($page->fresh(), $page->revisions()->first()->data, $page->fresh()->version);
        $this->assertTrue($page->fresh()->hasDraft());
        $this->assertStringContainsString('<title>Новое название</title>', File::get($this->output.'/index.html'));
    }

    public function test_shared_publication_updates_all_pages_but_does_not_publish_other_drafts(): void
    {
        $page = $this->page();
        $draft = $page->draft;
        $draft['seo_title'] = 'НЕ ПУБЛИКОВАТЬ';
        app(ContentEditor::class)->save($page, $draft, $page->version);
        $footer = ContentDocument::where('key', 'shared.footer')->first();
        $draft = $footer->draft;
        $key = array_key_first($draft);
        $draft[$key] = 'Общая правка';
        $footer = app(ContentEditor::class)->save($footer, $draft, $footer->version);
        app(SitePublisher::class)->publish($footer, $footer->version, 'Редактор');
        $this->assertCount(16, glob($this->output.'/*.html'));
        $html = File::get($this->output.'/index.html');
        $this->assertStringContainsString('Общая правка', $html);
        $this->assertStringNotContainsString('НЕ ПУБЛИКОВАТЬ', $html);
    }

    public function test_central_price_and_schedule_updates_include_interactive_settings(): void
    {
        $settings = ContentDocument::where('key', 'settings')->first();
        $settings = app(ContentEditor::class)->save($settings, ['rate' => 1000, 'morning' => '10:00–14:30', 'evening' => '18:00–22:30'], $settings->version);
        app(SitePublisher::class)->publish($settings, $settings->version, 'Редактор');
        $html = File::get($this->output.'/tuition.html');
        $this->assertStringContainsString('16 000 ₽', $html);
        $this->assertStringContainsString('"rate":1000', $html);
        $html = File::get($this->output.'/schedule.html');
        $this->assertStringContainsString('10:00–14:30', $html);
        $this->assertStringNotContainsString('9:00–13:30', $html);
    }

    public function test_stale_editor_cannot_overwrite_a_newer_save(): void
    {
        $page = $this->page();
        app(ContentEditor::class)->save($page, $page->draft, $page->version);
        $this->expectException(ValidationException::class);
        app(ContentEditor::class)->save($page, $page->draft, $page->version);
    }

    public function test_failed_render_does_not_replace_public_files_or_publish_data(): void
    {
        File::put($this->output.'/index.html', 'old page');
        $page = $this->page();
        $page->update(['template' => 'CMSVALUE_missing_END']);
        try {
            app(SitePublisher::class)->publish($page, $page->version, 'Редактор');
            $this->fail('Expected rendering failure');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('не найдено', $e->getMessage());
        }
        $this->assertEquals('old page', File::get($this->output.'/index.html'));
        $this->assertEquals(0, $page->revisions()->count());
    }

    public function test_admin_and_preview_require_authenticated_administrator(): void
    {
        $this->get('/admin/pages')->assertRedirect('/admin/login');
        $this->get('/admin/preview/'.$this->page()->id)->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create())->get('/admin/pages')->assertForbidden();
        $this->get('/admin/preview/'.$this->page()->id)->assertForbidden();
        $this->actingAs($this->admin())->get('/admin/pages')->assertOk();
        $this->get('/admin/preview/'.$this->page()->id)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertSee('Предпросмотр сохранённого черновика');
    }

    public function test_uploaded_images_are_reencoded_and_executable_files_rejected(): void
    {
        $photo = UploadedFile::fake()->image('classroom.jpg', 800, 600);
        $media = app(MediaUploader::class)->store($photo, 'Наш класс', 'Занятие');
        $this->assertStringEndsWith('.webp', $media->path);
        $this->assertEquals(0644, fileperms($this->output.'/'.$media->path) & 0777);
        $this->assertFileExists($this->output.'/'.$media->path);
        $this->assertEquals('image/webp', getimagesize($this->output.'/'.$media->path)['mime']);
        $this->expectException(ValidationException::class);
        app(MediaUploader::class)->store(UploadedFile::fake()->createWithContent('fake.jpg', '<?php echo 1;'), 'Неверный файл');
    }

    public function test_image_field_cannot_publish_arbitrary_or_external_paths(): void
    {
        $page = $this->page();
        $draft = $page->draft;
        foreach ($page->schema as $group) {
            foreach ($group['fields'] as $field) {
                if ($field['type'] === 'image') {
                    $draft[$field['key']] = 'https://bad.example/track.jpg';
                    break 2;
                }
            }
        }
        $this->expectException(ValidationException::class);
        app(ContentEditor::class)->save($page, $draft, $page->version);
    }

    public function test_first_access_setup_requires_a_valid_unexpired_ticket_and_closes_after_creation(): void
    {
        $token = str_repeat('a', 64);
        config(['cms.setup_hash' => hash('sha256', $token), 'cms.setup_expires' => now()->addHour()->toIso8601String()]);
        $this->get('/admin/setup/wrong')->assertNotFound();
        $this->get('/admin/setup/'.$token)->assertOk()->assertSee('Создайте свой доступ');
        $this->post('/admin/setup/'.$token, ['name' => 'Владелец', 'email' => 'owner@example.test', 'password' => 'safe-local-test-password', 'password_confirmation' => 'safe-local-test-password'])->assertRedirect('/admin');
        $this->assertTrue((bool) User::where('email', 'owner@example.test')->first()->is_admin);
        $this->get('/admin/setup/'.$token)->assertNotFound();
    }

    public function test_expired_setup_link_never_exposes_registration(): void
    {
        $token = str_repeat('b', 64);
        config(['cms.setup_hash' => hash('sha256', $token), 'cms.setup_expires' => now()->subMinute()->toIso8601String()]);
        $this->get('/admin/setup/'.$token)->assertNotFound();
    }
}
