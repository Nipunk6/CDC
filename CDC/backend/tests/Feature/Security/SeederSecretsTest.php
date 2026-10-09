<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Database\Seeders\CompanySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * P-1.11: CompanySeeder carried a hard-coded password (the same weak value as an admin password, SEC_PROGRESS D-08).
 * Demo company users now get DEMO_COMPANY_PASSWORD, or a random password when it is not set.
 */
#[Group('security')]
class SeederSecretsTest extends TestCase
{
    use RefreshDatabase;

    private function setEnv(?string $value): void
    {
        foreach (['_ENV', '_SERVER'] as $global) {
            if ($value === null) {
                unset($GLOBALS[$global]['DEMO_COMPANY_PASSWORD']);
            } else {
                $GLOBALS[$global]['DEMO_COMPANY_PASSWORD'] = $value;
            }
        }
        putenv($value === null ? 'DEMO_COMPANY_PASSWORD' : "DEMO_COMPANY_PASSWORD={$value}");
    }

    protected function tearDown(): void
    {
        $this->setEnv(null);
        parent::tearDown();
    }

    public function test_company_seeder_source_has_no_password_literal(): void
    {
        $source = file_get_contents(database_path('seeders/CompanySeeder.php'));

        $this->assertDoesNotMatchRegularExpression("/'password'\\s*=>\\s*['\"]/", $source);
    }

    public function test_company_seeder_uses_the_env_password_or_a_random_one(): void
    {
        $this->setEnv(null);
        $this->seed(CompanySeeder::class);
        $users = User::where('role', 'company')->get();
        $this->assertCount(2, $users);
        foreach ($users as $user) {
            $this->assertFalse(Hash::check('', $user->password));
        }

        $this->setEnv('Demo-Company-Pass-9');
        $this->seed(CompanySeeder::class);
        foreach (User::where('role', 'company')->get() as $user) {
            $this->assertTrue(Hash::check('Demo-Company-Pass-9', $user->password));
        }
    }
}
