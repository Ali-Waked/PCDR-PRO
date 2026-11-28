<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Derived presentation type. It is intentionally separate from MediaType,
 * which keeps the existing image/document storage taxonomy intact.
 */
enum MediaKind: string
{
    case Image = 'image';
    case Pdf = 'pdf';
    case Word = 'word';
    case Excel = 'excel';
    case PowerPoint = 'powerpoint';
    case Video = 'video';
    case Audio = 'audio';
    case Text = 'text';
    case Other = 'other';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function fromMetadata(string $mime, ?string $extension = null): self
    {
        $mime = strtolower(trim($mime));
        $safeExtension = strtolower(ltrim((string) $extension, '.'));

        if (in_array($mime, self::rasterImageMimes(), true)) {
            return self::Image;
        }

        if ($mime === 'application/pdf') {
            return self::Pdf;
        }

        if (in_array($mime, self::wordMimes(), true)) {
            return self::Word;
        }

        if (in_array($mime, self::excelMimes(), true)) {
            return self::Excel;
        }

        if (in_array($mime, self::powerPointMimes(), true)) {
            return self::PowerPoint;
        }

        if (str_starts_with($mime, 'video/')) {
            return self::Video;
        }

        if (str_starts_with($mime, 'audio/')) {
            return self::Audio;
        }

        if (in_array($mime, self::textMimes(), true)) {
            return self::Text;
        }

        if (self::isGenericMime($mime)) {
            $fromExtension = self::fromExtension($safeExtension);
            if ($fromExtension !== null) {
                return $fromExtension;
            }
        }

        // SVG is deliberately not a previewable image kind here. Until it is
        // sanitized on upload, legacy SVG records use the safe neutral fallback.
        return self::Other;
    }

    /** @return list<string> */
    public function mimes(): array
    {
        return match ($this) {
            self::Image => self::rasterImageMimes(),
            self::Pdf => ['application/pdf'],
            self::Word => self::wordMimes(),
            self::Excel => self::excelMimes(),
            self::PowerPoint => self::powerPointMimes(),
            self::Video => self::previewableVideoMimes(),
            self::Audio => self::previewableAudioMimes(),
            self::Text => self::textMimes(),
            self::Other => [],
        };
    }

    /** @return list<string> */
    public function mimePrefixes(): array
    {
        return match ($this) {
            self::Video => ['video/'],
            self::Audio => ['audio/'],
            default => [],
        };
    }

    /** @return list<string> */
    public function extensions(): array
    {
        return match ($this) {
            self::Image => ['jpg', 'jpeg', 'png', 'webp', 'gif'],
            self::Pdf => ['pdf'],
            self::Word => ['doc', 'docx'],
            self::Excel => ['xls', 'xlsx', 'csv'],
            self::PowerPoint => ['ppt', 'pptx'],
            self::Video => ['mp4', 'webm', 'ogv', 'mov'],
            self::Audio => ['mp3', 'wav', 'ogg', 'aac', 'm4a'],
            self::Text => ['txt'],
            self::Other => [],
        };
    }

    public function isPreviewable(string $mime = ''): bool
    {
        $mime = strtolower(trim($mime));

        return match ($this) {
            self::Image => in_array($mime, self::rasterImageMimes(), true),
            self::Pdf => $mime === 'application/pdf',
            self::Video => in_array($mime, self::previewableVideoMimes(), true),
            self::Audio => in_array($mime, self::previewableAudioMimes(), true),
            default => false,
        };
    }

    public function labelKey(): string
    {
        return "website.ui.cms_media_kind_{$this->value}";
    }

    /** @return list<string> */
    private static function rasterImageMimes(): array
    {
        return ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    }

    /** @return list<string> */
    private static function wordMimes(): array
    {
        return [
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];
    }

    /** @return list<string> */
    private static function excelMimes(): array
    {
        return [
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/csv',
        ];
    }

    /** @return list<string> */
    private static function powerPointMimes(): array
    {
        return [
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.openxmlformats-officedocument.presentationml.slideshow',
        ];
    }

    /** @return list<string> */
    private static function textMimes(): array
    {
        return ['text/plain'];
    }

    /** @return list<string> */
    private static function previewableVideoMimes(): array
    {
        return ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'];
    }

    /** @return list<string> */
    private static function previewableAudioMimes(): array
    {
        return ['audio/mpeg', 'audio/mp3', 'audio/ogg', 'audio/wav', 'audio/x-wav', 'audio/aac', 'audio/mp4'];
    }

    private static function isGenericMime(string $mime): bool
    {
        return in_array($mime, ['', 'application/octet-stream', 'binary/octet-stream', 'application/unknown'], true);
    }

    private static function fromExtension(string $extension): ?self
    {
        return match ($extension) {
            'jpg', 'jpeg', 'png', 'webp', 'gif' => self::Image,
            'pdf' => self::Pdf,
            'doc', 'docx' => self::Word,
            'xls', 'xlsx', 'csv' => self::Excel,
            'ppt', 'pptx' => self::PowerPoint,
            'mp4', 'webm', 'ogv', 'mov' => self::Video,
            'mp3', 'wav', 'ogg', 'aac', 'm4a' => self::Audio,
            'txt' => self::Text,
            default => null,
        };
    }
}
