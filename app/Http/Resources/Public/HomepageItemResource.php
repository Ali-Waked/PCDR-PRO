<?php

declare(strict_types=1);

namespace App\Http\Resources\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class HomepageItemResource extends JsonResource
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
            'sort_order' => $this['sortOrder'],
            'label' => $this['label'],
            'title' => $this['title'],
            'body' => $this['body'],
            'display' => $this['display'],
            'url' => $this['url'],
            'value' => $this['value'],
            'media' => $this['media'],
        ];
    }
}
