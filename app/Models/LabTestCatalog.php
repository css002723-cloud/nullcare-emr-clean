<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabTestCatalog extends Model
{
    protected $table = 'lab_test_catalog';
    protected $primaryKey = 'test_code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['test_code', 'loinc_code', 'loinc_display', 'default_specimen_type'];

    public function labOrders()
    {
        return $this->hasMany(LabOrder::class, 'test_code', 'test_code');
    }
}
