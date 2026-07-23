<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['code', 'service_id', 'category', 'subject', 'description', 'status', 'assigned_technician_id', 'assigned_by', 'claimed_at', 'sla_reminder_sent_at', 'resolution_notes', 'solved_at', 'solved_by', 'created_by', 'updated_by'])]
class ServiceTicket extends Model
{
    use SoftDeletes;

    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_IN_PROGRESS,
        self::STATUS_RESOLVED,
    ];

    public const STATUS_LABELS = [
        self::STATUS_OPEN => 'Terbuka',
        self::STATUS_IN_PROGRESS => 'Sedang Ditangani',
        self::STATUS_RESOLVED => 'Selesai',
    ];

    public const CATEGORY_TEKNIS = 'teknis';

    public const CATEGORY_BILLING = 'billing';

    public const CATEGORY_PERMINTAAN = 'permintaan';

    public const CATEGORY_LAINNYA = 'lainnya';

    public const CATEGORIES = [
        self::CATEGORY_TEKNIS,
        self::CATEGORY_BILLING,
        self::CATEGORY_PERMINTAAN,
        self::CATEGORY_LAINNYA,
    ];

    public const CATEGORY_LABELS = [
        self::CATEGORY_TEKNIS => 'Teknis',
        self::CATEGORY_BILLING => 'Billing',
        self::CATEGORY_PERMINTAAN => 'Permintaan',
        self::CATEGORY_LAINNYA => 'Lainnya',
    ];

    // Kategori yang wajib lewat assign/klaim teknisi sebelum bisa
    // diselesaikan (lihat ServiceTicketService::resolve()) — kategori lain
    // diselesaikan langsung oleh staff tanpa tahap in_progress via
    // penugasan. Keputusan bisnis eksplisit dari user, lihat CLAUDE.md
    // "Ticketing".
    public const CATEGORIES_REQUIRING_TECHNICIAN = [
        self::CATEGORY_TEKNIS,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'claimed_at' => 'datetime',
            'sla_reminder_sent_at' => 'datetime',
            'solved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignedTechnician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_technician_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /** @return BelongsTo<User, $this> */
    public function solvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solved_by');
    }
}
