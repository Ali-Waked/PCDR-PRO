<?php

declare(strict_types=1);

namespace App\Http\Resources\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class HomepageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'locale' => $this['locale'],
            'dir' => $this['dir'],
            'organization' => $this['organization'],
            'sections' => HomepageSectionResource::collection($this['sections']),
        ];
    }
}
