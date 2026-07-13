<?php

namespace App\Services;

use App\Models\Vendor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VendorService
{
    public function create(array $data): Vendor
    {
        return DB::transaction(function () use ($data) {
            $vendor = Vendor::create([
                ...$data,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $vendor->update(['code' => 'VEN'.str_pad((string) $vendor->id, 6, '0', STR_PAD_LEFT)]);

            return $vendor;
        });
    }

    public function update(Vendor $vendor, array $data): Vendor
    {
        $vendor->update([
            ...$data,
            'updated_by' => Auth::id(),
        ]);

        return $vendor;
    }

    public function delete(Vendor $vendor): void
    {
        $vendor->delete();
    }
}
