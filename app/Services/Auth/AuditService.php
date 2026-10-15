<?php

namespace App\Services\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Request;

class AuditService
{
    private const REDACTED_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'api_key',
        'two_factor_secret',
        'recovery_code',
        'secret',
        'authorization',
        'token_encrypted',
        'api_key_encrypted',
        'content',
        'prompt',
        'completion',
        'messages',
    ];

    /**
     * Record an audit event with sanitization and correlation ID.
     */
    public function log(
        string $action,
        ?User $user = null,
        ?string $targetType = null,
        ?string $targetId = null,
        ?array $metadata = null
    ): AuditLog {
        $actorId = $user ? $user->id : (auth()->id() ?? null);
        $correlationId = request()?->header('X-Correlation-ID') ?? app()->bound('correlation_id') ? app('correlation_id') : null;

        $sanitizedMetadata = $metadata ? $this->redactSensitive($metadata) : null;

        return AuditLog::create([
            'user_id' => $actorId,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'ip_address' => Request::ip() ?? '127.0.0.1',
            'user_agent' => substr(Request::userAgent() ?? 'CLI/System', 0, 500),
            'correlation_id' => $correlationId,
            'metadata' => $sanitizedMetadata,
            'created_at' => now(),
        ]);
    }

    /**
     * Recursively redact sensitive fields from metadata.
     */
    public function redactSensitive(array $data): array
    {
        $sanitized = [];
        foreach ($data as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            if (in_array($normalizedKey, self::REDACTED_KEYS, true) || str_contains($normalizedKey, 'secret') || str_contains($normalizedKey, 'token') || str_contains($normalizedKey, 'password')) {
                $sanitized[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->redactSensitive($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }
}
