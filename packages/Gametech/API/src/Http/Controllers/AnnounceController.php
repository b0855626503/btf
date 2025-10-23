<?php

namespace Gametech\API\Http\Controllers;

use App\Events\RealTimeMessageAll;
use DateTime;
use Gametech\Core\Models\ConfigServerProxy;
use Gametech\Core\Repositories\AnnounceRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;
use Throwable;

class AnnounceController extends AppBaseController
{
    protected $_config;

    protected $repository;

    public function __construct(AnnounceRepository $repository)
    {
        $this->_config = request('_config');

        $this->middleware('api');

        $this->repository = $repository;
    }

    public function Announce(Request $request)
    {
        $id = 1;
        $chk = $this->repository->findOrFail($id);

        if (empty($chk)) {
            return $this->sendError('ไม่สามารถบันทึกข้อมูลได้', 200);
        }

        $data['content'] = $request->input('message');
        $data['new'] = 'Y';

        $this->repository->update($data, $id);

        return $this->sendSuccess('บันทึกข้อมูลแล้ว');

    }

    public function broadcast(Request $request)
    {

        $message = $request->input('message');
        broadcast(new RealTimeMessageAll($message));

        $responses = [];

        $urls = ['http://api.gb168slot.com/api/broadcast', 'http://api.wsw88.click/api/broadcast'];
        foreach ($urls as $url) {
            try {
                $res = Http::timeout(15)->post($url, ['message' => $message]);
                $responses[$url] = $res->successful() ? 'OK' : 'FAILED: '.$res->body();
            } catch (Throwable $e) {
                $responses[$url] = 'ERROR: '.$e->getMessage();
            }
        }

        return response()->json($responses);
    }

    public function getDashBoard()
    {
        $config = core()->getConfigData();
        $startdate = now()->toDateString();

        $deposit = app('Gametech\Payment\Repositories\BankPaymentRepository')->income()->active()->whereIn('status', [0, 1])->whereDate('date_create', $startdate)->sum('value');
        $deposit_cnt = app('Gametech\Payment\Repositories\BankPaymentRepository')->income()->active()->whereIn('status', [0, 1])->whereDate('date_create', $startdate)->count();
        $withdraw = app('Gametech\Payment\Repositories\WithdrawRepository')->active()->complete()->whereDate('date_approve', $startdate)->sum('amount');
        $withdraw_cnt = app('Gametech\Payment\Repositories\WithdrawRepository')->active()->complete()->whereDate('date_approve', $startdate)->count();
        $member_cnt = app('Gametech\Member\Repositories\MemberRepository')->active()->whereDate('date_regis', $startdate)->count();
        $banks = app('Gametech\Payment\Repositories\BankAccountRepository')->getAccountInAll();
        $banks = $banks->map(function ($items) {
            return [
                'text' => $items->bank->shortcode.' '.$items->acc_no,
                'value' => core()->currency($items->balance),
                'update' => core()->formatDate($items->checktime, 'd/m/y H:i:s'),
            ];
        });

        $response = [
            'title' => $config['sitename'].' ('.config('game.starvegas.merchant_admin_name').')',
            'data' => [
                ['method' => 'deposit', 'icon' => 'fas fa-plus-circle', 'color' => 'bg-info', 'text' => 'ยอดฝาก', 'value' => core()->currency($deposit)],
                ['method' => 'deposit_1', 'icon' => 'fas fa-plus', 'color' => 'bg-info', 'text' => 'จำนวนบิลฝาก', 'value' => $deposit_cnt],
                ['method' => 'withdraw', 'icon' => 'fas fa-minus-circle', 'color' => 'bg-danger', 'text' => 'ยอดถอน', 'value' => core()->currency($withdraw)],
                ['method' => 'withdraw_1', 'icon' => 'fas fa-minus', 'color' => 'bg-danger', 'text' => 'จำนวนบิลถอน', 'value' => $withdraw_cnt],
                ['method' => 'member', 'icon' => 'fas fa-user', 'color' => 'bg-success', 'text' => 'ลูกค้าสมัครใหม่', 'value' => $member_cnt],
                ['method' => 'agent', 'icon' => 'fas fa-user', 'color' => 'bg-success', 'text' => 'Agent', 'value' => config('game.starvegas.merchant_admin_name')],
            ],
            'bank' => $banks,
            'money' => [
                ['method' => 'deposit', 'icon' => 'fas fa-plus-circle', 'color' => 'bg-info', 'text' => 'ยอดฝาก', 'value' => core()->currency($deposit)],
                ['method' => 'withdraw', 'icon' => 'fas fa-minus-circle', 'color' => 'bg-danger', 'text' => 'ยอดถอน', 'value' => core()->currency($withdraw)],
                ['method' => 'withdraw', 'icon' => 'fas fa-minus-circle', 'color' => 'bg-danger', 'text' => 'คงเหลือ', 'value' => core()->currency($deposit - $withdraw)],

            ],
        ];

        return Response::json($response);
    }

