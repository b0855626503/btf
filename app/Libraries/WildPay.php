<?php

namespace App\Libraries;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WildPay
{
    public function create($url, $data)
    {
        $parterId = config('wildpay.partner_id') ?? '';
        $return['success'] = false;
        $return['msg'] = __('app.topup.fail');
        $return['code'] = 1;

        $response = Http::timeout(30)
            ->withHeaders([
                'x-wildpay-partnerid' => $parterId,
                'x-wildpay-signature' => $this->generateSignature($parterId, config('wildpay.secret_key'), $data),
            ])
            ->asJson()->post($url, $data);

        $debug = [
            'json' => $response->json(),
            'success' => $response->successful(),
            'fail' => $response->failed(),
            'status' => $response->status(),
            'serverError' => $response->serverError(),
            'clientError' => $response->clientError(),
            'date' => now()->toDateTimeString(),
            'param' => $data,
        ];

        if (! File::exists(storage_path('logs/wildpay'))) {
            File::makeDirectory(storage_path('logs/wildpay'));
        }
        Log::channel('wildpay_deposit_create')->info('เริ่มสร้างรายการฝาก', [
            'debug' => $debug,
        ]);

        if ($response->successful()) {

            $result = $response->json();

            if ($result['code'] === 0) {
                $return['success'] = true;
                $return['code'] = $result['code'];
                $return['data'] = $result['data'];
                $return['msg'] = __('app.topup.create');
            } else {
                $return['msg'] = $result['msg'] ?? __('app.topup.fail');
            }

        } else {
            $result = $response->json();
            $return['code'] = $result['code'] ?? 1;
            $return['msg'] = $result['msg'] ?? __('app.topup.fail');
        }

        return $return;
    }

    public function generateSignature(string $partnerId, string $secret, array $body): string
    {
        $keyString = $partnerId.':'.$this->buildKeyValueString($body);

        // นำ signature ที่ได้ไปใส่ใน x-wildpay-signature
        return hash_hmac('sha256', $keyString, $secret);
    }

    private function buildKeyValueString(array $obj): string
    {
        $flat = $this->flattenObject($obj);
        ksort($flat); // sort keys alphabetically

        $keyValuePairs = [];
        foreach ($flat as $key => $value) {
            // handle boolean to string
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }

            $keyValuePairs[] = "{$key}={$value}";
        }

