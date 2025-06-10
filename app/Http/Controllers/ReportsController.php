<?php

namespace App\Http\Controllers;

use App\Models\Client as ModelsClient;
use App\Models\Consolidate;
use App\Models\ConsolidateAdditional;
use App\Models\Store;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\FileCookieJar;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    private $client;
    private $cookieJar;
    private $quinosAPI;
    private $quinosToken;
    private $username;
    private $password;
    private $department;


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
        $this->department = [
            [
                'name' => 'MAIN COURSE',
                'value' => 'Main_Course'
            ], [
                'name' => 'SIDE DISH',
                'value' => 'Side_Dish'
            ],
            [
                'name' => 'DESSERT',
                'value' => 'Dessert'
            ],
            [
                'name' => 'BEVERAGE',
                'value' => 'Beverage'
            ]
        ];
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
    public function paymentSummary($date1, $date2, $store = "")
    {
        if (strtolower($store) == 'all') {
            $store = '';
        }
        $api_url = "{$this->quinosAPI}/reports/getPaymentSummaryReport/{$date1}/{$date2}/{$store}";
        return $this->guzzleReq($api_url);
    }
    public function summarySales($date1, $date2, $store = "")
    {
        if (strtolower($store) == 'all') {
            $store = '';
        }
        $api_url = "{$this->quinosAPI}/reports/getTransactionSummaryReport/{$date1}/{$date2}/{$store}";
        return $this->guzzleReq($api_url);
    }
    public function discountSummary($date1, $date2, $store = "")
    {
        if (strtolower($store) == 'all') {
            $store = '';
        }
        $api_url = "{$this->quinosAPI}/reports/getDiscountSummaryReport/{$date1}/{$date2}/{$store}";
        return $this->guzzleReq($api_url);
    }
    public function noSales($date1, $date2, $store = "")
    {
        if (strtolower($store) == 'all') {
            $store = '';
        }
        $api_url = "{$this->quinosAPI}/reports/getNoSalesReportSummary/{$date1}/{$date2}/{$store}";
        return $this->guzzleReq($api_url);
    }
    public function itemSales($date1, $date2, $store = "")
    {
        if (strtolower($store) == 'all') {
            $store = '';
        }
        $api_url = "{$this->quinosAPI}/reports/getItemSalesReport/{$date1}/{$date2}/{$store}";
        return $this->guzzleReq($api_url);
    }
    public function salesType($date1, $date2, $store = "")
    {
        if (strtolower($store) == 'all') {
            $store = '';
        }
        $api_url = "{$this->quinosAPI}/reports/getSalesTypeReport/{$date1}/{$date2}/{$store}";
        return $this->guzzleReq($api_url);
    }

    public function consolidateReport($month, $year, $store_f = "")
    {

        $check_not = $this->checkNotRecorded($month, $year);
        $not_recorded = $check_not['not_recorded'];
        $cons = $check_not['consolidate'];
        $arr_data = [];

        foreach ($not_recorded as $d) {
            $date1 = $d['date'];

            $api_url1 = "{$this->quinosAPI}/reports/getDepartmentStoreReport/{$date1}/{$date1}";
            $department_store = $this->guzzleReq($api_url1);
            $department_arr = json_decode($department_store);
            foreach ($d['store'] as $store) {
                $api_url2 = "{$this->quinosAPI}/reports/getSalesTrendReport/{$date1}/{$date1}/{$store}";
                $sales_trend = $this->guzzleReq($api_url2);
                $sales_arr = json_decode($sales_trend);

                $data = new \stdClass();
                $data->date = $date1;
                $data->store = $store;
                $data->client_id = Auth::user()->client_id;
                if (count($sales_arr) > 0) {

                    $data->Actual_Revenue = $sales_arr[0]->Transaction->amount;
                    $data->Pax = $sales_arr[0]->Transaction->pax;
                    $data->Average_Pax = $data->Actual_Revenue / $data->Pax;


                    foreach ($this->department as $dep) {
                        $key = $dep['value'];
                        $name = $dep['name'];
                        $department_filter = array_values(array_filter($department_arr, function ($value) use ($store, $name) {
                            return $value->Transaction->store_code == $store && $value->TransactionLine->department_code == $name;
                        }));

                        $dep_value = 0;
                        if (count($department_filter) > 0) {
                            try {
                                $dep_value = $department_filter[0]->TransactionLine->amount;
                            } catch (\Throwable $th) {
                            }
                        }

                        $data->$key = $dep_value;
                    }
                } else {

                    $data->Actual_Revenue = 0;
                    $data->Pax = 0;
                    $data->Average_Pax = 0;
                    foreach ($this->department as $dep) {
                        $key = $dep['value'];
                        $data->$key = 0;
                    }
                }

                array_push($arr_data, $data);
                $datatable = (array)$data;
                $consolidate = Consolidate::createIfNotExists($datatable);
            }
        }
        if (strtolower($store_f) == 'all') {

            $cons = Consolidate::select('date', DB::raw(
                '0 as id, COALESCE(SUM(Actual_Revenue), 0) as Actual_Revenue, COALESCE(SUM(Pax), 0) as Pax, COALESCE(SUM(Average_Pax), 0) as Average_Pax, COALESCE(SUM(Main_Course), 0) as Main_Course, COALESCE(SUM(Side_Dish), 0) as Side_Dish, COALESCE(SUM(Beverage), 0) as Beverage'
            ))
                ->whereYear('date', '=', $year)
                ->whereMonth('date', '=', $month)
                ->where('client_id', '=', Auth::user()->client_id)
                ->groupBy('date')
                ->get();

            $stores = Store::getStore(Auth::user()->client_id);
            $stores_name = $stores->pluck('name')->toArray();
            $consolidate_additional = ConsolidateAdditional::select('cons_date', DB::raw('COALESCE(SUM(consolidate_additional.Target_Revenue), 0) as Target_Revenue, SUM(COALESCE(isHoliday,0)) as isHoliday'))->groupBy('cons_date')->wherein('cons_store', $stores_name)->get();
            return json_encode(['consolidate' => $cons, 'consolidate_additional' => $consolidate_additional]);
        } else {
            $cons = Consolidate::whereYear('date', '=', $year)
                ->select(['consolidate.id', 'date', 'store', 'Actual_Revenue', 'Pax', 'Average_Pax', 'Main_Course', 'Side_Dish', 'Dessert', 'Beverage'])
                ->whereMonth('date', '=', $month)
                ->where('store', '=', $store_f)
                ->where('client_id', '=', Auth::user()->client_id)
                ->get();

            // $consolidate_additional = ConsolidateAdditional::select('cons_date as date', DB::raw('COALESCE(consolidate_additional.Target_Revenue, 0) as Target_Revenue, COALESCE(isHoliday,0) as isHoliday'))->where('cons_store', $store_f)->get();
            $stores = Store::getStore(Auth::user()->client_id);
            $stores_name = $stores->pluck('name')->toArray();
            $consolidate_additional = ConsolidateAdditional::select('cons_date', DB::raw('COALESCE(SUM(case
            when cons_store = "' . $store_f . '" then 
            consolidate_additional.Target_Revenue
            else 0
            end)
             , 0) as Target_Revenue, SUM(COALESCE(isHoliday,0)) as isHoliday'))->groupBy('cons_date')->wherein('cons_store', $stores_name)->get();

            return json_encode(['consolidate' => $cons, 'consolidate_additional' => $consolidate_additional]);
        }
    }


    public function storeConsolidateAdditional(Request $request)
    {
        // return $request;
        $consolidate_additional = ConsolidateAdditional::createOrUpdate([
            'cons_date' => $request->date,
            'cons_store' => $request->store,
            'Target_Revenue' => $request->Target_Revenue ? $request->Target_Revenue : 0,
            'isHoliday' => $request->isHoliday ? $request->isHoliday : 0,
        ]);
        return response()->json(['message' => 'Success update data']);
    }


    public function getDatesinMonth($year, $month)
    {

        $date_array = [];
        $begin = new \DateTime("{$year}-{$month}-01");
        $end = new \DateTime("{$year}-{$month}-01");
        $end->modify('last day of this month');
        $end->add(new \DateInterval('P1D'));
        $today = (new \DateTime())->modify("-1 days");

        if ($end > $today) {
            $end = $today;
        }
        $interval = new \DateInterval('P1D');
        $date_range = new \DatePeriod($begin, $interval, $end);
        foreach ($date_range as $date) {
            $date_array[] = $date->format('Y-m-d');
        }
        return $date_array;
    }

    public function checkNotRecorded($month, $year)
    {
        $all_dates = $this->getDatesinMonth($year, $month);
        $stores = Store::where('is_active', '=', true)->where('client_id', '=', Auth::user()->client_id)->pluck('name')->toArray();
        $consolidate = Consolidate::whereYear('date', '=', $year)
            ->whereMonth('date', '=', $month)
            ->get();
        $not_recorded = [];
        if (count($consolidate) == 0) {
            foreach ($all_dates as $date) {
                $not_recorded[] = ['date' => $date, 'store' => $stores];
            }

            return ['not_recorded' => $not_recorded, 'consolidate'   => $consolidate];
        }
        foreach ($all_dates as $date) {
            $consolidate_at_date = $consolidate->where('date', $date);
            if (count($consolidate_at_date) == 0) {
                $not_recorded[] = ['date' => $date, 'store' => $stores];
            } else {
                $empty_store = [];
                foreach ($stores as $store) {
                    $consolidate_at_date_at_store = $consolidate_at_date->where('store', $store);
                    if (count($consolidate_at_date_at_store) == 0) {
                        $empty_store[] = $store;
                    }
                }
                if (count($empty_store) > 0) {
                    $not_recorded[] = ['date' => $date, 'store' => $empty_store];
                }
            }
        }

        return ['not_recorded' => $not_recorded, 'consolidate'   => $consolidate];
    }

    public function store()
    {
        $api_url = "{$this->quinosAPI}/staffs/listStore";
        // $data_store=$this->guzzleReq($api_url);
        // $stores=json_decode($data_store);
        // foreach ($stores as $key => $value) {

        // }
        return $this->guzzleReq($api_url);
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
