<?php

namespace Tests\Feature\Security;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * SEC-004 (P-1.9): company logos are stored on the public disk and served from the API origin, so a scriptable file
 * type (SVG, HTML) must never be accepted — not with its own extension and not disguised as an image.
 */
#[Group('security')]
class UploadTest extends TestCase
{
    use RefreshDatabase;

    private const SVG = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><script>alert(document.domain)</script><rect width="10" height="10"/></svg>';

    /** A real upload (content-sniffed MIME type), unlike UploadedFile::fake(), which reports a type from the name. */
    private function upload(string $name, string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'logo');
        file_put_contents($path, $content);

        return new UploadedFile($path, $name, null, null, true);
    }

    private function companyUser(): User
    {
        $company = Company::create(['name' => 'Acme Ltd', 'hr_name' => 'Asha', 'hr_email' => 'hr@acme.qa.test']);

        return User::factory()->create(['role' => 'company', 'company_id' => $company->id, 'email' => 'hr@acme.qa.test']);
    }

    public function test_company_logo_rejects_svg_and_scriptable_types(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->companyUser());

        $files = [
            'svg' => $this->upload('logo.svg', self::SVG),
            'svg disguised as png' => $this->upload('logo.png', self::SVG),
            'html disguised as png' => $this->upload('logo.png', '<html><body><script>alert(1)</script></body></html>'),
        ];

        foreach ($files as $label => $file) {
            $this->postJson('/api/company/profile/logo', ['company_logo' => $file])
                ->assertStatus(422)
                ->assertJsonValidationErrors('company_logo');
            $this->assertSame([], Storage::disk('public')->allFiles('company-logos'), "{$label}: nothing may be stored");
        }

        // Registration uses the same rule (it validates before the recruiter-email check).
        $this->postJson('/api/auth/company/register', ['company_logo' => $this->upload('logo.svg', self::SVG)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('company_logo');
        $this->assertSame([], Storage::disk('public')->allFiles('company-logos'));
    }

    public function test_company_logo_still_accepts_raster_images(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->companyUser());

        $this->postJson('/api/company/profile/logo', ['company_logo' => UploadedFile::fake()->image('logo.png', 64, 64)])
            ->assertOk();
        $this->assertCount(1, Storage::disk('public')->allFiles('company-logos'));
    }
}
