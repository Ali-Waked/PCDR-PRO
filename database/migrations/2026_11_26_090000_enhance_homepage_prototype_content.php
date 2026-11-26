<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive schema support for the approved source-homepage prototype.
 *
 * Existing columns are never changed or removed. All new columns are nullable
 * (or carry an inert default) so legacy homepage content keeps working.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homepage_section_translations', function (Blueprint $table): void {
            $table->string('ticker_label')->nullable()->after('cta_link');
            $table->json('tab_labels')->nullable()->after('ticker_label');
            $table->string('video_url')->nullable()->after('tab_labels');
            $table->string('video_caption')->nullable()->after('video_url');
        });

        Schema::table('homepage_section_items', function (Blueprint $table): void {
            $table->string('tab', 32)->nullable()->after('item_key');
            $table->boolean('is_featured')->default(false)->after('tab');
            $table->string('motif_key', 32)->nullable()->after('is_featured');
        });

        Schema::table('homepage_section_item_translations', function (Blueprint $table): void {
            $table->string('date_label')->nullable()->after('display_value');
            $table->string('cta_label')->nullable()->after('date_label');
            $table->text('caption')->nullable()->after('cta_label');
            $table->string('unit', 64)->nullable()->after('caption');
        });
    }

    public function down(): void
    {
        Schema::table('homepage_section_item_translations', function (Blueprint $table): void {
            $table->dropColumn(['date_label', 'cta_label', 'caption', 'unit']);
        });

        Schema::table('homepage_section_items', function (Blueprint $table): void {
            $table->dropColumn(['tab', 'is_featured', 'motif_key']);
        });

        Schema::table('homepage_section_translations', function (Blueprint $table): void {
            $table->dropColumn(['ticker_label', 'tab_labels', 'video_url', 'video_caption']);
        });
    }
};