    public function index()
    {
        $response = $this->repository->findOrFail(1);

        return $this->sendResponse($response, 'complete');
    }

    public function AppLogin(Request $request)
    {
        $user = $request->input('username');
        $token = $request->input('token');

        $chk = ConfigServerProxy::with('package')->where('accno', $user)->where('token', $token)->first();
        //        dd($chk);
        if ($chk) {
            $data['status'] = 'ok';
            $data['url'] = $chk->url;
            $data['title'] = $chk->package->title;
            $data['value'] = $chk->package->value;
            $data['type'] = $chk->package->type;
            $data['sender'] = $chk->package->sender;

            return response()->json($data);
        } else {
            $data['status'] = 'fail';
            $data['url'] = '';

            return response()->json($data);
        }
    }

    public function scb_superrich($mobile, Request $request)
    {
        //        $mobile = '1882028767';
        $date = now()->toDateString();
        $datenow = now()->toDateTimeString();

        $data = DB::connection('superrich')->table('bankaccount')->where('accountno', $mobile)->where('status', 1)->where('status_auto', 'Y')->where('enable', 'Y')->first();
        //        $data = app('Gametech\Payment\Repositories\BankAccountRepository')->getAccountOneNew('scb', $mobile);
        if (! $data) {
            return 1;
        }
        //
        //        if ($data->webhook == 'N') {
        //            return 1;
        //        }
        //
        //        $data->checktime = $datenow;
        //        $data->save();

        $messages = [];
        $message = $request->all();

        $path = storage_path('logs/scb/webhook_superrich_'.$mobile.'_'.now()->format('Y_m_d').'.log');
        file_put_contents($path, print_r($message, true), FILE_APPEND);
        //        return true;

        //        $path = storage_path('logs/scb/webhook_' . $mobile . '_' . now()->format('Y_m_d') . '.log');
        //        file_put_contents($path, print_r($message, true), FILE_APPEND);
        //        return true;
        //        $path = storage_path('ttb/webhook_' . $mobile . '_' . now()->format('Y_m_d') . '.log');
        //        file_put_contents($path, print_r($message, true), FILE_APPEND);

        if ($message['username'] != $mobile) {
            return true;
        }

        $displayOriginatingAddress = $message['address'];
        if ($displayOriginatingAddress != '027777777' && $displayOriginatingAddress != '02 777 7777') {
            return true;
        }

        $title = $message['body'];
        $titles = stripos($title, 'จาก');
        if ($titles !== false) {

            $from = Str::of($title)->between('จาก', 'เข้า')->__toString();

            $contents = explode(' ', $title);
            $amount = $contents[1];

            $amount = str_replace(',', '', $amount);
            $messages['amount'] = number_format((float) $amount, 2, '.', '');

            $banks = explode('/', $from);
            $bank = $banks['0'];
            if ($bank == 'KBNK') {
                $bank = 'KBANK';
            }
            if ($bank == 'GSBA') {
                $bank = 'GSB';
            }
            $messages['bank'] = $bank;
            $messages['acc'] = $banks['1'];

            $datetimeall = explode('@', $contents[0]);
            $dates = explode('/', $datetimeall[0]);
            $date = date('Y').'-'.$dates[1].'-'.$dates[0];
            $time = $datetimeall[1].':00';
            $messages['current_date'] = $date;
            $messages['current_time'] = $time;
            $messages['date'] = $datenow;

            $remains = $contents[3];
            $remains = str_replace('ใช้ได้', '', $remains);
            $remains = str_replace('บ', '', $remains);
            $remains = str_replace(',', '', $remains);
            $remain = number_format((float) $remains, 2, '.', '');
            $messages['remain'] = $remain;

            file_put_contents($path, print_r($messages, true), FILE_APPEND);

            //            $date = str_replace("/", "-", $messages['current_date']);

            //            $time = Str::of($title)->explode('@');
            $datetime = $date.' '.$time;

            $hash = md5($data->code.$datetime.$amount.$messages['bank'].$messages['acc']);

            $checktime = strtotime(date('Y-m-d H:i:s'));
            $check = DB::connection('superrich')->table('bank_payment')->where('tx_hash', $hash)->first();
            if (! $check) {
                DB::connection('superrich')->table('bank_payment')->insert([
                    'bank' => strtolower('scb_'.$mobile),
                    'bankstatus' => 1,
                    'bankname' => 'SCB',
                    'date_create' => date('Y-m-d H:i:s'),
                    'checktime' => $checktime,
                    'time' => $checktime,
                    'channel' => 'SMS',
                    'value' => $amount,
                    'tx_hash' => $hash,
                    'detail' => $messages['bank'].' '.$messages['acc'],
                    'atranferer' => '',
                ]);

                DB::connection('superrich')->table('bankbalance')->where('bank', $data->bank.'_'.$data->accountno)->update(['updatetime' => $checktime, 'balance' => $remain]);
            }

        }

        return true;
    }

