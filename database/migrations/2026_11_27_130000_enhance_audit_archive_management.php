<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_archives', function (Blueprint $table): void {
            $table->string('source', 20)->default('manual')->after('status');
            $table->string('format', 10)->default('zip')->after('source');
            $table->string('operation_key', 64)->nullable()->unique()->after('format');
            $table->timestamp('queued_at')->nullable()->after('attempts');
            $table->timestamp('started_at')->nullable()->after('queued_at');
            $table->timestamp('finished_at')->nullable()->after('started_at');
            $table->timestamp('failed_at')->nullable()->after('finished_at');
        });

        Schema::create('audit_archive_status_transitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('audit_archive_id')->constrained('audit_archives')->cascadeOnDelete();
            $table->string('from_status', 20);
            $table->string('to_status', 20);
            $table->string('actor_type', 20)->default('system');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['audit_archive_id', 'created_at']);
            $table->index(['to_status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_archive_status_transitions');

        Schema::table('audit_archives', function (Blueprint $table): void {
            $table->dropUnique(['operation_key']);
            $table->dropColumn([
                'source',
                'format',
                'operation_key',
                'queued_at',
                'started_at',
                'finished_at',
                'failed_at',
            ]);
        });
    }
};
