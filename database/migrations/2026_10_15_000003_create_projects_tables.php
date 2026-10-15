<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->index();
            $table->text('description')->nullable();
            $table->string('template', 50)->default('laravel-vue3');
            $table->string('visibility', 20)->default('private');
            $table->string('status', 20)->default('active');
            $table->string('github_repo')->nullable();
            $table->string('github_branch')->default('main');
            $table->boolean('auto_push_github')->default(false);
            $table->uuid('active_checkpoint_id')->nullable();
            $table->timestamps();
        });

        Schema::create('project_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('path', 1000);
            $table->longText('content')->nullable();
            $table->unsignedInteger('size_bytes')->default(0);
            $table->boolean('is_binary')->default(false);
            $table->string('mime_type', 100)->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->timestamps();

            $table->index(['project_id', 'path']);
        });

        Schema::create('project_checkpoints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('commit_message');
            $table->longText('snapshot');
            $table->unsignedInteger('file_count')->default(0);
            $table->string('build_status', 30)->nullable();
            $table->string('tests_status', 30)->nullable();
            $table->string('github_commit_sha', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_checkpoints');
        Schema::dropIfExists('project_files');
        Schema::dropIfExists('projects');
    }
};
