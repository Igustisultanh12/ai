<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('TokenomixAI Primary');
            $table->string('provider', 50)->default('tokenomixai');
            $table->string('base_url')->default('https://api.tokenomixai.com/v1');
            $table->text('api_key_encrypted')->nullable();
            $table->string('default_model', 100)->default('glm-5.1');
            $table->json('allowed_models')->nullable();
            $table->unsignedInteger('max_output_tokens')->default(4096);
            $table->decimal('temperature', 3, 2)->default(0.70);
            $table->unsignedInteger('timeout_seconds')->default(60);
            $table->unsignedInteger('concurrency_limit')->default(5);
            $table->unsignedInteger('max_iterations')->default(5);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status', 30)->nullable();
            $table->text('last_test_message')->nullable();
            $table->timestamps();
        });

        Schema::create('model_multipliers', function (Blueprint $table) {
            $table->id();
            $table->string('model_id', 100)->unique();
            $table->decimal('multiplier', 8, 4)->default(1.0000);
            $table->string('source', 100)->default('TokenomixAI Catalog');
            $table->string('description')->nullable();
            $table->boolean('supports_tools')->default(false);
            $table->boolean('supports_vision')->default(false);
            $table->unsignedInteger('context_window')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_multipliers');
        Schema::dropIfExists('agent_configs');
    }
};