    public function scb_superrich69($mobile, Request $request)
    {
        //        $mobile = '1882028767';
        $date = now()->toDateString();
        $datenow = now()->toDateTimeString();

        $data = DB::connection('superrich69')->table('bankaccount')->where('accountno', $mobile)->where('status', 1)->where('status_auto', 'Y')->where('enable', 'Y')->first();
        //        $data = app('Gametech\Payment\Repositories\BankAccountRepository')->getAccountOneNew('scb', $mobile);
        if (! $data) {
            return 1;
        }
        //
        //        if ($data->webhook == 'N') {
        //            return 1;
        //        }
        //
        //        $data->checktime = $datenow;
        //        $data->save();

        $messages = [];
        $message = $request->all();

        $path = storage_path('logs/scb/webhook_superrich69_'.$mobile.'_'.now()->format('Y_m_d').'.log');
        file_put_contents($path, print_r($message, true), FILE_APPEND);
        //        return true;

        //        $path = storage_path('logs/scb/webhook_' . $mobile . '_' . now()->format('Y_m_d') . '.log');
        //        file_put_contents($path, print_r($message, true), FILE_APPEND);
        //        return true;
        //        $path = storage_path('ttb/webhook_' . $mobile . '_' . now()->format('Y_m_d') . '.log');
        //        file_put_contents($path, print_r($message, true), FILE_APPEND);

        if ($message['username'] != $mobile) {
            return true;
        }

        $displayOriginatingAddress = $message['address'];
        if ($displayOriginatingAddress != '027777777' && $displayOriginatingAddress != '02 777 7777') {
            return true;
        }

        $title = $message['body'];
        $titles = stripos($title, 'จาก');
        if ($titles !== false) {

            $from = Str::of($title)->between('จาก', 'เข้า')->__toString();

            $contents = explode(' ', $title);
            $amount = $contents[1];

            $amount = str_replace(',', '', $amount);
            $messages['amount'] = number_format((float) $amount, 2, '.', '');

            $banks = explode('/', $from);
            $bank = $banks['0'];
            if ($bank == 'KBNK') {
                $bank = 'KBANK';
            }
            if ($bank == 'GSBA') {
                $bank = 'GSB';
            }
            $messages['bank'] = $bank;
            $messages['acc'] = $banks['1'];

            $datetimeall = explode('@', $contents[0]);
            $dates = explode('/', $datetimeall[0]);
            $date = date('Y').'-'.$dates[1].'-'.$dates[0];
            $time = $datetimeall[1].':00';
            $messages['current_date'] = $date;
            $messages['current_time'] = $time;
            $messages['date'] = $datenow;

            $remains = $contents[3];
            $remains = str_replace('ใช้ได้', '', $remains);
            $remains = str_replace('บ', '', $remains);
            $remains = str_replace(',', '', $remains);
            $remain = number_format((float) $remains, 2, '.', '');
            $messages['remain'] = $remain;

            file_put_contents($path, print_r($messages, true), FILE_APPEND);

            //            $date = str_replace("/", "-", $messages['current_date']);

            //            $time = Str::of($title)->explode('@');
            $datetime = $date.' '.$time;

            $hash = md5($data->code.$datetime.$amount.$messages['bank'].$messages['acc']);

            $checktime = strtotime(date('Y-m-d H:i:s'));
            $check = DB::connection('superrich69')->table('bank_payment')->where('tx_hash', $hash)->first();
            if (! $check) {
                DB::connection('superrich69')->table('bank_payment')->insert([
                    'bank' => strtolower('scb_'.$mobile),
                    'bankstatus' => 1,
                    'bankname' => 'SCB',
                    'checktime' => $checktime,
                    'date_create' => date('Y-m-d H:i:s'),
                    'time' => $checktime,
                    'channel' => 'SMS',
                    'value' => $amount,
                    'tx_hash' => $hash,
                    'detail' => $messages['bank'].' '.$messages['acc'],
                    'atranferer' => '',
                ]);

                DB::connection('superrich69')->table('bankbalance')->where('bank', $data->bank.'_'.$data->accountno)->update(['updatetime' => $checktime, 'balance' => $remain]);
            }

        }

        return true;
    }

