<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('path', 128);
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['is_enabled', 'display_order']);
        });

        Schema::create('website_page_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_page_id')->constrained('website_pages')->cascadeOnDelete();
            $table->string('locale', 8);
            $table->string('name', 160)->nullable();
            $table->string('seo_title', 200)->nullable();
            $table->text('seo_description')->nullable();
            $table->timestamps();

            $table->unique(['website_page_id', 'locale']);
        });

        Schema::create('website_organizations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('logo_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('destination_url', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('website_organization_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_organization_id')->constrained('website_organizations')->cascadeOnDelete();
            $table->string('locale', 8);
            $table->string('title', 255)->nullable();
            $table->text('description')->nullable();
            $table->string('vision_title', 160)->nullable();
            $table->text('vision')->nullable();
            $table->string('mission_title', 160)->nullable();
            $table->text('mission')->nullable();
            $table->timestamps();

            $table->unique(['website_organization_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_organization_translations');
        Schema::dropIfExists('website_organizations');
        Schema::dropIfExists('website_page_translations');
        Schema::dropIfExists('website_pages');
    }
};
