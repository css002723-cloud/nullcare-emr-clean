<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['invoice_id', 'service_name', 'amount'];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
