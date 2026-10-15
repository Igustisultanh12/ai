<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Auth\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BootstrapAdminCommand extends Command
{
    protected $signature = 'xzy:admin:bootstrap 
                            {--email= : The email address of the administrator}
                            {--name= : The name of the administrator}
                            {--password= : The administrator password (will prompt securely if not provided)}';

    protected $description = 'Bootstrap or promote an administrative account securely without hardcoded credentials.';

    public function handle(AuditService $auditService): int
    {
        $this->info('=== XZY Platform Admin Bootstrap ===');

        $email = $this->option('email') ?: $this->ask('Masukkan alamat email administrator:');
        while (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Format email tidak valid.');
            $email = $this->ask('Masukkan alamat email administrator yang valid:');
        }

        $name = $this->option('name') ?: $this->ask('Masukkan nama administrator:', 'Administrator XZY');

        $password = $this->option('password');
        if (empty($password)) {
            if ($this->confirm('Apakah Anda ingin sistem membuatkan password aman secara acak?', true)) {
                $password = Str::password(16, true, true, true, false);
                $this->warn("Password acak dibuat: {$password}");
                $this->line('Harap simpan password ini dengan aman!');
            } else {
                $password = $this->secret('Masukkan password (minimal 8 karakter):');
                while (strlen($password) < 8) {
                    $this->error('Password minimal harus 8 karakter.');
                    $password = $this->secret('Masukkan password yang memenuhi syarat:');
                }
            }
        }

        $user = User::where('email', $email)->first();

        if ($user) {
            $this->warn("User dengan email {$email} sudah ada. Memperbarui hak akses menjadi administrator...");
            $user->update([
                'name' => $name ?: $user->name,
                'password' => Hash::make($password),
                'role' => 'admin',
                'status' => 'active',
                'email_verified_at' => $user->email_verified_at ?: now(),
            ]);
            $action = 'user.bootstrap_promoted_admin';
        } else {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'role' => 'admin',
                'status' => 'active',
                'email_verified_at' => now(),
                'daily_token_quota' => 10000000,
                'monthly_token_quota' => 50000000,
                'concurrency_limit' => 10,
            ]);
            $action = 'user.bootstrap_created_admin';
        }

        $auditService->log($action, $user, 'User', (string) $user->id, [
            'email' => $email,
            'role' => 'admin',
        ]);

        $this->info("✓ Administrator berhasil dikonfigurasi: {$user->email} [ID: {$user->id}]");
        return self::SUCCESS;
    }
}
