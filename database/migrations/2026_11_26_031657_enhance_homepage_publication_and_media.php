<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add independent publication state and verified media references to the
     * existing homepage tables. No new homepage entity is introduced.
     */
    public function up(): void
    {
        Schema::table('homepage_sections', function (Blueprint $table): void {
            $table->string('status', 32)->default('published')->after('type');
            $table->timestamp('published_at')->nullable()->after('status');
            $table->index(['status', 'is_enabled', 'display_order'], 'homepage_sections_publication_index');
        });

        Schema::table('homepage_section_items', function (Blueprint $table): void {
            $table->foreignId('media_id')->nullable()->after('item_id')->constrained('media')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('homepage_section_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('media_id');
        });

        Schema::table('homepage_sections', function (Blueprint $table): void {
            $table->dropIndex('homepage_sections_publication_index');
            $table->dropColumn(['status', 'published_at']);
        });
    }
};
