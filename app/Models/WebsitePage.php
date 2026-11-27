<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebsitePage extends Model
{
    use HasFactory;
    use HasTranslations;

    protected $fillable = ['key', 'path', 'is_enabled', 'display_order'];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    public function translationModel(): string
    {
        return WebsitePageTranslation::class;
    }

    public function translationForeignKey(): string
    {
        return 'website_page_id';
    }

    /** @param Builder<self> $query */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    public static function isVisible(string $key): bool
    {
        $page = static::query()->where('key', $key)->first();

        // Installations that have not seeded the registry yet keep their
        // existing public pages. A seeded row is always authoritative.
        return $page === null || $page->is_enabled;
    }

    /**
     * Resolve a public navigation path to a page visibility decision. Paths
     * that are not part of the predefined page registry are left untouched.
     */
    public static function isPathVisible(string $path): bool
    {
        $normalized = '/'.ltrim((string) (parse_url($path, PHP_URL_PATH) ?: ''), '/');
        $normalized = preg_replace('#^/(ar|en)(?=/|$)#', '', $normalized) ?: '/';
        $normalized = rtrim($normalized, '/') ?: '/';

        $page = static::query()
            ->where('path', $normalized)
            ->orWhere('path', $normalized.'/')
            ->first();

        return $page === null || $page->is_enabled;
    }
}
