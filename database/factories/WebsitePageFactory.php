<?php

namespace Database\Factories;

use App\Models\WebsitePage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebsitePage>
 */
class WebsitePageFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'key' => 'page-'.$this->faker->unique()->slug(2),
            'path' => '/'.$this->faker->slug(),
            'is_enabled' => true,
            'display_order' => $this->faker->numberBetween(0, 20),
        ];
    }
}
