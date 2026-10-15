<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('token_ledgers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained('projects')->cascadeOnDelete();
            $table->foreignUuid('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
            $table->string('run_id')->nullable()->index();
            $table->string('idempotency_key', 100)->unique();
            $table->string('correlation_id', 100)->nullable()->index();
            $table->string('provider', 50)->default('tokenomixai');
            $table->string('model', 100);
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('raw_tokens')->default(0);
            $table->decimal('multiplier_snapshot', 8, 4)->default(1.0000);
            $table->decimal('weighted_tokens', 14, 4)->default(0.0000);
            $table->string('data_source', 30)->default('actual'); // actual, estimated, unknown
            $table->string('status', 30)->default('success'); // success, failed, cancelled
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedInteger('attempt_count')->default(1);
            $table->string('error_code', 50)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('project_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30)->default('vibecoding');
            $table->string('status', 30)->default('queued'); // queued, running, completed, failed, cancelled
            $table->text('plan')->nullable();
            $table->json('proposed_patches')->nullable();
            $table->longText('build_log')->nullable();
            $table->longText('test_log')->nullable();
            $table->unsignedInteger('iterations_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_runs');
        Schema::dropIfExists('token_ledgers');
    }
};
