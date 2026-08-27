<?php

namespace Tests\Feature\Phase2;

use App\Http\Controllers\App\v1\ApiController;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Phase 2 (Task 20, response security): several hand-built user-response arrays across the customer and
 * delivery-boy app APIs (login, register/social-login's shared getUserDataArray(), profile update) included
 * the account's password hash and its forgotten-password/activation/remember-me tokens directly in the JSON
 * response - confirmed via a direct read of App\v1\ApiController's login() and getUserDataArray(), and
 * Delivery_boy\v1\ApiController's login()-equivalents. None of these are IDOR (the row is always the
 * authenticated caller's own account), but the exposure is still real: anything that can read the response
 * later (access/proxy logs, a browser extension, a shared device, a MITM on a misconfigured non-HTTPS
 * deployment) gets a live password-reset token or an offline-crackable hash. Fixed with
 * redactSensitiveUserFields() (app/function_helper.php), wired into every hand-built array found.
 */
class SensitiveFieldRedactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_redact_blanks_every_sensitive_key_present(): void
    {
        $data = redactSensitiveUserFields([
            'username' => 'alice',
            'password' => '$2y$10$abcdefghijklmnopqrstuv',
            'activation_selector' => 'sel-1',
            'activation_code' => 'code-1',
            'forgotten_password_selector' => 'sel-2',
            'forgotten_password_code' => 'code-2',
            'forgotten_password_time' => '2026-01-01',
            'remember_selector' => 'sel-3',
            'remember_code' => 'code-3',
        ]);

        $this->assertSame('alice', $data['username'], 'Non-sensitive fields must be left untouched.');
        foreach (['password', 'activation_selector', 'activation_code', 'forgotten_password_selector', 'forgotten_password_code', 'forgotten_password_time', 'remember_selector', 'remember_code'] as $key) {
            $this->assertSame('', $data[$key], "Expected \"$key\" to be redacted.");
        }
    }

    public function test_redact_does_not_add_keys_that_were_not_present(): void
    {
        $data = redactSensitiveUserFields(['username' => 'alice']);

        $this->assertSame(['username' => 'alice'], $data);
    }

    public function test_login_response_does_not_leak_the_password_hash_or_reset_tokens(): void
    {
        $user = User::forceCreate([
            'username' => 'customer_' . uniqid(),
            'mobile' => (string) random_int(6000000000, 6999999999),
            'password' => bcrypt('correct-password'),
            'disk' => 'public',
            'serviceable_cities' => '',
            'type' => 'phone',
            'role_id' => Role::CUSTOMER,
            'activation_code' => 'live-activation-code',
            'forgotten_password_code' => 'live-reset-token',
        ]);

        $request = new Request([
            'mobile' => $user->mobile,
            'password' => 'correct-password',
        ]);

        $response = app(ApiController::class)->login($request);
        $data = json_decode($response->getContent(), true);

        $this->assertFalse($data['error']);
        $this->assertSame('', $data['user']['activation_code']);
        $this->assertSame('', $data['user']['forgotten_password_code']);
        $this->assertSame('', $data['user']['remember_code']);
        $this->assertArrayNotHasKey('password', $data['user'] ?? [], 'The login response must never include a password field at all.');
    }
}
