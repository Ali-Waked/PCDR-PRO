<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ComplaintCategory;
use App\Enums\ComplaintContactMethod;
use App\Enums\ComplaintPriority;
use App\Enums\ComplaintStatus;
use App\Models\Complaint;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Complaint>
 */
class ComplaintFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'reference' => 'PCDR-CMP-'.now()->year.'-'.Str::padLeft((string) fake()->unique()->numberBetween(1, 999999), 6, '0'),
            'category' => fake()->randomElement(ComplaintCategory::values()),
            'subject' => fake()->sentence(6),
            'description' => fake()->paragraph(),
            'locale' => fake()->randomElement(['ar', 'en']),
            'related_project' => null,
            'location' => null,
            'status' => ComplaintStatus::New,
            'priority' => ComplaintPriority::Normal,
            'is_anonymous' => false,
            'complainant_name' => fake()->name(),
            'preferred_contact_method' => ComplaintContactMethod::Email,
            'email' => fake()->unique()->safeEmail(),
            'phone' => null,
            'preferred_locale' => 'ar',
        ];
    }

    public function anonymous(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_anonymous' => true,
            'complainant_name' => null,
            'preferred_contact_method' => null,
            'email' => null,
            'phone' => null,
        ]);
    }

    public function withStatus(ComplaintStatus $status): static
    {
        return $this->state(fn (array $attributes): array => ['status' => $status]);
    }
}
