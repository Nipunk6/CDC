<?php

namespace Tests\Feature\Security;

use App\Models\Company;
use App\Models\Inf;
use App\Models\Jnf;
use App\Models\PolicyDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * SEC-022 / QA F-040 (P-1.12): another company's JNF/INF and one that does not exist must look exactly the same —
 * same status (404) and same body — whatever the request body, on update, autosave and read. The admin
 * policy-documents resource has no `show`, so it must not crash.
 */
#[Group('security')]
class ExistenceOracleTest extends TestCase
{
    use RefreshDatabase;

    private function company(string $name): Company
    {
        return Company::create(['name' => $name, 'hr_name' => 'HR', 'hr_email' => strtolower($name).'@co.qa.test']);
    }

    /** @return array{0: int, 1: string} status and body */
    private function probe(string $method, string $uri, array $body = []): array
    {
        $response = $this->json($method, $uri, $body);

        return [$response->status(), $response->getContent()];
    }

    public function test_other_tenant_and_missing_forms_are_indistinguishable(): void
    {
        $a = $this->company('Alpha');
        $b = $this->company('Beta');
        $jnf = Jnf::create(['company_id' => $a->id, 'job_title' => 'E', 'job_description' => 'D', 'status' => 'draft']);
        $inf = Inf::create(['company_id' => $a->id, 'internship_title' => 'I', 'internship_description' => 'D', 'status' => 'draft']);
        Sanctum::actingAs(User::factory()->create(['role' => 'company', 'company_id' => $b->id]));
        $missing = 999999;

        foreach ([
            ['PUT', 'jnfs', $jnf->id, []],
            ['PUT', 'jnfs', $jnf->id, ['job_title' => 'x', 'job_description' => 'y']],
            ['PATCH', 'jnfs', $jnf->id, []],
            ['GET', 'jnfs', $jnf->id, []],
            ['DELETE', 'jnfs', $jnf->id, []],
            ['PUT', 'infs', $inf->id, []],
            ['PATCH', 'infs', $inf->id, []],
            ['GET', 'infs', $inf->id, []],
        ] as [$method, $kind, $id, $body]) {
            $theirs = $this->probe($method, "/api/company/{$kind}/{$id}", $body);
            $none = $this->probe($method, "/api/company/{$kind}/{$missing}", $body);
            $this->assertSame(404, $theirs[0], "{$method} {$kind} of another company");
            $this->assertSame($theirs, $none, "{$method} {$kind}: another company's form and a missing one must look the same");
        }

        foreach (['jnfs' => ['job_title' => 'x', 'job_description' => 'y'], 'infs' => ['internship_title' => 'x', 'internship_description' => 'y']] as $kind => $body) {
            $owned = $kind === 'jnfs' ? $jnf->id : $inf->id;
            $theirs = $this->probe('POST', "/api/company/{$kind}/autosave", $body + ['id' => $owned]);
            $none = $this->probe('POST', "/api/company/{$kind}/autosave", $body + ['id' => $missing]);
            $this->assertSame(404, $theirs[0], "autosave {$kind}");
            $this->assertSame($theirs, $none, "autosave {$kind}: same response for another company's id and a missing id");
        }

        $this->assertSame('E', $jnf->fresh()->job_title);
        $this->assertSame('I', $inf->fresh()->internship_title);
    }

    public function test_admin_policy_document_has_no_show_route_and_never_500s(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $document = PolicyDocument::create(['title' => 'Policy', 'type' => 'link', 'url' => 'https://www.iitism.ac.in/p', 'is_visible_jnf' => true, 'is_visible_inf' => true]);

        $status = $this->getJson("/api/admin/policy-documents/{$document->id}")->status();
        $this->assertLessThan(500, $status);
        $this->assertNotSame(200, $status);
    }
}
