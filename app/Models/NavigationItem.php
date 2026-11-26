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

        $routes = [
            'home' => 'public.home',
        ];

        $name = $routes[$this->link_value] ?? 'public.home';

        return route($name, ['locale' => $locale]);
    }
}