    public function ttb_superrich69($mobile, Request $request)
    {
        $date = now()->toDateString();
        $datenow = now()->toDateTimeString();

        $data = DB::connection('superrich69')->table('bankaccount')->where('accountno', $mobile)->where('status', 1)->where('status_auto', 'Y')->where('enable', 'Y')->first();
        if (! $data) {
            return 1;
        }

        //        $data->checktime = $datenow;
        //        $data->save();

        $messages = [];
        $message = $request->all();

        $path = storage_path('logs/ttb/webhook_'.$mobile.'_'.now()->format('Y_m_d').'.log');
        file_put_contents($path, print_r($message, true), FILE_APPEND);

        //        $path = storage_path('ttb/webhook_' . $mobile . '_' . now()->format('Y_m_d') . '.log');
        //        file_put_contents($path, print_r($message, true), FILE_APPEND);

        if ($message['type'] != 'noti') {
            return 1;
        }

        $title = $message['body'];

        $titles = stripos($title, 'มีเงิน');
        if ($titles !== false) {

            //            $from = Str::of($title)->between('จาก', 'เข้า')->__toString();

            //            $contents = explode(' ', $title);
            //            $amount = $contents[1];

            preg_match('/มีเงิน([\d,\.]+)บ/', $title, $amountMatch);
            $amount = str_replace(',', '', $amountMatch[1]);

            $amount = str_replace(',', '', $amount);
            $messages['amount'] = number_format((float) $amount, 2, '.', '');

            // ชื่อธนาคารต้นทาง (ถ้ามี)
            preg_match('/จาก\s+([A-Z]+)\s+X?(\d{4})\s+([^\s]+ [^\s]+)/u', $title, $fromMatch);

            $fromBank = $fromMatch[1] ?? null;      // KBANK
            $fromAcc = $fromMatch[2] ?? null;      // 3912
            $fromName = $fromMatch[3] ?? null;      // สฤษดิ์ พุทธ

            // ถ้า format ชื่อมี "นาย", "นาง", ฯลฯ ให้ดึง 2 คำถัดไปหลังชื่อคำนำหน้า
            if (preg_match('/จาก\s+([A-Z]+)\s+X?(\d{4})\s+(นาย|นาง|น.ส.|นางสาว)?\s*([^\s]+ [^\s]+)/u', $title, $fromFullMatch)) {
                $fromName = trim(($fromFullMatch[3] ?? '').' '.$fromFullMatch[4]);
            }

            $messages['bank'] = $fromBank;
            $messages['acc'] = $fromAcc;

            preg_match('/(\d{2}\/\d{2}\/\d{2})@(\d{2}:\d{2})/', $title, $dateMatch);
            $date = $dateMatch[1];
            $time = $dateMatch[2];
            $dateObj = DateTime::createFromFormat('d/m/y H:i', $date.' '.$time);
            $datetime = $dateObj ? $dateObj->format('Y-m-d H:i:s') : null;

            $messages['current_date'] = $date;
            $messages['current_time'] = $time;
            $messages['date'] = $datenow;

            // ดึงยอดคงเหลือ
            preg_match('/เหลือ([\d,\.]+)บ/', $title, $remainMatch);
            $remains = str_replace(',', '', $remainMatch[1]);

            $remain = number_format((float) $remains, 2, '.', '');
            $messages['remain'] = $remain;

            file_put_contents($path, print_r($messages, true), FILE_APPEND);

            //            $date = str_replace("/", "-", $messages['current_date']);

            //            $time = Str::of($title)->explode('@');
            //            $datetime = $date.' '.$time;

            $hash = md5($data->code.$datetime.$amount.$remains.$messages['bank'].$messages['acc']);

            $checktime = strtotime(date('Y-m-d H:i:s'));
            $check = DB::connection('superrich69')->table('bank_payment')->where('tx_hash', $hash)->first();
            if (! $check) {
                DB::connection('superrich69')->table('bank_payment')->insert([
                    'bank' => strtolower('ttb_'.$mobile),
                    'bankstatus' => 1,
                    'bankname' => 'NOTI',
                    'checktime' => $checktime,
                    'date_create' => date('Y-m-d H:i:s'),
                    'time' => $checktime,
                    'channel' => 'SMS',
                    'value' => $amount,
                    'tx_hash' => $hash,
                    'detail' => $fromName.' '.$messages['bank'].' '.$messages['acc'],
                    'atranferer' => '',
                ]);

                DB::connection('superrich69')->table('bankbalance')->where('bank', $data->bank.'_'.$data->accountno)->update(['updatetime' => $checktime, 'balance' => $remain]);
            }

        }

    }

