<?php

namespace Tests\Feature\Security;

use App\Models\Company;
use App\Models\Inf;
use App\Models\Jnf;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * SEC-006 (P-1.5): the original one-row JNF/INF CSV downloads must not let company-typed values run as spreadsheet
 * formulas. Any cell starting with =, +, -, @, a tab or a carriage return gets a leading apostrophe. The column layout
 * is unchanged (Q8.3).
 */
#[Group('security')]
class ExportInjectionTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<list<string|null>> */
    private function csv(string $uri): array
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $this->get($uri)->assertOk()->streamedContent());
        $handle = fopen('php://memory', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        $rows = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
            if ($row !== [null]) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /** @param list<list<string|null>> $rows @return array<string, string|null> header => value */
    private function row(array $rows): array
    {
        return array_combine($rows[0], $rows[1]);
    }

    public function test_phase1_csv_neutralises_formula_prefixes(): void
    {
        $company = Company::create(['name' => '@SUM(1)', 'hr_name' => '=HYPERLINK("http://evil.example","x")', 'hr_email' => 'hr@evil.qa.test']);
        $jnf = Jnf::create([
            'company_id' => $company->id, 'job_title' => '=1+1', 'job_description' => '+cmd|/C calc', 'status' => 'accepted',
            'form_data' => ['jobTitle' => '=1+1', 'jobLocation' => "\t=2+2", 'jobDescription' => '-2+3'],
        ]);
        $inf = Inf::create([
            'company_id' => $company->id, 'internship_title' => '@cmd', 'internship_description' => 'safe text', 'status' => 'accepted',
            'form_data' => ['internshipTitle' => '@cmd', 'internshipLocation' => "\r=1"],
        ]);

        foreach (["/api/admin/jnfs/{$jnf->id}/csv", "/api/admin/infs/{$inf->id}/csv"] as $uri) {
            $rows = $this->csv($uri);
            $this->assertCount(2, $rows, $uri);
            foreach ($rows[1] as $i => $cell) {
                $this->assertFalse(
                    $cell !== null && $cell !== '' && in_array($cell[0], ['=', '+', '-', '@', "\t", "\r"], true),
                    sprintf('%s column "%s" starts with a formula character: %s', $uri, $rows[0][$i], json_encode($cell))
                );
            }
            $values = $this->row($rows);
            $this->assertSame("'@SUM(1)", $values['company_name'] ?? null, $uri);
        }

        $jnfRow = $this->row($this->csv("/api/admin/jnfs/{$jnf->id}/csv"));
        $this->assertContains("'=1+1", $jnfRow, 'the job title is kept, neutralised with an apostrophe');
        $this->assertContains("'-2+3", $jnfRow, 'the description comes from form_data and is neutralised too');
    }

    public function test_ordinary_values_are_unchanged(): void
    {
        $company = Company::create(['name' => 'Acme Ltd', 'hr_name' => 'Asha', 'hr_email' => 'hr@acme.qa.test']);
        $jnf = Jnf::create([
            'company_id' => $company->id, 'job_title' => 'SDE (C++)', 'job_description' => 'Build things', 'status' => 'accepted',
            'form_data' => ['jobTitle' => 'SDE (C++)'],
        ]);

        $row = $this->row($this->csv("/api/admin/jnfs/{$jnf->id}/csv"));
        $this->assertSame('Acme Ltd', $row['company_name'] ?? null);
        $this->assertContains('SDE (C++)', $row);
    }
}
