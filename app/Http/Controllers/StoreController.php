<?php


namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use App\Models\Store;
use Illuminate\Support\Facades\Auth;

class StoreController extends Controller
{
    public function __construct()
    {
        // $this->middleware('auth');

        $this->middleware('auth');
    }

    public function index()
    {
        if (Auth::user()->role == 'admin' || Auth::user()->role == 'super-admin') {
            $stores = Store::getStore(Auth::user()->client_id);

            $stores = Store::listStoreAdmin($stores, Auth::user()->client_id);
        } else {
            $stores = Store::where('name', Auth::user()->location)->orderBy('name')->get();
        }
        return response()->json($stores);
    }
    public function getAdmin($client)
    {
        $client = Client::where('name', $client)->first();
        if ($client) {
            $stores = Store::getStore($client->id);
            return response()->json($stores);
        }

        return response()->json(['message' => 'Client not found'], 404);
    }


    public function update(Request $request)
    {
        $store = Store::find($request->id);
        $store->long_name = $request->input('long_name');
        $store->description = $request->input('description');
        $store->save();
        return response()->json(['message' => 'Successfully update store']);
    }
}
