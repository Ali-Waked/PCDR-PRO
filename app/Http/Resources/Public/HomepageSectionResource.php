<?php

declare(strict_types=1);

namespace App\Http\Resources\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class HomepageSectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this['id'],
            'key' => $this['key'],
            'type' => $this['type'],
            'status' => $this['status'],
            'sort_order' => $this['order'],
            'eyebrow' => $this['eyebrow'],
            'title' => $this['title'],
            'body' => $this['body'],
            'cta_label' => $this['ctaLabel'],
            'cta_link' => $this['ctaLink'],
            'tickerLabel' => $this['tickerLabel'],
            'tabLabels' => $this['tabLabels'],
            'videoUrl' => $this['videoUrl'],
            'videoCaption' => $this['videoCaption'],
            'items' => HomepageItemResource::collection($this['items']),
        ];
    }
}
