<?php


namespace App\Http\Controllers;

use App\Models\Client as ModelsClient;;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use GuzzleHttp\Cookie\FileCookieJar;
use GuzzleHttp\Client;

class WarehouseController extends Controller
{
    private $client;
    private $cookieJar;
    private $quinosAPI;
    private $username;
    private $password;
    public function __construct()
    {
        // $this->middleware('auth');

        $this->middleware('auth');
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

    public function index() {}

    public function listWarehouse()
    {
        $api_url = "{$this->quinosAPI}/warehouses/combobox";
        $stores = json_decode($this->guzzleReq($api_url));

        return response()->json($stores);
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
