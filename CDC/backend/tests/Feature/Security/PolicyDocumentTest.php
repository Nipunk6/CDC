<?php

namespace Tests\Feature\Security;

use App\Models\PolicyDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * SEC-019 (P-1.12): a policy "link" document is opened by companies, so its URL must be a real https link — never
 * javascript:, data:, file: or plain http. Switching a link document to "pdf" needs a file (the old link must not be
 * kept as the PDF's address).
 */
#[Group('security')]
class PolicyDocumentTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_link_url_rejects_non_https_schemes(): void
    {
        $this->admin();
        foreach (['javascript:alert(document.cookie)', 'data:text/html,<script>alert(1)</script>', 'file:///etc/passwd', 'http://example.org/policy', 'not a url'] as $url) {
            $this->postJson('/api/admin/policy-documents', ['title' => 'Policy', 'type' => 'link', 'url' => $url])
                ->assertStatus(422)->assertJsonValidationErrors('url');
        }
        $this->assertSame(0, PolicyDocument::count());

        $this->postJson('/api/admin/policy-documents', ['title' => 'Policy', 'type' => 'link', 'url' => 'https://www.iitism.ac.in/policy'])
            ->assertCreated();

        $document = PolicyDocument::first();
        $this->putJson("/api/admin/policy-documents/{$document->id}", ['title' => 'Policy', 'type' => 'link', 'url' => 'javascript:alert(1)'])
            ->assertStatus(422)->assertJsonValidationErrors('url');
        $this->assertSame('https://www.iitism.ac.in/policy', $document->fresh()->url);
    }

    public function test_switching_a_link_to_pdf_requires_a_file(): void
    {
        $this->admin();
        $document = PolicyDocument::create(['title' => 'Policy', 'type' => 'link', 'url' => 'https://www.iitism.ac.in/policy', 'is_visible_jnf' => true, 'is_visible_inf' => true]);

        $this->putJson("/api/admin/policy-documents/{$document->id}", ['title' => 'Policy', 'type' => 'pdf'])
            ->assertStatus(422)->assertJsonValidationErrors('file');
        $this->assertSame('link', $document->fresh()->type);
    }
}
