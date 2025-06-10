<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DailyTransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function __construct()
    {
        $this->middleware('auth');
    }
    public function index($date1, $date2, $store)
    {
        $start_date = \Carbon\Carbon::createFromFormat('Y-m-d', $date1);
        $end_date = \Carbon\Carbon::createFromFormat('Y-m-d', $date2);
        $dates = [];
        while ($start_date->lte($end_date)) {

            array_push($dates, $start_date->format('Y-m-d'));
            $start_date->addDay();
        }
        // return response()->json($stores);
        $client = new \GuzzleHttp\Client();
        $store = str_replace('*', '.', $store);
        $return_data = [];
        $return_each_date = [];
        // return response()->json($dates);
        foreach ($dates as $key => $date_one) {
            if ($store == 'All') {
                // $store = ['EXPAT-BALI', 'EXPAT.SBY', 'EXPAT.JAKARTA', 'AF.JUANDA'];
                $stores = Store::getStore(Auth::user()->client_id);
                $store = $stores->pluck('name')->toArray();
                foreach ($store as $key => $value) {
                    $url = "https://quinoscloud.com/cloud/transactions/getTransactionByDate/6da1b2274c6e008c6c7eb099f6e0bd051e6ada7a/" . $value . "/" . $date_one;
                    $response = $client->request('GET', $url);
                    $r_data = json_decode($response->getBody()->getContents(), true);
                    $return_each_date = array_merge($return_each_date, $r_data);
                }
            } else {
                $url = "https://quinoscloud.com/cloud/transactions/getTransactionByDate/6da1b2274c6e008c6c7eb099f6e0bd051e6ada7a/" . $store . "/" .
                    $date_one;

                $response = $client->request('GET', $url);
                $return_each_date = json_decode($response->getBody()->getContents(), true);
            }
            $return_data = array_merge($return_data, $return_each_date);
        }



        $arr_data = [];
        $all_data = new \stdClass();
        $total_data = new \stdClass();

        $total_total = 0;
        $total_cost = 0;
        $total_revenue = 0;
        $total_profit = 0;


        foreach ($return_data as $key => $value) {
            $trans_line = [];
            $total_quantity = 0;
            if ($value["Transaction"]["void_transaction"] == "1" || $value["Transaction"]["no_sales"] == "1") {
                continue;
            }

            foreach ($value["TransactionLine"] as $key => $transline) {

                if ((empty($transline['item_code']) || $transline['item_code'] == null)) {
                    if ($transline["unit_price"] == '0' || empty($transline["unit_price"])) {
                        continue;
                    }
                }

                $data = new \stdClass();
                $parent_item_code = $transline["parent_item_code"];

                if (!empty($parent_item_code) && $parent_item_code != null && $transline["unit_price"] == '0') {
                    $index = array_search($parent_item_code, array_column($trans_line, 'item_code'));
                    if ($index !== false && strpos(strtolower($transline["description"]), 'sugar') == false) {
                        $trans_line[$index]->modifier = $transline["description"];
                    }
                } else {
                    $rounding = $this->strToNum($value["Transaction"]["rounding"]);
                    if (count($trans_line) > 0) {
                        $rounding = 0;
                    }


                    $data->date = $value["Transaction"]["date"];
                    $data->invoice = $value["Transaction"]["transaction_id"];
                    $data->pax =  $value["Transaction"]["pax"];
                    $data->tbl =  $value["Transaction"]["table_name"];
                    $data->customer =  (string) $value["Transaction"]["customer_name"];
                    $data->phone =  $value["Transaction"]["customer_code"];
                    $data->store_code =  $value["Transaction"]["store_code"];
                    $data->sales_type =  $value["Transaction"]["sales_type"];
                    $data->category =  $transline["category_code"];
                    $data->description =  $transline["description"];
                    $data->modifier =  "";
                    $data->item_code =  $transline["item_code"];
                    $data->time_hour =  $value["Transaction"]["close_time"];
                    $data->quantity =  $transline["quantity"];
                    $data->quantity_percent =  0;
                    $data->revenue = $this->strToNum($transline["unit_price"]) * $this->strToNum($transline["quantity"]); //0
                    $data->revenue_percent = $this->percentString($data->revenue, $value["Transaction"]["subtotal"]); //0
                    $data->disc_desc =  $transline["item_discount_code"];
                    $data->discount =  $this->strToNum($transline["discount"]);
                    $data->cost = $this->strToNum($transline["unit_cost"]);
                    $data->profit =  $data->revenue - $data->discount - $data->cost; //0
                    $data->svc_charge =  $transline["service_charge"];
                    $data->tax =  $this->strToNum($transline["tax1"]) + $this->strToNum($transline["tax2"]);
                    $data->rounding =  $rounding;
                    $data->total =   number_format($data->revenue  - $data->discount + $data->svc_charge + $data->tax + $rounding, 2);
                    $data->payment_method =  $value["Transaction"]["payments"];
                    if ($transline["void_line"] == "0") {
                        $total_quantity += $this->strToNum($data->quantity);
                        $total_cost += $data->cost;
                        $total_revenue += $data->revenue;
                        $total_profit += $data->profit;
                        $total_total += $data->revenue  - $data->discount + $data->svc_charge + $data->tax + $rounding;

                        array_push($trans_line, $data);
                    }
                }
            }


            foreach ($trans_line as $key => $val) {

                $val->quantity_percent = $this->percentString($val->quantity, $total_quantity);
            }

            $arr_data = array_merge($arr_data, $trans_line);

            // echo $value["Transaction"]["date"];

        }
        $total_data->total_revenue = $total_revenue;
        $total_data->total_cost = $total_cost;
        $total_data->total_profit = $total_profit;
        $total_data->total_total = $total_total;
        $all_data->trans = $arr_data;
        $all_data->total = $total_data;

        return response()->json($all_data);
    }
    private function strToNum($str)
    {
        $str = str_replace(['$', ','], '', $str);
        return (float)$str;
    }

    private function percentString($a, $b)
    {
        if ($this->strToNum($b) == 0) {
            return "0%";
        }
        $percent = $this->strToNum($a) / $this->strToNum($b) * 100;
        return number_format($percent, 2) . "%";
    }
}
