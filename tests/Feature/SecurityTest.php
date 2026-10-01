<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Http\Services\SecurityLogService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('results_static');
        config(['security.csp' => "default-src 'self'"]);
    }

    public function test_malicious_accept_language_does_not_crash(): void
    {
        // كان يسبب خطأ 500: Invalid characters present in locale
        $this->getJson('/api/competitions', ['Accept-Language' => '../../etc/passwd'])->assertOk();
        $this->get('/', ['Accept-Language' => 'x/y'])->assertOk();
    }

    public function test_security_headers_are_sent(): void
    {
        $this->get('/')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Content-Security-Policy', "default-src 'self'")
            ->assertHeaderMissing('X-Powered-By');

        $this->getJson('/api/competitions')->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_account_is_locked_after_failed_logins_from_many_ips(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);
        config(['security.login.max_attempts_per_account' => 3]);

        // محاولات من عناوين IP مختلفة (تجاوز حد الـ IP لا ينفع المهاجم)
        foreach (['10.0.0.1', '10.0.0.2', '10.0.0.3'] as $ip)
        {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])
                ->postJson('/api/admin/login', ['email' => $user->email, 'password' => 'wrong'])
                ->assertStatus(422);
        }

        // حتى كلمة المرور الصحيحة تُرفض أثناء القفل
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.4'])
            ->postJson('/api/admin/login', ['email' => $user->email, 'password' => 'correct-password'])
            ->assertStatus(429)
            ->assertJsonPath('success', false);

        $this->assertGuest('web');
    }

    public function test_successful_login_and_sensitive_actions_are_audited(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);

        $logged = [];
        $this->mock(SecurityLogService::class, function ($mock) use (&$logged) {
            $mock->shouldReceive('info', 'warning')->andReturnUsing(function ($event) use (&$logged) { $logged[] = $event; });
        });

        $this->postJson('/api/admin/login', ['email' => $user->email, 'password' => 'wrong'])->assertStatus(422);
        $this->postJson('/api/admin/login', ['email' => $user->email, 'password' => 'correct-password'])->assertOk();

        $competition = Competition::query()->create(['title' => 'م', 'slug' => 'm', 'status' => 'draft']);
        $this->actingAs($user)->deleteJson("/api/admin/competitions/{$competition->id}")->assertOk();

        $this->assertContains('login.failed', $logged);
        $this->assertContains('login.success', $logged);
        $this->assertContains('competition.deleted', $logged);
    }

    public function test_excel_row_limit(): void
    {
        config(['results.max_rows' => 2]);
        $competition = Competition::query()->create(['title' => 'م', 'slug' => 'm', 'status' => 'draft']);

        $path = tempnam(sys_get_temp_dir(), 'res') . '.xlsx';
        $writer = new \OpenSpout\Writer\XLSX\Writer();
        $writer->openToFile($path);
        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(['الاسم الكامل', 'المحافظة', 'المسمى الوظيفي', 'نتيجة']));
        foreach (range(1, 3) as $i)
        {
            $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues(["اسم {$i}", 'دمشق', 'ناسخ', 'أساسي']));
        }
        $writer->close();

        $this->artisan('results:import', ['competition' => $competition->id, 'file' => $path, '--force' => true])->assertFailed();
        $this->assertSame(0, $competition->candidates()->count());
    }
}
