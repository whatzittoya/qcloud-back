<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\Client as ModelsClient;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\FileCookieJar;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
    private $client;
    private $cookieJar;
    private $quinosAPI;
    private $quinosToken;
    private $username;
    private $password;


    public function __construct()
    {
        $this->middleware('auth');

        // Specify the path to the cookie file
        $client = ModelsClient::where('id', Auth::user()->client_id)->first();
        if (!$client) {
            return abort(404);
        }
        $client_name = $client->name;
        $cookieFile = __DIR__ . '/' . $client_name . '.txt';

        // Create a Guzzle client instance
        $this->client = new Client();

        // Create a FileCookieJar to handle cookies and store them in the specified file
        $this->cookieJar = new FileCookieJar($cookieFile, true);
        $this->quinosAPI = $client->url;
        $this->username = $client->email;
        $this->password = $client->password;
    }
    public function index()
    {
        return Auth::user()->email;

        //$api_url = "https://quinoscloud.com/cloud/reports/getTransactionReport/".$date1 ."/".$date2 ."/".$store;
        //$this->guzzleReq($api_url);


    }
    public function login()
    {
        $client = new Client();

        // Define login credentials


        // Define the login URL
        $loginUrl = "{$this->quinosAPI}/staffs/login";

        $client = new Client(array(
            'cookies' => $this->cookieJar,
        ));


        $response = $client->request('POST', $loginUrl, [
            'timeout' => 30,
            'form_params' => [
                'data[Staff][email]' => $this->username,
                'data[Staff][password]' => $this->password,
            ]
        ]);
        // echo $response->getBody()->getContents();
        return str_contains($response->getBody()->getContents(), "Data Configuration");
    }

    public function monthly($store = "")
    {
        if (strtolower($store) == 'all') {
            $store = '';
        }
        $api_url = "{$this->quinosAPI}/reports/getMonthlyTransactionReport/{$store}";
        return $this->guzzleReq($api_url);
    }
    public function daily($date1, $date2, $store = "")
    {
        if (strtolower($store) == 'all') {
            $store = '';
        }

        $api_url = "https://quinoscloud.com/cloud/reports/getTransactionReport/{$date1}/{$date2}/{$store}";
        return $this->guzzleReq($api_url);
    }

    public function store()
    {

        $store = Auth::user()->location;
        $api_url = "{$this->quinosAPI}/staffs/listStore";
        $stores = json_decode($this->guzzleReq($api_url));
        $stores = Store::createOrUpdate($stores, Auth::user()->client_id);
        if (Auth::user()->role == 'admin' || Auth::user()->role == 'super-admin') {
            $stores = Store::listStoreAdmin($stores, Auth::user()->client_id);
        }
        return response()->json($stores);

        // return [$stores[array_search($store, $stores)]];
    }

    public function guzzleReq($api_url)
    {

        $client = new Client();
        $response = $client->request('GET', $api_url, ['cookies' => $this->cookieJar]);
        $body = $response->getBody()->getContents();
        if (str_contains($body, 'Sign in')) {

            $login = $this->login();

            if ($login) {
                $response = $client->request('GET', $api_url, ['cookies' => $this->cookieJar]);
                $body = $response->getBody()->getContents();
            } else {
                $body = [];
            }
        }
        return $body;
    }
}
