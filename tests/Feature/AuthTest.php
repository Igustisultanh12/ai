<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Auth\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('user.email', 'john@example.com')
            ->assertJsonPath('user.role', 'user');

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'role' => 'user',
        ]);
    }

    public function test_user_can_login_with_correct_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('SecurePass123!'),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'SecurePass123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('mfa_required', false)
            ->assertJsonPath('user.email', 'test@example.com');
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('SecurePass123!'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['message']);
    }

    public function test_suspended_user_cannot_login(): void
    {
        User::factory()->create([
            'email' => 'suspended@example.com',
            'password' => bcrypt('SecurePass123!'),
            'status' => 'suspended',
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'suspended@example.com',
            'password' => 'SecurePass123!',
        ]);

        $response->assertStatus(403);
    }

    public function test_totp_2fa_setup_and_verification_flow(): void
    {
        $user = User::factory()->create([
            'email' => 'totp@example.com',
            'password' => bcrypt('SecurePass123!'),
            'status' => 'active',
        ]);

        $this->actingAs($user);

        // 1. Setup MFA
        $setupRes = $this->postJson('/api/auth/mfa/setup');
        $setupRes->assertStatus(200)
            ->assertJsonStructure(['secret', 'otpauth_url', 'recovery_codes']);

        $secret = $setupRes->json('secret');
        $recoveryCodes = $setupRes->json('recovery_codes');
        $this->assertCount(8, $recoveryCodes);

        // 2. Generate valid TOTP code
        $totpService = new TotpService();
        $code = $totpService->calculateTotp($secret, (int) floor(time() / 30));

        // 3. Confirm MFA
        $confirmRes = $this->postJson('/api/auth/mfa/confirm', ['code' => $code]);
        $confirmRes->assertStatus(200);

        $user->refresh();
        $this->assertTrue($user->hasTwoFactorEnabled());

        // 4. Test login with 2FA enabled
        $loginRes = $this->postJson('/api/auth/login', [
            'email' => 'totp@example.com',
            'password' => 'SecurePass123!',
        ]);

        $loginRes->assertStatus(200)
            ->assertJsonPath('mfa_required', true);

        // 5. Complete 2FA verification using a recovery code
        $mfaRes = $this->postJson('/api/auth/mfa/verify', [
            'code' => $recoveryCodes[0],
        ]);

        $mfaRes->assertStatus(200);
        $user->refresh();
        $this->assertCount(7, $user->two_factor_recovery_codes);
    }
}
