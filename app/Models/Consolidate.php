<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Consolidate extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'consolidate';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'date', 'store', 'Target_Revenue', 'Actual_Revenue', 'Pax', 'Average_Pax', 'Main_Course', 'Side_Dish', 'Dessert', 'Beverage', 'client_id'
    ];


    /**
     * Create a new instance of the model and persist it to the database.
     *
     * @param  array  $attributes
     * @return static
     */
    public static function createIfNotExists($attributes)
    {
        if (!is_null(static::where('date', $attributes['date'] ?? '')->where('store', $attributes['store'] ?? '')->first())) {
            return static::where($attributes)->first();
        }

        return static::create($attributes);
    }


    /**
     * store consolidate_additional
     */
    public function storeConsolidateAdditional($attributes)
    {
        $this->consolidate_additional()->create($attributes);
    }


    /**
     * one to one relation with consolidate_additional
     */
    public function consolidate_additional()
    {
        return $this->hasOne(ConsolidateAdditional::class, 'consolidate_id', 'id');
    }
}
