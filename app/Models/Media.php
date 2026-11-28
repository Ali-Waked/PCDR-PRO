<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MediaKind;
use App\Enums\MediaType;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use HasFactory;
    use HasTranslations;
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'disk', 'path', 'type', 'mime_type', 'extension', 'size_bytes',
        'width', 'height', 'checksum', 'original_name', 'is_restricted', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => MediaType::class,
            'is_restricted' => 'boolean',
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    public function translationModel(): string
    {
        return MediaTranslation::class;
    }

    public function translationForeignKey(): string
    {
        return 'media_id';
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function scopeImages(Builder $query): Builder
    {
        return $query->where('type', MediaType::Image->value);
    }

    public function scopeDocuments(Builder $query): Builder
    {
        return $query->where('type', MediaType::Document->value);
    }

    public function scopeKind(Builder $query, string $kind): Builder
    {
        $resolved = MediaKind::tryFrom($kind);

        if ($resolved === null || $resolved === MediaKind::Other) {
            foreach (MediaKind::cases() as $case) {
                if ($case !== MediaKind::Other) {
                    $this->applyKindConstraint($query, $case, true);
                }
            }

            return $query;
        }

        return $this->applyKindConstraint($query, $resolved, false);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_restricted', false);
    }

    public function scopeRestricted(Builder $query): Builder
    {
        return $query->where('is_restricted', true);
    }

    public function isImage(): bool
    {
        return $this->type === MediaType::Image;
    }

    public function kind(): MediaKind
    {
        return MediaKind::fromMetadata((string) $this->mime_type, $this->extension);
    }

    public function canPreview(): bool
    {
        return $this->kind()->isPreviewable((string) $this->mime_type);
    }

    private function applyKindConstraint(Builder $query, MediaKind $kind, bool $exclude): Builder
    {
        $constraint = function (Builder $inner) use ($kind): void {
            $mimes = $kind->mimes();
            if ($mimes !== []) {
                $inner->whereIn('mime_type', $mimes);
            }

            foreach ($kind->mimePrefixes() as $prefix) {
                $inner->orWhere('mime_type', 'like', $prefix.'%');
            }

            $extensions = $kind->extensions();
            if ($extensions !== []) {
                $inner->orWhere(function (Builder $fallback) use ($extensions): void {
                    $fallback->whereIn('extension', $extensions)
                        ->where(function (Builder $generic): void {
                            $generic->whereNull('mime_type')
                                ->orWhere('mime_type', '')
                                ->orWhere('mime_type', 'application/octet-stream');
                        });
                });
            }
        };

        return $exclude ? $query->whereNot($constraint) : $query->where($constraint);
    }

    public function url(): string
    {
        // A restricted record must never emit a direct public URL, even if it
        // was written to the public disk by an older installation. Downloads
        // remain behind the signed, permission-checked controller.
        if ($this->is_restricted || $this->disk === config('website.uploads.private_disk')) {
            return route('public.media.download', [
                'locale' => app()->getLocale(),
                'uuid' => $this->uuid,
            ]);
        }

        return Storage::disk($this->disk)->url($this->path);
    }

    public function humanSize(): string
    {
        $bytes = (int) $this->size_bytes;

        return $bytes >= 1024 * 1024
            ? round($bytes / (1024 * 1024), 1).' MB'
            : round(max($bytes, 1) / 1024, 1).' KB';
    }
}
