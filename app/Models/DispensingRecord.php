<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DispensingRecord extends Model
{
    const UPDATED_AT = null;
    const CREATED_AT = null;

    protected $fillable = ['prescription_id', 'dispensed_by', 'quantity_dispensed', 'dispensed_at', 'notes'];

    protected function casts(): array
    {
        return ['dispensed_at' => 'datetime'];
    }

    public function prescription()
    {
        return $this->belongsTo(Prescription::class);
    }

    public function dispensedBy()
    {
        return $this->belongsTo(User::class, 'dispensed_by');
    }
}
