<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('user')->after('password');
            $table->string('status', 20)->default('active')->after('role');
            $table->text('two_factor_secret')->nullable()->after('status');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            $table->unsignedBigInteger('daily_token_quota')->default(100000)->after('two_factor_confirmed_at');
            $table->unsignedBigInteger('monthly_token_quota')->default(2000000)->after('daily_token_quota');
            $table->unsignedInteger('concurrency_limit')->default(2)->after('monthly_token_quota');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'role',
                'status',
                'two_factor_secret',
                'two_factor_recovery_codes',
                'two_factor_confirmed_at',
                'daily_token_quota',
                'monthly_token_quota',
                'concurrency_limit',
            ]);
        });
    }
};
