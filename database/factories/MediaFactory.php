<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MediaType;
use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::ulid(),
            'disk' => (string) config('website.uploads.private_disk', 'private'),
            'path' => 'media/'.now()->format('Y/m').'/'.Str::ulid().'.pdf',
            'type' => MediaType::Document,
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => fake()->numberBetween(1024, 512000),
            'checksum' => hash('sha256', Str::random(32)),
            'original_name' => fake()->word().'.pdf',
            'is_restricted' => true,
        ];
    }
}
