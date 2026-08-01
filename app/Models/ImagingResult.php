<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImagingResult extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['imaging_order_id', 'report', 'file_path', 'reported_by', 'reported_at'];

    protected function casts(): array
    {
        return ['reported_at' => 'datetime'];
    }

    public function imagingOrder() { return $this->belongsTo(ImagingOrder::class); }
    public function reportedBy() { return $this->belongsTo(User::class, 'reported_by'); }
}
