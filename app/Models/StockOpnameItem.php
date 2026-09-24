<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockOpnameItem extends Model
{
    protected $fillable = [
        'stock_opname_id',
        'medicine_stock_id',
        'system_qty',
        'physical_qty',
    ];

    public function stock(): BelongsTo
    {
        return $this->belongsTo(MedicineStock::class, 'medicine_stock_id');
    }
}
