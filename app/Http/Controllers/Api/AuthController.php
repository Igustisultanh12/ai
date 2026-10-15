<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\AuditService;
use App\Services\Auth\TotpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    protected TotpService $totpService;
    protected AuditService $auditService;

    public function __construct(TotpService $totpService, AuditService $auditService)
    {
        $this->totpService = $totpService;
        $this->auditService = $auditService;
    }

    /**
     * User registration.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'user',
            'status' => 'active',
            'email_verified_at' => now(), // Auto verify for ease in dev/testing, production uses SMTP notification
        ]);

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('auth.mfa_verified', false);

        $this->auditService->log('auth.register', $user, 'User', (string) $user->id);

        return response()->json([
            'message' => 'Pendaftaran berhasil.',
            'user' => $user->fresh(),
        ], 201);
    }

    /**
     * User login with rate limiting, session rotation, and 2FA challenge.
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            $this->auditService->log('auth.login_failed', null, 'User', null, ['email' => $credentials['email']]);
            return response()->json([
                'message' => 'Email atau password yang Anda masukkan salah.',
            ], 422);
        }

        if (!$user->isActive()) {
            return response()->json([
                'message' => 'Akun Anda dinonaktifkan oleh administrator. Hubungi tim dukungan.',
            ], 403);
        }

        Auth::login($user);
        $request->session()->regenerate();

        $isProduction = app()->environment('production');
        $hasMfa = $user->hasTwoFactorEnabled();
        $mfaRequired = $hasMfa || ($isProduction && $user->isAdmin());

        if ($mfaRequired) {
            $request->session()->put('auth.mfa_verified', false);
            return response()->json([
                'message' => 'Verifikasi autentikasi dua faktor (TOTP) diperlukan.',
                'mfa_required' => true,
                'has_2fa_setup' => $hasMfa,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
            ]);
        }

        $request->session()->put('auth.mfa_verified', true);
        $this->auditService->log('auth.login', $user, 'User', (string) $user->id);

        return response()->json([
            'message' => 'Login berhasil.',
            'mfa_required' => false,
            'user' => $user,
        ]);
    }

    /**
     * Complete MFA challenge.
     */
    public function verifyMfa(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Sesi tidak valid.'], 401);
        }

        $code = trim($validated['code']);
        $isTotpValid = $user->two_factor_secret && $this->totpService->verifyCode($user->two_factor_secret, $code);

        if ($isTotpValid) {
            $request->session()->put('auth.mfa_verified', true);
            $this->auditService->log('auth.mfa_verified_totp', $user, 'User', (string) $user->id);
            return response()->json([
                'message' => 'Verifikasi dua faktor berhasil.',
                'user' => $user,
            ]);
        }

        // Try recovery codes
        if ($user->two_factor_recovery_codes && is_array($user->two_factor_recovery_codes)) {
            [$isRecoveryValid, $remainingCodes] = $this->totpService->verifyAndConsumeRecoveryCode(
                $code,
                $user->two_factor_recovery_codes
            );

            if ($isRecoveryValid) {
                $user->update(['two_factor_recovery_codes' => $remainingCodes]);
                $request->session()->put('auth.mfa_verified', true);
                $this->auditService->log('auth.mfa_verified_recovery', $user, 'User', (string) $user->id);

                return response()->json([
                    'message' => 'Verifikasi dengan recovery code berhasil. Sisa kode: ' . count($remainingCodes),
                    'user' => $user,
                ]);
            }
        }

        return response()->json([
            'message' => 'Kode autentikasi atau recovery code tidak valid.',
        ], 422);
    }

    /**
     * User logout.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            $this->auditService->log('auth.logout', $user, 'User', (string) $user->id);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logout berhasil.']);
    }

    /**
     * Get authenticated user info.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        return response()->json([
            'user' => $user,
            'mfa_verified' => $request->session()->get('auth.mfa_verified', false),
            'has_two_factor' => $user ? $user->hasTwoFactorEnabled() : false,
        ]);
    }

    /**
     * Setup 2FA: generates secret and manual key.
     */
    public function setupMfa(Request $request): JsonResponse
    {
        $user = $request->user();
        $secret = $this->totpService->generateSecret();
        $appName = config('app.name', 'XZY');
        $otpUrl = $this->totpService->getOtpAuthUrl($appName, $user->email, $secret);

        $recovery = $this->totpService->generateRecoveryCodes();

        // Store temporary secret in session for confirmation
        $request->session()->put('auth.pending_mfa_secret', $secret);
        $request->session()->put('auth.pending_mfa_recovery', $recovery['hashed']);

        return response()->json([
            'secret' => $secret,
            'otpauth_url' => $otpUrl,
            'recovery_codes' => $recovery['plain'], // plain shown only during setup!
        ]);
    }

    /**
     * Confirm 2FA setup by validating first code.
     */
    public function confirmMfa(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = $request->user();
        $pendingSecret = $request->session()->get('auth.pending_mfa_secret');
        $pendingRecovery = $request->session()->get('auth.pending_mfa_recovery');

        if (!$pendingSecret) {
            return response()->json(['message' => 'Sesi penyiapan MFA telah kedaluwarsa. Ulangi proses.'], 400);
        }

        if (!$this->totpService->verifyCode($pendingSecret, $validated['code'])) {
            return response()->json(['message' => 'Kode TOTP salah atau tidak sinkron.'], 422);
        }

        $user->update([
            'two_factor_secret' => $pendingSecret,
            'two_factor_recovery_codes' => $pendingRecovery,
            'two_factor_confirmed_at' => now(),
        ]);

        $request->session()->forget(['auth.pending_mfa_secret', 'auth.pending_mfa_recovery']);
        $request->session()->put('auth.mfa_verified', true);

        $this->auditService->log('auth.mfa_enabled', $user, 'User', (string) $user->id);

        return response()->json([
            'message' => 'Autentikasi dua faktor berhasil diaktifkan.',
            'user' => $user->fresh(),
        ]);
    }

    /**
     * Disable 2FA (requires password confirmation).
     */
    public function disableMfa(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required'],
        ]);

        $user = $request->user();
        if (!Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Password konfirmasi salah.'], 422);
        }

        $user->update([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        $this->auditService->log('auth.mfa_disabled', $user, 'User', (string) $user->id);

        return response()->json([
            'message' => 'Autentikasi dua faktor berhasil dinonaktifkan.',
            'user' => $user->fresh(),
        ]);
    }

    /**
     * List active sessions for user.
     */
    public function activeSessions(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentSessionId = $request->session()->getId();

        $sessions = DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderBy('last_activity', 'desc')
            ->get()
            ->map(function ($s) use ($currentSessionId) {
                return [
                    'id' => $s->id,
                    'ip_address' => $s->ip_address,
                    'user_agent' => $s->user_agent,
                    'last_active' => date('Y-m-d H:i:s', $s->last_activity),
                    'is_current' => $s->id === $currentSessionId,
                ];
            });

        return response()->json(['sessions' => $sessions]);
    }

    /**
     * Revoke other active sessions.
     */
    public function revokeOtherSessions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'password' => ['required'],
        ]);

        $user = $request->user();
        if (!Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Password konfirmasi salah.'], 422);
        }

        Auth::logoutOtherDevices($validated['password']);
        $this->auditService->log('auth.revoke_other_sessions', $user, 'User', (string) $user->id);

        return response()->json(['message' => 'Semua sesi lain pada perangkat berbeda telah dikeluarkan.']);
    }

    /**
     * Update profile info.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
        ]);

        $user->update($validated);
        $this->auditService->log('user.update_profile', $user, 'User', (string) $user->id);

        return response()->json([
            'message' => 'Profil berhasil diperbarui.',
            'user' => $user->fresh(),
        ]);
    }

    /**
     * Update password with re-authentication.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $request->user();
        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json(['message' => 'Password saat ini salah.'], 422);
        }

        $user->update(['password' => Hash::make($validated['password'])]);
        $this->auditService->log('user.update_password', $user, 'User', (string) $user->id);

        return response()->json(['message' => 'Password berhasil diubah.']);
    }
}
