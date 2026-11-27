<?php

namespace Database\Factories;

use App\Models\WebsiteOrganization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebsiteOrganization>
 */
class WebsiteOrganizationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'logo_media_id' => null,
            'destination_url' => null,
        ];
    }
}
