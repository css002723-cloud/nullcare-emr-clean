<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabResult extends Model
{
    const CREATED_AT = null;
    const UPDATED_AT = null;

    protected $fillable = [
        'lab_order_id', 'result_value', 'unit', 'reference_range', 'interpretation',
        'is_critical', 'is_abnormal', 'entered_by', 'verified_by', 'result_date',
    ];

    protected function casts(): array
    {
        return ['is_critical' => 'boolean', 'is_abnormal' => 'boolean', 'result_date' => 'datetime'];
    }

    public function labOrder()
    {
        return $this->belongsTo(LabOrder::class);
    }

    public function enteredBy()
    {
        return $this->belongsTo(User::class, 'entered_by');
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
