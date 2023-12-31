<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\FileCookieJar;
use Illuminate\Support\Facades\Auth;

class ReportsController extends Controller
{
    private $client;
    private $cookieJar;
    private $quinosAPI;
    private $quinosToken;
    private $username;
    private $password ;


    public function __construct()
    {
        // $this->middleware('auth');

        // Specify the path to the cookie file
        $cookieFile = __DIR__ . '/cookies.txt';

        // Create a Guzzle client instance
        $this->client = new Client();

        // Create a FileCookieJar to handle cookies and store them in the specified file
        $this->cookieJar = new FileCookieJar($cookieFile, true);
        $this->quinosAPI=env("QUINOS_API");
        $this->quinosToken=env("QUINOS_TOKEN");
        $this->username = env("QUINOS_EMAIL");
        $this->password = env("QUINOS_PASSWORD");

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
        return str_contains($response->getBody()->getContents(), "Data Configuration");

    }
    public function paymentSummary($date1, $date2, $store="")
    {
        if(strtolower($store) == 'all') {
            $store='';
        }
        $api_url = "{$this->quinosAPI}/reports/getPaymentSummaryReport/{$date1}/{$date2}/{$store}";
        return $this->guzzleReq($api_url);

    }
    public function summarySales($date1, $date2, $store="")
    {
        if(strtolower($store) == 'all') {
            $store='';
        }
        $api_url = "{$this->quinosAPI}/reports/getTransactionSummaryReport/{$date1}/{$date2}/{$store}";
        return $this->guzzleReq($api_url);

    }
    public function discountSummary($date1, $date2, $store="")
    {
        if(strtolower($store) == 'all') {
            $store='';
        }
        $api_url = "{$this->quinosAPI}/reports/getDiscountSummaryReport/{$date1}/{$date2}/{$store}";
        return $this->guzzleReq($api_url);

    }
    public function noSales($date1, $date2, $store="")
    {
        if(strtolower($store) == 'all') {
            $store='';
        }
        $api_url = "{$this->quinosAPI}/reports/getNoSalesReportSummary/{$date1}/{$date2}/{$store}";
        return $this->guzzleReq($api_url);

    }
    public function itemSales($date1, $date2, $store="")
    {
        if(strtolower($store) == 'all') {
            $store='';
        }
        $api_url = "{$this->quinosAPI}/reports/getItemSalesReport/{$date1}/{$date2}/{$store}";
        return $this->guzzleReq($api_url);

    }
    public function salesType($date1, $date2, $store="")
    {
        if(strtolower($store) == 'all') {
            $store='';
        }
        $api_url = "{$this->quinosAPI}/reports/getSalesTypeReport/{$date1}/{$date2}/{$store}";
        return $this->guzzleReq($api_url);

    }
    public function store()
    {
        $api_url = "{$this->quinosAPI}/staffs/listStore";
        return $this->guzzleReq($api_url);

    }
    
    public function guzzleReq($api_url)
    {
        
        $client = new Client();
        $response = $client->request('GET', $api_url, ['cookies' => $this->cookieJar]);
        $body = $response->getBody()->getContents();

        if(str_contains($body, 'Sign in')) {

            $login=$this->login();

            if($login) {
                $response = $client->request('GET', $api_url, ['cookies' => $this->cookieJar]);
                $body = $response->getBody()->getContents();
            } else {
                $body=[];
            }
        }
        return $body;
    }

}
