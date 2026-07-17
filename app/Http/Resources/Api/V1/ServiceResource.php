<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Service $this */
        return [
            'code' => $this->code,
            'status' => $this->status,
            'status_label' => Service::STATUS_LABELS[$this->status] ?? $this->status,
            'address' => $this->address,
            'residential_name' => $this->residential_name,
            'rw' => $this->rw,
            'rt' => $this->rt,
            'coverage' => $this->whenLoaded('coverage', fn () => [
                'code' => $this->coverage->code,
                'name' => $this->coverage->name,
            ]),
            'package' => $this->whenLoaded('package', fn () => [
                'code' => $this->package->code,
                'name' => $this->package->name,
            ]),
            'activated_at' => $this->activated_at?->toIso8601String(),
            'expired_at' => $this->expired_at?->toIso8601String(),
            'suspended_at' => $this->suspended_at?->toIso8601String(),
            'dismantled_at' => $this->dismantled_at?->toIso8601String(),
        ];
    }
}
