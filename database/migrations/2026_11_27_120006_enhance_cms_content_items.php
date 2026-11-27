<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homepage_sections', function (Blueprint $table): void {
            $table->string('page_key', 64)->default('home')->after('key');
            $table->index(['page_key', 'is_enabled']);
        });

        Schema::table('homepage_section_items', function (Blueprint $table): void {
            $table->foreignId('parent_item_id')->nullable()->after('item_id')->constrained('homepage_section_items')->nullOnDelete();
            $table->timestamp('display_start_at')->nullable()->after('is_published');
            $table->timestamp('display_end_at')->nullable()->after('display_start_at');
            $table->index(['is_published', 'display_start_at', 'display_end_at'], 'homepage_items_eligibility_index');
        });

        Schema::table('homepage_section_item_translations', function (Blueprint $table): void {
            $table->string('alt_text', 255)->nullable()->after('title');
            $table->text('summary')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('homepage_section_item_translations', function (Blueprint $table): void {
            $table->dropColumn(['alt_text', 'summary']);
        });

        Schema::table('homepage_section_items', function (Blueprint $table): void {
            $table->dropIndex('homepage_items_eligibility_index');
            $table->dropColumn(['display_start_at', 'display_end_at', 'parent_item_id']);
        });

        Schema::table('homepage_sections', function (Blueprint $table): void {
            $table->dropIndex(['page_key', 'is_enabled']);
            $table->dropColumn('page_key');
        });
    }
};
