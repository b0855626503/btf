<?php


namespace App\Libraries;


use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class SuperrichPay
{

    public function removeBOM($data)
    {
        if (0 === strpos(bin2hex($data), 'efbbbf')) {
            return substr($data, 3);
        }
        return $data;
    }

    public function Auth()
    {
//        $return['success'] = false;


        $username = config('app.superrich_user'); //Merchant username
        $api_key = config('app.superrich_apikey'); //Api key get from panel settings page
        $send = array('username' => $username , 'api_key' => $api_key);
        $apiurl = config('app.superrich_apiurl')."/merchant/auth";
        $response = Http::timeout(30)->asForm()->post($apiurl, $send);
        if ($response->successful()) {

            $result = $response->json();
            if ($result['status'] === true) {
                $return['success'] = true;
                return $result['auth'];
            }

        }


        return '';
    }

    public function create($url, $data)
    {
        $return['success'] = false;


        $response = Http::timeout(30)->asForm()->post($url, $data);


        $api = $response->json();

        $debug = [
            'json' => $response->json(),
            'success' => $response->successful(),
            'fail' => $response->failed(),
            'status' => $response->status(),
            'serverError' => $response->serverError(),
            'clientError' => $response->clientError(),
            'date' => now()->toDateTimeString(),
        ];

        if (!File::exists(storage_path('logs/superrich'))) {
            File::makeDirectory(storage_path('logs/superrich'));
        }
        $path = storage_path('logs/superrich/out_' . now()->format('Y_m_d') . '.log');
        file_put_contents($path, print_r($debug, true), FILE_APPEND);


        if ($response->successful()) {

            $result = $response->json();
            if ($result['status'] === true) {
                $return['success'] = true;
                $return['url'] = $result['p_url'];
            }

        }


        return $return;
    }

    public function create_payout($url, $data)
    {
        $return['success'] = false;
        $return['message'] = '';


        $response = Http::timeout(30)->withHeaders(['transactiontoken' => config('app.papayapay_token')])->acceptJson()->post($url, $data);



        $api = $response->json();

        $debug = [
            'json' => $response->json(),
            'success' => $response->successful(),
            'fail' => $response->failed(),
            'status' => $response->status(),
            'serverError' => $response->serverError(),
            'clientError' => $response->clientError(),
            'date' => now()->toDateTimeString(),
        ];

        if (!File::exists(storage_path('logs/papayapay'))) {
            File::makeDirectory(storage_path('logs/papayapay'));
        }
        $path = storage_path('logs/papayapay/payout_' . now()->format('Y_m_d') . '.log');
        file_put_contents($path, print_r($debug, true), FILE_APPEND);


        if ($response->successful()) {

            $result = $response->json();
            if ($result['data']['fundOutStatus'] === 'PROCESSING') {
                $return['success'] = true;
                $return['message'] = 'Complete';
                $return['data'] = $result['data'];

            }else{
                $return['message'] = $result['data']['message'];
            }

        }else{
            $result = $response->json();
            $return['message'] = $result['data']['message'];

        }


        return $return;
    }



    public function check_uuid($uuid,$id)
    {

        $data = app('Gametech\Payment\Repositories\WithdrawRepository')->findOneWhere(['txid' => $uuid]);
        if (isset($data)) {
            $uuid = 'PAY'.str_pad($id, 7, "0", STR_PAD_LEFT).'X'.date('His');
            return $this->check_uuid($uuid,$id);
        } else {
            return $uuid;
        }
    }

    public function check_uuid2($uuid,$id)
    {

        $data = app('Gametech\Payment\Repositories\WithdrawSeamlessRepository')->findOneWhere(['txid' => $uuid]);
        if (isset($data)) {
            $uuid = 'PAY'.str_pad($id, 7, "0", STR_PAD_LEFT).'X'.date('His');
            return $this->check_uuid2($uuid,$id);
        } else {
            return $uuid;
        }
    }
    public function Banks($bankcode)
    {

        switch ($bankcode) {
            case '1':
                $result = '106';
                break;
            case '2':
                $result = '107';
                break;
            case '3':
                $result = '108';
                break;
            case '4':
                $result = '110';
                break;
            case '5':
                $result = '117';
                break;
            case '6':
                $result = '121';
                break;
            case '7':
                $result = '113';
                break;
            case '8':
                $result = '120';
                break;
            case '9':
                $result = '158';
                break;
            case '19':
            case '15':
            case '10':
                $result = '109';
                break;
            case '11':
                $result = '115';
                break;
            case '12':
                $result = '114';
                break;
            case '13':
                $result = '123';
                break;
            case '14':
                $result = '116';
                break;
            case '17':
                $result = '118';
                break;
            default:
                $result = '500';
                break;
        }
        return $result;

    }


}
