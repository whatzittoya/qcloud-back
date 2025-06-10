<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function __construct() {}
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function index(Request $request)
    {
        return $request->user();
    }
    public function authenticate(Request $request)
    {
        $this->validate($request, [
            'email' => 'required',
            'password' => 'required'
        ]);
        $user = User::where('email', $request->input('email'))->first();
        if (Hash::check($request->input('password'), $user->password)) {
            // if($request->input('password')== $user->password) {
            $apikey = base64_encode(substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 1, 40));
            $user = User::where('email', $request->input('email'))->first();
            $user->update(['api_key' => "$apikey"]);
            return response()->json(['status' => 'success', 'api_key' => $apikey, 'role' => $user->role], 200);
        } else {
            return response()->json(['status' => 'fail'], 401);
        }
    }

    public function logout(Request $request)
    {
        $user = User::where('email', Auth::user()->email)->first();
        if ($user) {
            $user->api_key = "";
            $user->update();
            return response()->json(['status' => 'success', 'msg' => 'success logout']);
        } else {
            return response()->json(['status' => 'fail'], 401);
        }
    }


    public function changeClientId(Request $request)
    {
        $client_id = $request['client_id'];

        $user = User::where('id', Auth::user()->id)->where('role', 'super-admin')->first();
        $user->client_id =  $client_id;
        $user->update();
        return response()->json(['status' => 'success', 'msg' => 'success change client_id']);
    }

    public function resetPass(Request $request)
    {
        $user = Auth::user();

        if (!Hash::check($request->old_password, $user->password)) {
            return response()->json(['message' => 'Incorrect old password'], 400);
        }
        $user->password = Hash::make($request->new_password);
        $user->update();

        return response()->json(['message' => 'Password reset successfully']);
    }
    public function addMember(Request $request)
    {
        $this->validate($request, [
            'email' => 'required|email|unique:users',
            'name' => 'required',
            'password' => 'required',
            'location' => 'required|string',
        ]);

        $member = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
            'role' => 'staff',
            'client_id' => Auth::user()->client_id,
            'location' => $request->input('location'),
        ]);
        return response()->json(['message' => 'Member created successfully', 'data' => $member], 201);
    }

    public function updateMember(Request $request)
    {
        $this->validate($request, [
            'name' => 'required',
            'email' => 'email|unique:users,email,' . $request->id,
            'location' => 'string',
        ]);

        $member = User::find($request->id);

        if (!$member) {
            return response()->json(['message' => 'Member not found'], 404);
        }
        // Check if a new password is provided
        if ($request->password) {
            $member->name = $request->name;
            $member->email = $request->email;
            $member->password = Hash::make($request->input('password'));
            $member->location = $request->location;
        } else {
            $member->name = $request->name;
            $member->email = $request->email;
            $member->location = $request->location;
        }

        $member->update();

        return response()->json(['message' => 'Member updated successfully', 'data' => $member]);
    }
    public function deleteMember($id)
    {
        $member = User::find($id);

        if (!$member) {
            return response()->json(['message' => 'Member not found'], 404);
        }

        $member->delete();

        return response()->json(['message' => 'Member deleted successfully']);
    }
    public function getMember()
    {

        $member = User::with(['store' => function ($query) {
            $query->select('name', 'name as label', 'long_name as value');
        }])->select('users.name', 'users.email', 'users.id', 'users.role', 'users.location')->where('role', 'staff')->where('client_id', Auth::user()->client_id)->get();


        return $member;
    }
}