        return implode('&', $keyValuePairs);
    }

    private function flattenObject(array $obj, string $prefix = ''): array
    {
        $result = [];

        foreach ($obj as $key => $value) {
            $fullKey = $prefix !== '' ? $prefix.'.'.$key : $key;

            if (is_array($value) && array_values($value) !== $value) {
                $result = array_merge($result, $this->flattenObject($value, $fullKey));
            } else {
                $result[$fullKey] = $value;
            }
        }

        return $result;
    }

    public function create_withdraw($url, $data)
    {
        $parterId = config('wildpay.partner_id') ?? '';
        $return['success'] = false;
        $return['msg'] = __('app.withdraw.fail');
        $return['code'] = 1;

        $response = Http::timeout(30)
            ->withHeaders([
                'x-wildpay-partnerid' => $parterId,
                'x-wildpay-signature' => $this->generateSignature($parterId, config('wildpay.secret_key'), $data),
            ])
            ->asJson()->post($url, $data);

        $debug = [
            'json' => $response->json(),
            'success' => $response->successful(),
            'fail' => $response->failed(),
            'status' => $response->status(),
            'serverError' => $response->serverError(),
            'clientError' => $response->clientError(),
            'date' => now()->toDateTimeString(),
            'param' => $data,
        ];

        if (! File::exists(storage_path('logs/wildpay'))) {
            File::makeDirectory(storage_path('logs/wildpay'));
        }
        Log::channel('wildpay_withdraw_create')->info('เริ่มสร้างรายการถอน', [
            'debug' => $debug,
        ]);

        if ($response->successful()) {

            $result = $response->json();

            if ($result['code'] === 0) {
                $return['success'] = true;
                $return['code'] = $result['code'];
                $return['data'] = $result['data'];
                $return['msg'] = 'Success';
            } else {
                $return['msg'] = $result['msg'] ?? __('app.withdraw.fail');
            }

        } else {
            $result = $response->json();
            $return['code'] = $result['code'] ?? 1;
            $return['msg'] = $result['msg'] ?? __('app.withdraw.fail');
        }

        return $return;
    }

    public function create_cancel($url, $data)
    {
        $parterId = config('wildpay.partner_id') ?? '';
        $return['success'] = false;
        $return['msg'] = __('app.withdraw.fail');
        $return['code'] = 1;

        $response = Http::timeout(30)
            ->withHeaders([
                'x-wildpay-partnerid' => $parterId,
                'x-wildpay-signature' => $this->generateSignature($parterId, config('wildpay.secret_key'), $data),
            ])
            ->asJson()->patch($url, $data);

        $debug = [
            'json' => $response->json(),
            'success' => $response->successful(),
            'fail' => $response->failed(),
            'status' => $response->status(),
            'serverError' => $response->serverError(),
            'clientError' => $response->clientError(),
            'date' => now()->toDateTimeString(),
            'param' => $data,
        ];

        if (! File::exists(storage_path('logs/wildpay'))) {
            File::makeDirectory(storage_path('logs/wildpay'));
        }
        Log::channel('wildpay_cancel_create')->info('เริ่มสร้างรายการยกเลิก', [
            'debug' => $debug,
        ]);

        if ($response->successful()) {

            $result = $response->json();

            if ($result['code'] === 0) {
                $return['success'] = true;
                $return['code'] = $result['code'];
                $return['data'] = $result['data'];
                $return['msg'] = 'Success';
            } else {
                $return['msg'] = $result['msg'] ?? __('app.withdraw.fail');
            }

        } else {
            $result = $response->json();
            $return['code'] = $result['code'] ?? 1;
            $return['msg'] = $result['msg'] ?? __('app.withdraw.fail');
        }

        return $return;
    }

    public function create_balance($url, $data)
    {
        $parterId = config('wildpay.partner_id') ?? '';
        $return['success'] = false;
        $return['msg'] = __('app.withdraw.fail');
        $return['code'] = 1;

        $response = Http::timeout(30)
            ->withHeaders([
                'x-wildpay-partnerid' => $parterId,
                'x-wildpay-signature' => $this->generateSignature($parterId, config('wildpay.secret_key'), $data),
            ])
            ->asJson()->get($url);

        //        $debug = [
        //            'json' => $response->json(),
        //            'success' => $response->successful(),
        //            'fail' => $response->failed(),
        //            'status' => $response->status(),
        //            'serverError' => $response->serverError(),
        //            'clientError' => $response->clientError(),
        //            'date' => now()->toDateTimeString(),
        //            'param' => $data,
        //        ];

        //        if (! File::exists(storage_path('logs/wildpay'))) {
        //            File::makeDirectory(storage_path('logs/wildpay'));
        //        }
        //        Log::channel('wildpay_cancel_create')->info('เริ่มสร้างรายการยกเลิก', [
        //            'debug' => $debug,
        //        ]);

        if ($response->successful()) {

            $result = $response->json();

            if ($result['code'] === 0) {
                $return['success'] = true;
                $return['code'] = $result['code'];
                $return['data'] = $result['data'];
                $return['msg'] = 'Success';
            } else {
                $return['msg'] = $result['msg'] ?? __('app.withdraw.fail');
            }

        } else {
            $result = $response->json();
            $return['code'] = $result['code'] ?? 1;
            $return['msg'] = $result['msg'] ?? __('app.withdraw.fail');
        }

        return $return;
    }

    public function Banks($bankcode)
    {

        switch ($bankcode) {
            case '1':
                $result = 'BBL';
                break;
            case '2':
                $result = 'KBANK';
                break;
            case '3':
                $result = 'KTB';
                break;
            case '4':
                $result = 'SCB';
                break;
            case '5':
                $result = 'GHB';
                break;
            case '6':
                $result = 'KKP';
                break;
            case '7':
                $result = 'CIMB';
                break;
            case '8':
                $result = 'IBANK';
                break;
            case '9':
                $result = 'TISCO';
                break;
            case '10':
            case '15':
                $result = 'TTB';
                break;
            case '11':
                $result = 'BAY';
                break;
            case '12':
                $result = 'UOBT';
                break;
            case '13':
                $result = 'LHBANK';
                break;
            case '14':
                $result = 'GSB';
                break;
            case '17':
                $result = 'BAAC';
                break;
            case '19':
                $result = 'TTB';
                break;
            default:
                $result = false;
                break;
        }

        return $result;

    }
}
