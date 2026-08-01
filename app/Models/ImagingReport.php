<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImagingReport extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'client_uuid', 'imaging_order_id', 'findings', 'impression', 'is_critical_finding',
        'reported_by', 'reviewed_by_clinician', 'reviewed_at', 'synced',
    ];

    protected function casts(): array
    {
        return ['is_critical_finding' => 'boolean', 'synced' => 'boolean', 'reviewed_at' => 'datetime'];
    }

    public function imagingOrder()
    {
        return $this->belongsTo(ImagingOrder::class);
    }
}
