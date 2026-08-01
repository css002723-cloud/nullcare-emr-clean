<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabResult extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'client_uuid', 'lab_order_id', 'result_value', 'unit', 'reference_range',
        'is_critical', 'is_abnormal', 'interpretation', 'entered_by', 'verified_by',
        'verified_at', 'critical_alert_acknowledged', 'synced',
    ];

    protected function casts(): array
    {
        return [
            'is_critical' => 'boolean', 'is_abnormal' => 'boolean',
            'critical_alert_acknowledged' => 'boolean', 'synced' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function labOrder()
    {
        return $this->belongsTo(LabOrder::class);
    }
}
