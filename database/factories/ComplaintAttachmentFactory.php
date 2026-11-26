<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Complaint;
use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComplaintAttachment>
 */
class ComplaintAttachmentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'complaint_id' => Complaint::factory(),
            'media_id' => Media::factory(),
            'label' => fake()->optional()->words(3, true),
        ];
    }
}
