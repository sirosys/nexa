<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'phone' => (string) $this->phone,
            'email' => $this->email,
            'nik' => $this->userDetails?->nik,
            'has_ktp_photo' => $this->userDetails?->ktp_photo !== null,
            'gender' => $this->userDetails?->gender,
            'birth_date' => $this->userDetails?->birth_date?->format('Y-m-d'),
        ];
    }
}