    public function ttb_superrich($mobile, Request $request)
    {
        $date = now()->toDateString();
        $datenow = now()->toDateTimeString();

        $data = DB::connection('superrich')->table('bankaccount')->where('accountno', $mobile)->where('status', 1)->where('status_auto', 'Y')->where('enable', 'Y')->first();
        if (! $data) {
            return 1;
        }

        //        $data->checktime = $datenow;
        //        $data->save();

        $messages = [];
        $message = $request->all();

        $path = storage_path('logs/ttb/webhook_'.$mobile.'_'.now()->format('Y_m_d').'.log');
        file_put_contents($path, print_r($message, true), FILE_APPEND);

        //        $path = storage_path('ttb/webhook_' . $mobile . '_' . now()->format('Y_m_d') . '.log');
        //        file_put_contents($path, print_r($message, true), FILE_APPEND);

        if ($message['type'] != 'noti') {
            return 1;
        }

        $title = $message['body'];

        $titles = stripos($title, 'มีเงิน');
        if ($titles !== false) {

            //            $from = Str::of($title)->between('จาก', 'เข้า')->__toString();

            //            $contents = explode(' ', $title);
            //            $amount = $contents[1];

            preg_match('/มีเงิน([\d,\.]+)บ/', $title, $amountMatch);
            $amount = str_replace(',', '', $amountMatch[1]);

            $amount = str_replace(',', '', $amount);
            $messages['amount'] = number_format((float) $amount, 2, '.', '');

            // ชื่อธนาคารต้นทาง (ถ้ามี)
            preg_match('/จาก\s+([A-Z]+)\s+X?(\d{4})\s+([^\s]+ [^\s]+)/u', $title, $fromMatch);

            $fromBank = $fromMatch[1] ?? null;      // KBANK
            $fromAcc = $fromMatch[2] ?? null;      // 3912
            $fromName = $fromMatch[3] ?? null;      // สฤษดิ์ พุทธ

            // ถ้า format ชื่อมี "นาย", "นาง", ฯลฯ ให้ดึง 2 คำถัดไปหลังชื่อคำนำหน้า
            if (preg_match('/จาก\s+([A-Z]+)\s+X?(\d{4})\s+(นาย|นาง|น.ส.|นางสาว)?\s*([^\s]+ [^\s]+)/u', $title, $fromFullMatch)) {
                $fromName = trim(($fromFullMatch[3] ?? '').' '.$fromFullMatch[4]);
            }

            $messages['bank'] = $fromBank;
            $messages['acc'] = $fromAcc;

            preg_match('/(\d{2}\/\d{2}\/\d{2})@(\d{2}:\d{2})/', $title, $dateMatch);
            $date = $dateMatch[1];
            $time = $dateMatch[2];
            $dateObj = DateTime::createFromFormat('d/m/y H:i', $date.' '.$time);
            $datetime = $dateObj ? $dateObj->format('Y-m-d H:i:s') : null;

            $messages['current_date'] = $date;
            $messages['current_time'] = $time;
            $messages['date'] = $datenow;

            // ดึงยอดคงเหลือ
            preg_match('/เหลือ([\d,\.]+)บ/', $title, $remainMatch);
            $remains = str_replace(',', '', $remainMatch[1]);

            $remain = number_format((float) $remains, 2, '.', '');
            $messages['remain'] = $remain;

            file_put_contents($path, print_r($messages, true), FILE_APPEND);

            //            $date = str_replace("/", "-", $messages['current_date']);

            //            $time = Str::of($title)->explode('@');
            //            $datetime = $date.' '.$time;

            $hash = md5($data->code.$datetime.$amount.$remains.$messages['bank'].$messages['acc']);

            $checktime = strtotime(date('Y-m-d H:i:s'));
            $check = DB::connection('superrich')->table('bank_payment')->where('tx_hash', $hash)->first();
            if (! $check) {
                DB::connection('superrich')->table('bank_payment')->insert([
                    'bank' => strtolower('ttb_'.$mobile),
                    'bankstatus' => 1,
                    'bankname' => 'NOTI',
                    'checktime' => $checktime,
                    'date_create' => date('Y-m-d H:i:s'),
                    'time' => $checktime,
                    'channel' => 'SMS',
                    'value' => $amount,
                    'tx_hash' => $hash,
                    'detail' => $fromName.' '.$messages['bank'].' '.$messages['acc'],
                    'atranferer' => '',
                ]);

                DB::connection('superrich')->table('bankbalance')->where('bank', $data->bank.'_'.$data->accountno)->update(['updatetime' => $checktime, 'balance' => $remain]);
            }

        }

    }
}
