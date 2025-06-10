<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    protected $fillable = ['name', 'store', 'long_name', 'description', 'client_id'];


    public static function createOrUpdate($stores, $client_id)
    {

        $store = Store::where('client_id', $client_id)->update(['is_active' => 0]);
        foreach ($stores as $name) {
            $entry = Store::firstOrNew(['name' => $name, 'client_id' => $client_id]);
            $entry->is_active = 1;
            if ($entry->long_name == null || empty($entry->long_name)) {
                $entry->long_name = $name;
            }
            $entry->save();
        }
        $stores = Store::where('client_id', $client_id)->where('is_active', 1)->get();



        return $stores;
        try {
        } catch (\Throwable $th) {
            return [];
        }
    }


    public static function getStore($client_id)
    {
        return Store::where('client_id', $client_id)->where('is_active', 1)->orderBy('name')->get();
    }



    public static function listStoreAdmin($stores, $client_id)
    {
        $newStore = new Store();
        $newStore->id = 0;
        $newStore->name = 'All';
        $newStore->long_name = 'All';
        $newStore->client_id = $client_id;
        $newStore->location = null;
        $stores->push($newStore);
        $stores->prepend($stores->last());
        $stores->pop();
        return $stores;
    }
}
