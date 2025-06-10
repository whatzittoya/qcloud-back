<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ClientController extends Controller
{
    public function index()
    {
        return Client::with('user')->get();
    }

    public function store(Request $request)
    {
        $client = new Client();
        $client->name = $request->name;
        $client->email = $request->email;
        $client->password = $request->password;
        $client->description = $request->description;
        $client->url = $request->url;
        $client->save();

        $user = User::firstOrNew(['client_id' => $client->id, 'role' => 'admin']);
        $user->name = $request->admin_name;
        $user->email = $request->admin_email;
        $user->client_id = $client->id;
        if (isset($request->admin_pass) && !empty($request->admin_pass) && $request->admin_pass != '') {
            $user->password = Hash::make($request->admin_pass);
        }
        $user->save();
        return response()->json(['message' => 'Successfully created']);
    }


    public function update(Request $request)
    {
        // return (isset($request->password) && !empty($request->password) && $request->password != '');
        $client = Client::find($request->id);
        $client->name = $request->name;
        $client->email = $request->email;
        if (isset($request->password) && !empty($request->password) && $request->password != '') {
            $client->password = $request->password;
        }
        $client->description = $request->description;
        $client->url = $request->url;
        $client->save();
        $user = User::firstOrNew(['client_id' => $client->id, 'role' => 'admin']);
        $user->name = $request->admin_name;
        $user->email = $request->admin_email;
        $user->client_id = $client->id;
        if (isset($request->admin_pass) && !empty($request->admin_pass) && $request->admin_pass != '') {
            $user->password = Hash::make($request->admin_pass);
        }
        $user->save();
        return response()->json(['message' => 'Successfully updated']);
    }

    public function destroy($id)
    {
        $client = Client::find($id);
        $client->delete();
        return response()->json(['message' => 'Successfully deleted']);
    }
}
