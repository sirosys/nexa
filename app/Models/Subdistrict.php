<?php

namespace App\Models;

use Database\Factories\SubdistrictFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subdistrict extends Model
{
    /** @use HasFactory<SubdistrictFactory> */
    use HasFactory;

    protected $fillable = [
        'district_id',
        'city_id',
        'province_id',
        'code',
        'zip',
        'type',
        'name',
        'district_name',
        'city_type',
        'city_name',
        'province_name',
    ];

    protected function casts(): array
    {
        return [
            'district_id' => 'integer',
            'city_id' => 'integer',
            'province_id' => 'integer',
            'zip' => 'integer',
        ];
    }
}
