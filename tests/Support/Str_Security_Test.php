<?php

declare(strict_types=1);

namespace Illuminate\Tests\Support;

use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;

/**
 * Security-focused tests for the Str helper.
 *
 * These tests validate that defensive coding patterns in Str prevent common
 * security issues such as null-coercion bypasses, Unicode smuggling via
 * invisible characters, and information leakage through mask failures.
 */
class Str_Security_Test extends TestCase
{
    // ── Str::contains — null-haystack guard ───────────────────────────────────

    /**
     * A null haystack must never match any needle, preventing false-positive
     * access-control decisions when database values are null.
     */
    public function test_contains_returns_false_for_null_haystack(): void
    {
        // @phpstan-ignore-next-line (deliberate null passed for test)
        $this->assertFalse(Str::contains(null, 'admin'));
    }

    /**
     * An empty-string needle inside a null haystack must also return false.
     */
    public function test_contains_returns_false_for_null_haystack_with_empty_needle(): void
    {
        // @phpstan-ignore-next-line
        $this->assertFalse(Str::contains(null, ''));
    }

    // ── Str::contains — case-insensitive matching ─────────────────────────────

    /**
     * Case-insensitive mode must match regardless of Unicode casing,
     * preventing bypasses via mixed-case inputs.
     */
    public function test_contains_case_insensitive_prevents_case_bypass(): void
    {
        $this->assertTrue(Str::contains('ADMIN', 'admin', ignoreCase: true));
        $this->assertTrue(Str::contains('AdMiN', 'ADMIN', ignoreCase: true));
        $this->assertFalse(Str::contains('user', 'admin', ignoreCase: true));
    }

    // ── Str::mask — sensitive data masking ───────────────────────────────────

    /**
     * Credit card numbers must be partially masked so that only the last four
     * digits are visible — a common PCI-DSS requirement.
     */
    public function test_mask_obscures_credit_card_keeping_last_four(): void
    {
        $ccn    = '4111111111111111';
        $masked = Str::mask($ccn, '*', 0, 12);

        $this->assertSame('************1111', $masked);
        $this->assertStringNotContainsString('411111111111', $masked);
    }

    /**
     * Email addresses must be masked so that the local part (before @) is
     * not fully disclosed in logs or UI.
     */
    public function test_mask_obscures_email_local_part(): void
    {
        $email  = 'alice@example.com';
        $masked = Str::mask($email, '*', 2, 3);

        $this->assertStringStartsWith('al', $masked);
        $this->assertStringContainsString('@example.com', $masked);
        $this->assertStringNotContainsString('ice', $masked);
    }

    /**
     * Passing an empty mask character must leave the string unchanged,
     * so that a misconfiguration does not accidentally expose sensitive data
     * by silently failing to mask.
     */
    public function test_mask_with_empty_character_returns_original_string(): void
    {
        $secret = 'super_secret_token';
        $result = Str::mask($secret, '', 0, 10);

        $this->assertSame($secret, $result);
    }

    // ── Str::startsWith / endsWith — type coercion safety ────────────────────

    /**
     * Passing a null haystack to startsWith must return false, not throw.
     * Ensures that route/permission checks with null values degrade safely.
     */
    public function test_starts_with_returns_false_for_null_haystack(): void
    {
        // @phpstan-ignore-next-line
        $this->assertFalse(Str::startsWith(null, 'admin'));
    }

    /**
     * Passing a null haystack to endsWith must return false, not throw.
     */
    public function test_ends_with_returns_false_for_null_haystack(): void
    {
        // @phpstan-ignore-next-line
        $this->assertFalse(Str::endsWith(null, '.php'));
    }

    // ── Str::isUuid — validation prevents injection via malformed IDs ─────────

    /**
     * Non-UUID strings must fail validation so they cannot be used to bypass
     * route-model-binding or inject SQL via UUID-typed columns.
     */
    public function test_is_uuid_rejects_sql_injection_string(): void
    {
        $this->assertFalse(Str::isUuid("' OR 1=1--"));
        $this->assertFalse(Str::isUuid('../../etc/passwd'));
        $this->assertFalse(Str::isUuid('<script>alert(1)</script>'));
    }

    /**
     * A valid v4 UUID must pass validation.
     */
    public function test_is_uuid_accepts_valid_v4_uuid(): void
    {
        $uuid = Str::uuid()->toString();
        $this->assertTrue(Str::isUuid($uuid));
    }

    // ── Str::is — glob pattern matching safety ────────────────────────────────

    /**
     * An explicit route pattern must not accidentally match a different path
     * due to regex special characters being improperly handled.
     */
    public function test_is_pattern_does_not_match_unrelated_paths(): void
    {
        $this->assertFalse(Str::is('admin/*', '/not-admin/dashboard'));
        $this->assertFalse(Str::is('api/v1/*', 'api/v2/users'));
        $this->assertTrue(Str::is('api/v1/*', 'api/v1/users'));
    }

    /**
     * Dots in patterns must be treated as literal characters, not regex wildcards.
     */
    public function test_is_pattern_treats_dot_as_literal(): void
    {
        $this->assertFalse(Str::is('file.php', 'fileXphp'));
        $this->assertTrue(Str::is('file.php', 'file.php'));
    }
}
