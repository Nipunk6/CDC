<?php

namespace Tests\Feature\QA;

use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Regression for QA F-001: the tracked `.env.example` ships placeholders only — no key, no mail or admin password,
 * debug off.
 */
#[Group('qa')]
class FixF001EnvExampleTest extends TestCase
{
    public function test_env_example_carries_no_secrets_and_safe_defaults(): void
    {
        $env = [];
        foreach (file(base_path('.env.example'), FILE_IGNORE_NEW_LINES) as $line) {
            if (preg_match('/^([A-Z0-9_]+)=(.*)$/', $line, $m)) {
                $env[$m[1]] = trim($m[2], '"');
            }
        }

        $this->assertSame('', $env['APP_KEY'] ?? null, 'APP_KEY must be generated per install');
        $this->assertSame('false', $env['APP_DEBUG'] ?? null);
        $this->assertSame('', $env['MAIL_PASSWORD'] ?? null, 'no mail password in a tracked file');
        $this->assertSame('', $env['ADMIN_PASSWORD'] ?? null, 'no admin password in a tracked file');
        $this->assertStringEndsWith('@example.com', $env['MAIL_USERNAME'] ?? '');
    }
}
