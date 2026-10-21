<?php

namespace Tests\Feature;

use App\Filament\Resources\ApplicationResource\Pages\EditApplication;
use App\Mail\ApplicationReceived;
use App\Models\Application;
use App\Models\ContentDocument;
use App\Models\User;
use App\Services\ApplicationToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class ApplicationsTest extends TestCase
{
    use RefreshDatabase;

    private const ORIGIN = 'https://externlangschool.ru';

    protected function setUp(): void
    {
        parent::setUp();
        config(['applications.enabled' => true, 'applications.recipient' => 'school@example.test', 'applications.origins' => [self::ORIGIN], 'cors.allowed_origins' => [self::ORIGIN]]);
        Mail::fake();
    }

    private function payload(): array
    {
        return ['token' => app(ApplicationToken::class)->issue(self::ORIGIN), 'name' => 'Тест — не реальная заявка', 'phone' => '+7 000 000 00 00', 'language' => 'Итальянский', 'comment' => 'Проверка формы', 'consent' => 'yes', 'page' => '/', 'preferences' => 'Утро · Оплата раз в неделю', 'website' => ''];
    }

    private function submit(array $data)
    {
        return $this->withHeader('Origin', self::ORIGIN)->postJson('/api/applications', $data);
    }

    public function test_application_is_saved_with_consent_and_notified_only_to_the_fixed_recipient(): void
    {
        $data = $this->payload();
        $data['recipient'] = 'attacker@example.test';
        $response = $this->submit($data)->assertCreated()->assertJson(['accepted' => true]);
        $application = Application::firstOrFail();
        $this->assertEquals($application->reference, $response->json('reference'));
        $this->assertNotNull($application->consented_at);
        $this->assertNotEmpty($application->consent_version);
        $this->assertNotNull($application->notified_at);
        Mail::assertSent(ApplicationReceived::class, fn ($mail) => $mail->hasTo('school@example.test') && ! $mail->hasTo('attacker@example.test'));
        $this->assertStringContainsString('Проверка формы', (new ApplicationReceived($application))->render());
    }

    public function test_retrying_a_request_does_not_duplicate_the_application_or_email(): void
    {
        $data = $this->payload();
        $first = $this->submit($data)->assertCreated();
        $this->submit($data)->assertCreated()->assertJson(['reference' => $first->json('reference')]);
        $this->assertDatabaseCount('applications', 1);
        Mail::assertSentCount(1);
    }

    public function test_invalid_inputs_and_missing_consent_do_not_create_records_or_email(): void
    {
        $this->submit([...$this->payload(), 'name' => "A\r\nBcc: test", 'phone' => '123', 'language' => 'Other', 'consent' => false])->assertUnprocessable()->assertJsonValidationErrors(['name', 'phone', 'language', 'consent']);
        $this->submit([...$this->payload(), 'comment' => str_repeat('я', 1001), 'page' => '/admin'])->assertUnprocessable()->assertJsonValidationErrors(['comment', 'page']);
        $this->assertDatabaseCount('applications', 0);
        Mail::assertNothingSent();
    }

    public function test_cross_site_expired_and_tampered_tokens_are_rejected(): void
    {
        $this->withHeader('Origin', 'https://evil.example')->postJson('/api/applications', $this->payload())->assertForbidden();
        $data = $this->payload();
        $this->travel(3)->hours();
        $this->submit($data)->assertStatus(419);
        $this->submit([...$this->payload(), 'token' => str_repeat('a', 110)])->assertStatus(419);
        $this->assertDatabaseCount('applications', 0);
    }

    public function test_honeypot_is_not_saved_or_mailed(): void
    {
        $this->submit([...$this->payload(), 'website' => 'https://spam.example'])->assertOk();
        $this->assertDatabaseCount('applications', 0);
        Mail::assertNothingSent();
    }

    public function test_submission_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->submit([...$this->payload(), 'website' => 'spam'])->assertOk();
        }
        $this->submit($this->payload())->assertStatus(429);
        $this->assertDatabaseCount('applications', 0);
    }

    public function test_mail_failure_preserves_the_application_and_can_be_retried(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('Mail unavailable'));
        $this->submit($this->payload())->assertCreated()->assertJson(['accepted' => true]);
        $application = Application::firstOrFail();
        $this->assertNull($application->notified_at);
        $this->assertEquals(1, $application->notification_attempts);
        Mail::swap(new MailManager($this->app));
        Mail::fake();
        $this->travel(16)->minutes();
        $this->artisan('applications:notify')->assertSuccessful();
        $this->assertNotNull($application->fresh()->notified_at);
        Mail::assertSentCount(1);
    }

    public function test_only_approved_origin_can_get_a_form_token_and_errors_remain_private(): void
    {
        $this->withHeader('Origin', self::ORIGIN)->getJson('/api/applications/token')->assertOk()->assertJsonStructure(['token'])->assertHeader('Access-Control-Allow-Origin', self::ORIGIN)->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->withHeader('Origin', 'https://evil.example')->getJson('/api/applications/token')->assertForbidden()->assertHeader('Access-Control-Allow-Origin', self::ORIGIN);
        config(['applications.enabled' => false]);
        $this->withHeader('Origin', self::ORIGIN)->getJson('/api/applications/token')->assertStatus(503);
    }

    public function test_applications_are_private_and_admin_can_only_change_status(): void
    {
        $this->submit($this->payload())->assertCreated();
        $application = Application::firstOrFail();
        $this->get('/admin/applications')->assertRedirect('/admin/login');
        $user = User::factory()->create(['is_admin' => true]);
        $this->actingAs($user)->get('/admin/applications')->assertOk()->assertSee('Заявки');
        Livewire::actingAs($user)->test(EditApplication::class, ['record' => $application->id])
            ->fillForm(['status' => 'contacted'])->call('save')->assertHasNoFormErrors();
        $this->assertEquals('contacted', $application->fresh()->status);
        $this->assertEquals('Тест — не реальная заявка', $application->fresh()->name);
    }

    public function test_retention_removes_old_records_and_keeps_current_applications(): void
    {
        $this->submit($this->payload())->assertCreated();
        $recent = Application::firstOrFail();
        $old = $recent->replicate();
        $old->reference = 'OLDFIXTURE01';
        $old->request_key = hash('sha256', 'old fixture');
        $old->created_at = now()->subDays(181);
        $old->save();
        $this->artisan('applications:prune')->assertSuccessful();
        $this->assertDatabaseCount('applications', 1);
        $this->assertNotNull($recent->fresh());
        $this->assertNull($old->fresh());
    }

    public function test_consent_records_keep_the_published_copy_after_documents_change(): void
    {
        $doc = ContentDocument::create(['key' => 'consent.html', 'kind' => 'page', 'name' => 'Согласие', 'template' => '', 'schema' => [], 'draft' => ['body' => 'Неопубликованный текст'], 'published' => ['body' => 'Согласие редакция 1']]);
        $this->submit($this->payload())->assertCreated();
        $doc->update(['published' => ['body' => 'Согласие редакция 2']]);
        $this->assertSame(['consent.html' => ['body' => 'Согласие редакция 1']], Application::first()->consent_documents);
    }
}
