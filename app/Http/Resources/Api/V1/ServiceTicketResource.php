<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ServiceTicket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceTicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ServiceTicket $this */
        return [
            'code' => $this->code,
            'category' => $this->category,
            'category_label' => ServiceTicket::CATEGORY_LABELS[$this->category] ?? $this->category,
            'subject' => $this->subject,
            'description' => $this->description,
            'status' => $this->status,
            'status_label' => ServiceTicket::STATUS_LABELS[$this->status] ?? $this->status,
            'resolution_notes' => $this->resolution_notes,
            'claimed_at' => $this->claimed_at?->toIso8601String(),
            'solved_at' => $this->solved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
