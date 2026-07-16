<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicalOrder extends Model
{
    protected $table = 'clinical_orders';

    protected $fillable = [
        'client_uuid', 'encounter_id', 'order_type', 'details',
        'target_department', 'priority', 'status', 'created_by',
    ];

    public function encounter()
    {
        return $this->belongsTo(Encounter::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
