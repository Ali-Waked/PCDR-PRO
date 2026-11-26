<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sessions')) {
            return;
        }

        Schema::table('sessions', function (Blueprint $table): void {
            if (! Schema::hasColumn('sessions', 'public_reference')) {
                $table->string('public_reference', 32)->nullable()->unique();
            }

            if (! Schema::hasColumn('sessions', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sessions')) {
            return;
        }

        Schema::table('sessions', function (Blueprint $table): void {
            if (Schema::hasColumn('sessions', 'public_reference')) {
                $table->dropUnique(['public_reference']);
                $table->dropColumn('public_reference');
            }

            if (Schema::hasColumn('sessions', 'created_at')) {
                $table->dropColumn('created_at');
            }
        });
    }
};
