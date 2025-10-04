<?php

namespace App\Services;

use App\Models\Client as ModelsClient;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\FileCookieJar;
use Illuminate\Support\Facades\Auth;

class QuinosApiService
{
    private $quinosAPI;
    private $username;
    private $password;
    private $cookieJar;
    private $client;

    public function __construct()
    {
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

    public function getPurchaseOrderPreview($poId)
    {
        try {
            $api_url = "{$this->quinosAPI}/purchaseOrders/getPreview/{$poId}";
            $response = json_decode($this->guzzleReq($api_url), true);

            return $response;
        } catch (\Exception $e) {
            throw new \Exception("Error fetching PO preview: " . $e->getMessage());
        }
    }

    private function login()
    {
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

    private function guzzleReq($api_url)
    {
        $response = $this->client->request('GET', $api_url, ['cookies' => $this->cookieJar]);
        $body = $response->getBody()->getContents();

        if (str_contains($body, 'Sign in')) {
            $login = $this->login();

            if ($login) {
                $response = $this->client->request('GET', $api_url, ['cookies' => $this->cookieJar]);
                $body = $response->getBody()->getContents();
            } else {
                $body = [];
            }
        }

        return $body;
    }
}
