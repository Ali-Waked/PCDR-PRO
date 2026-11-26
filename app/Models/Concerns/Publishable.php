<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Enums\ContentStatus;
use Illuminate\Database\Eloquent\Builder;

trait Publishable
{
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ContentStatus::Published->value)
            ->where(function (Builder $query): void {
                $query->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    public function scopeVisibleToPublic(Builder $query): Builder
    {
        return $query->published();
    }

    public function isPublished(): bool
    {
        return ContentStatus::valueOf($this->status) === ContentStatus::Published->value
            && ($this->published_at === null || $this->published_at->lessThanOrEqualTo(now()));
    }

    public function statusLabel(?string $locale = null): string
    {
        $status = $this->status instanceof ContentStatus
            ? $this->status
            : ContentStatus::from((string) $this->status);

        return $status->label($locale ?? app()->getLocale());
    }
}
