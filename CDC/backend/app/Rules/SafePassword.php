<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * SEC-012: refuses a password that is one of the 10,000 most common ones (also with digits or symbols put before or
 * after it, like "Welcome123"), or that contains the person's own name, roll number or the part of their email
 * before the "@". Used on every path that sets a password, next to the length / letters / numbers rule.
 */
final class SafePassword implements ValidationRule
{
    private const MIN_PERSONAL_LENGTH = 3;

    private const MIN_BASE_LENGTH = 4;

    /** @var array<string, true>|null */
    private static ?array $common = null;

    /** @var list<string> */
    private array $personal;

    /**
     * @param  array<int, string|null>  $personal  the person's name, roll number and email address, as available
     */
    public function __construct(array $personal = [])
    {
        $this->personal = self::personalTokens($personal);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $lower = mb_strtolower($value);
        $base = preg_replace('/^[^a-z]+|[^a-z]+$/', '', $lower) ?? '';

        if (self::isCommon($lower) || (mb_strlen($base) >= self::MIN_BASE_LENGTH && self::isCommon($base))) {
            $fail('This password is too common. Choose one that is harder to guess.');

            return;
        }

        $compact = preg_replace('/[^a-z0-9]/', '', $lower) ?? '';
        foreach ($this->personal as $token) {
            if (str_contains($compact, $token)) {
                $fail('The password must not contain your name, roll number or email address.');

                return;
            }
        }
    }

    private static function isCommon(string $candidate): bool
    {
        if (self::$common === null) {
            $lines = @file(resource_path('security/common-passwords.txt'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            self::$common = array_fill_keys(array_map('mb_strtolower', $lines), true);
        }

        return isset(self::$common[$candidate]);
    }

    /**
     * Lower-case letter/digit pieces of the name, roll number and email local part that are long enough to matter.
     *
     * @param  array<int, string|null>  $values
     * @return list<string>
     */
    private static function personalTokens(array $values): array
    {
        $tokens = [];
        foreach ($values as $value) {
            $value = mb_strtolower(trim((string) $value));
            if ($value === '') {
                continue;
            }
            if (str_contains($value, '@')) {
                $value = explode('@', $value)[0];
            }
            // The whole value without separators ("ashaverma", "22je0003") and each of its words ("asha", "verma").
            $pieces = array_merge([preg_replace('/[^a-z0-9]/', '', $value) ?? ''], preg_split('/[^a-z0-9]+/', $value) ?: []);
            foreach ($pieces as $piece) {
                if (mb_strlen($piece) >= self::MIN_PERSONAL_LENGTH) {
                    $tokens[$piece] = true;
                }
            }
        }

        return array_keys($tokens);
    }
}
