<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceLineItem extends Model
{
    const UPDATED_AT = null;

    protected $fillable = ['client_uuid', 'invoice_id', 'service_category', 'description', 'amount', 'synced'];

    protected function casts(): array
    {
        return ['synced' => 'boolean'];
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
