<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'client_uuid', 'invoice_number', 'encounter_id', 'patient_id', 'payer_type',
        'payer_name', 'total_amount', 'amount_paid', 'status', 'payment_reference',
        'created_by', 'synced',
    ];

    protected function casts(): array
    {
        return ['synced' => 'boolean'];
    }

    public function encounter()
    {
        return $this->belongsTo(Encounter::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lineItems()
    {
        return $this->hasMany(InvoiceLineItem::class);
    }
}
