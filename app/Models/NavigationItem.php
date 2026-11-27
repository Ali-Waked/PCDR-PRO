<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NavigationItem extends Model
{
    use HasFactory;
    use HasTranslations;

    protected $fillable = [
        'navigation_menu_id', 'parent_id', 'link_type', 'link_value',
        'is_enabled', 'display_order', 'icon',
    ];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean', 'display_order' => 'integer'];
    }

    public function translationModel(): string
    {
        return NavigationItemTranslation::class;
    }

    public function translationForeignKey(): string
    {
        return 'navigation_item_id';
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(NavigationMenu::class, 'navigation_menu_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('display_order');
    }

    /**
     * Resolve an internal route key to a locale-aware URL, or return a vetted
     * external URL. Never returns user-controlled JavaScript/data URLs.
     */
    public function resolvedUrl(string $locale): string
    {
        if ($this->link_type === 'external') {
            $value = (string) $this->link_value;

            return str_starts_with($value, 'https://') ? $value : '#';
        }

        $key = (string) $this->link_value;
        // Menu keys may use the shorter public wording while the predefined
        // page registry keeps its stable structural key. Legacy child keys are
        // kept as aliases so existing database rows also resolve to the new
        // independent pages instead of aggregate-page fragments.
        $aliases = [
            'programs' => 'areas_of_work',
            'media' => 'media_center',
            'vision' => 'about_vision',
            'objectives' => 'about_objectives',
            'achievements' => 'about_achievements',
            'board' => 'about_board',
            'news' => 'media_news',
            'albums' => 'media_albums',
            'stories' => 'media_stories',
        ];
        $pageKey = $aliases[$key] ?? $key;
        $pages = (array) config('cms_structure.pages', []);
        $route = $pages[$pageKey]['route'] ?? null;

        if (is_string($route) && $route !== '') {
            return route($route, ['locale' => $locale]);
        }

        // Unrecognised keys never become arbitrary URLs; they fall back to the
        // localized home page. Navigation never resolves to a hash fragment.
        return route('public.home', ['locale' => $locale]);
    }
}
