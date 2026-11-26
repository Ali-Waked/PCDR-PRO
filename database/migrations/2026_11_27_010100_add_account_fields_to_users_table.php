<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'avatar_media_id')) {
                $table->foreignId('avatar_media_id')
                    ->nullable()
                    ->constrained('media')
                    ->nullOnDelete()
                    ->after('locale');
            }

            if (! Schema::hasColumn('users', 'last_activity_at')) {
                $table->timestamp('last_activity_at')->nullable()->after('last_login_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'avatar_media_id')) {
                $table->dropConstrainedForeignId('avatar_media_id');
            }

            if (Schema::hasColumn('users', 'last_activity_at')) {
                $table->dropColumn('last_activity_at');
            }
        });
    }
};
