<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->string('category', 48)->index();
            $table->string('subject');
            $table->text('description');
            $table->string('locale', 5)->default('ar');
            $table->string('related_project')->nullable();
            $table->string('location')->nullable();
            $table->string('status', 32)->default('new')->index();
            $table->string('priority', 16)->default('normal')->index();
            $table->boolean('is_anonymous')->default(false);
            $table->string('complainant_name')->nullable();
            $table->string('preferred_contact_method', 24)->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('preferred_locale', 5)->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_details')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'priority']);
            $table->index(['created_at', 'status']);
        });

        Schema::create('complaint_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('complaint_id')->constrained('complaints')->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['complaint_id', 'created_at']);
        });

        Schema::create('complaint_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('complaint_id')->constrained('complaints')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->boolean('is_internal')->default(true);
            $table->timestamps();

            $table->index(['complaint_id', 'created_at']);
        });

        Schema::create('complaint_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('complaint_id')->constrained('complaints')->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->timestamps();

            $table->unique(['complaint_id', 'media_id']);
        });

        Schema::create('complaint_reference_sequences', function (Blueprint $table): void {
            $table->unsignedSmallInteger('year')->primary();
            $table->unsignedBigInteger('next_value')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_reference_sequences');
        Schema::dropIfExists('complaint_attachments');
        Schema::dropIfExists('complaint_notes');
        Schema::dropIfExists('complaint_status_histories');
        Schema::dropIfExists('complaints');
    }
};
