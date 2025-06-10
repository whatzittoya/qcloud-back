<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsolidateAdditional extends Model
{
    protected $table = 'consolidate_additional';
    protected $fillable = ['consolidate_id', 'Target_Revenue', 'isHoliday', 'cons_date', 'cons_store'];
    public $timestamps = false;


    public static function createOrUpdate($attributes)
    {
        $consolidate_additional = self::firstOrNew(['cons_store' => $attributes['cons_store'], 'cons_date' => $attributes['cons_date']]);
        $consolidate_additional->fill($attributes);
        $consolidate_additional->save();
        return $consolidate_additional;
    }
}
