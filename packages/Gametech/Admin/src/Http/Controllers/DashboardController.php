<?php

namespace Gametech\Admin\Http\Controllers;

use App\Libraries\KbankOut;
use App\Libraries\ScbOut;
use Carbon\Carbon;
use Gametech\Member\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class DashboardController extends AppBaseController
{
    protected ?array $_config = null;

    /** @var \Illuminate\Support\Carbon */
    protected Carbon $startDate;

    /** @var \Illuminate\Support\Carbon */
    protected Carbon $lastStartDate;

    /** @var \Illuminate\Support\Carbon */
    protected Carbon $endDate;

    /** @var \Illuminate\Support\Carbon */
    protected Carbon $lastEndDate;

    public function __construct()
    {
        // ควรหุ้ม route ด้วย 'web' จากไฟล์ routes อยู่แล้ว
        // ตรง Controller เราการันตีว่าเป็น admin ที่ผ่าน auth แล้ว
        $this->middleware(['auth:admin']);

        $this->_config = request('_config');
    }

    public function index(): View
    {
        $this->setStartEndDate();

//        dd('here');

        $view = $this->_config['view'] ?? 'admin.dashboard.index';

        return view($view, [
            'startDate' => $this->startDate,
            'endDate'   => $this->endDate,
        ]);
    }

    /** กำหนดช่วงวันแบบปลอดภัยและเบา DB */
    public function setStartEndDate(): void
    {
        $reqStart = request()->get('start');
        $reqEnd   = request()->get('end');

        $start = $reqStart ? Carbon::parse($reqStart)->startOfDay() : now()->subDays(30)->startOfDay();
        $end   = $reqEnd   ? Carbon::parse($reqEnd)->endOfDay()   : now();

        if ($end->greaterThan(now())) {
            $end = now();
        }

        $this->startDate = $start->clone();
        $this->endDate   = $end->clone();

        // previous-period: ย้อนหลังช่วงเท่ากันจาก start
        $this->lastStartDate = $this->startDate->clone()->subDays($this->startDate->diffInDays($this->endDate));
        $this->lastEndDate   = $this->startDate->clone(); // ตามโค้ดเดิมของคุณ
    }

    /** ตัวเลขหน้าแรก */
    public function loadCnt(): JsonResponse
    {
        $todayStart = now()->startOfDay();
        $todayEnd   = now()->endOfDay();
        $todayDate  = now()->toDateString();

        $config = core()->getConfigData();

        // จำนวนเคสฝาก (วันนี้ / ทั้งหมด)
        $bank_in_today = app('Gametech\Payment\Repositories\BankPaymentRepository')
            ->income()->active()->waiting()
            ->whereBetween('date_create', [$todayStart, $todayEnd])
            ->count();

        $bank_in = app('Gametech\Payment\Repositories\BankPaymentRepository')
            ->income()->active()->waiting()
            ->count();

        // ถอนรอภายในวันนี้
        $bank_out = app('Gametech\Payment\Repositories\BankPaymentRepository')
            ->profit()->active()->waiting()
            ->whereBetween('date_create', [$todayStart, $todayEnd])
            ->count();

        // withdraw คง logic เดิม: seamless vs non-seamless
//        if ($config->seamless === 'Y') {
//            $withdraw = app('Gametech\Payment\Repositories\WithdrawSeamlessRepository')->active()->waiting()->count();
//            $withdraw_free = app('Gametech\Payment\Repositories\WithdrawSeamlessFreeRepository')->active()->waiting()->count();
//        } else {
            $withdraw = app('Gametech\Payment\Repositories\WithdrawRepository')->where('transection_type',1)->active()->waiting()->count();
//            $withdraw_free = app('Gametech\Payment\Repositories\WithdrawFreeRepository')->active()->waiting()->count();
//        }

//        $payment_waiting = app('Gametech\Payment\Repositories\PaymentWaitingRepository')
//            ->whereDate('date_create', '>', '2021-04-05')
//            ->active()->waiting()->count();

//        $member_confirm = app('Gametech\Member\Repositories\MemberRepository')
//            ->active()->waiting()->count();

        // --- Announce (มี timeout + retry + fallback) ---
        $announceContent = '';
        $announceUpdatedAt = now()->toDateTimeString();

        try {
            $response = Http::timeout(10)->retry(2, 200)->get('https://api.168csn.com/api/announce');
            if ($response->successful()) {
                $json = $response->json();
                if (isset($json['data'])) {
                    $announceContent   = $json['data']['content']     ?? '';
                    $announceUpdatedAt = $json['data']['updated_at']  ?? $announceUpdatedAt;
                }
            }
        } catch (\Throwable $e) {
            // เงียบไว้ ให้มีค่า default
        }

        $announce_new = 'N';
        if ($announceContent !== '') {
            $startKey = $this->id().'announce_start';
            $stopKey  = $this->id().'announce_stop';

            if (!Cache::has($startKey)) {
                Cache::add($stopKey, $announceUpdatedAt);
            }
            if (!Cache::has($stopKey)) {
                Cache::add($stopKey, $announceUpdatedAt);
            } else {
                Cache::put($stopKey, $announceUpdatedAt);
            }

            $start = Cache::get($startKey);
            $stop  = Cache::get($stopKey);
            if ($start !== $stop) {
                $announce_new = 'Y';
                Cache::put($startKey, $stop);
            }
        }

        $result = [

            'withdraw'    => $withdraw,
            'bank_in_today'    => $bank_in_today,
            'bank_in'          => $bank_in,
            'bank_out'         => $bank_out,
            'announce'         => $announceContent,
            'announce_new'     => $announce_new,
        ];

        return $this->sendResponseNew($result, 'Complete');
    }

    /** สรุปตัวเลขรายวันตาม method */
    public function loadSum(Request $request): JsonResponse
    {
        $config    = core()->getConfigData();
        $today     = now()->toDateString();
        $method    = $request->string('method')->toString();
        $data      = 0;

        switch ($method) {
            case 'setdeposit':
                $data = app('Gametech\Member\Repositories\MemberCreditLogRepository')
                    ->active()->where('kind', 'SETWALLET')->where('credit_type', 'D')
                    ->whereDate('date_create', $today)->sum('amount');
                $data = core()->currency($data);
                break;

            case 'setwithdraw':
                $data = app('Gametech\Member\Repositories\MemberCreditLogRepository')
                    ->active()->where('kind', 'SETWALLET')->where('credit_type', 'W')
                    ->whereDate('date_create', $today)->sum('amount');
                $data = core()->currency($data);
                break;

            case 'deposit':
                $data = app('Gametech\Payment\Repositories\BankPaymentRepository')
                    ->income()->active()->whereIn('status', [0, 1])
                    ->whereDate('date_create', $today)->sum('value');
                $data = core()->currency($data);
                break;

            case 'deposit_wait':
                $data = app('Gametech\Payment\Repositories\BankPaymentRepository')
                    ->income()->active()->waiting()->where('autocheck', 'Y')
                    ->whereDate('date_create', $today)->sum('value');
                $data = core()->currency($data);
                break;

            case 'withdraw':
                if ($config->seamless === 'Y') {
                    $data1 = app('Gametech\Payment\Repositories\WithdrawSeamlessRepository')
                        ->active()->complete()
                        ->whereDate(DB::raw("DATE_FORMAT(date_approve,'%Y-%m-%d')"), $today)->sum('amount');
                } else {
                    $data1 = app('Gametech\Payment\Repositories\WithdrawRepository')
                        ->active()->complete()
                        ->whereDate(DB::raw("DATE_FORMAT(date_approve,'%Y-%m-%d')"), $today)->sum('amount');
                }
                $data = core()->currency($data1);
                break;

            case 'bonus':
                $data1 = app('Gametech\Payment\Repositories\PaymentPromotionRepository')
                    ->active()->aff()
                    ->whereDate('date_create', $today)->sum('credit_bonus');

                $data2 = app('Gametech\Payment\Repositories\BillRepository')
                    ->active()->getpro()->where('transfer_type', 1)
                    ->whereDate('date_create', $today)->sum('credit_bonus');

                $data = core()->currency($data1 + $data2);
                break;

            case 'balance':
                $in = app('Gametech\Payment\Repositories\BankPaymentRepository')
                    ->income()->active()->whereIn('status', [0, 1])
                    ->whereDate('date_create', $today)->sum('value');

                if ($config->seamless === 'Y') {
                    $out = app('Gametech\Payment\Repositories\WithdrawSeamlessRepository')
                        ->active()->complete()
                        ->whereDate(DB::raw("DATE_FORMAT(date_approve,'%Y-%m-%d')"), $today)->sum('amount');
                } else {
                    $out = app('Gametech\Payment\Repositories\WithdrawRepository')
                        ->active()->complete()
                        ->whereDate(DB::raw("DATE_FORMAT(date_approve,'%Y-%m-%d')"), $today)->sum('amount');
                }
                $data = core()->currency($in - $out);
                break;

            case 'register-today':
                $data = app('Gametech\Member\Repositories\MemberRepository')
                    ->active()
                    ->whereDate('date_regis', $today)->count();
                break;

            case 'register-deposit':
                $data = app('Gametech\Member\Repositories\MemberRepository')
                    ->whereDate('date_regis', $today)
                    ->whereHas('payment', function ($q) {
                        $q->where('status', 1)->where('enable', 'Y')->whereDate('date_approve', now()->toDateString());
                    })
                    ->count();
                break;

            case 'register-all-deposit':
                $data = app('Gametech\Member\Repositories\MemberRepository')
                    ->whereDate('date_regis', '!=', $today)
                    ->whereHas('payment', function ($q) {
                        $q->where('status', 1)->where('enable', 'Y')->whereDate('date_approve', now()->toDateString());
                    })
                    ->count();
                break;

            case 'register-not-deposit':
                $data = app('Gametech\Member\Repositories\MemberRepository')
                    ->whereDate('date_regis', $today)
                    ->whereDoesntHave('payment', function ($q) {
                        $q->where('status', 1)->where('enable', 'Y')->whereDate('date_approve', now()->toDateString());
                    })
                    ->count();
                break;

            case 'user_online':
                // เดิม dd($data) ทำหน้าเด้ง → เปลี่ยนเป็นส่งค่าออก
                $data = (new Member)->allOnline();
                break;
        }

        return $this->sendResponseNew(['sum' => $data], 'Complete');
    }

    /** สรุปกราฟช่วง 7 วัน */
    public function loadSumAll(Request $request): JsonResponse
    {
        $config    = core()->getConfigData();
        $startdate = now()->subDays(6)->toDateString();
        $enddate   = now()->toDateString();

        $date_arr = core()->generateDateRange($startdate, $enddate);
        $method   = $request->string('method')->toString();

        $result = [
            'label'         => [],
            'line_deposit'  => [],
            'line_withdraw' => [],
            'line_bonus'    => [],
            'line_balance'  => [],
            'bar'           => [],
        ];

        switch ($method) {
            case 'income':
                $data = app('Gametech\Payment\Repositories\BankPaymentRepository')
                    ->income()->active()->complete()
                    ->whereBetween(DB::raw("DATE_FORMAT(date_create,'%Y-%m-%d')"), [$startdate, $enddate])
                    ->groupBy(DB::raw('DATE(bank_payment.date_create)'))
                    ->select(
                        DB::raw('SUM(value) as value'),
                        DB::raw("DATE_FORMAT(date_create,'%Y-%m-%d') as date")
                    )->get();

                $datas = collect($data->toArray())->mapToGroups(fn ($it) => [$it['date'] => $it['value']])->toArray();

                if ($config->seamless === 'Y') {
                    $data2 = app('Gametech\Payment\Repositories\WithdrawSeamlessRepository')
                        ->active()->complete()
                        ->whereBetween(DB::raw("DATE_FORMAT(date_approve,'%Y-%m-%d')"), [$startdate, $enddate])
                        ->groupBy(DB::raw('DATE(withdraws_seamless.date_approve)'))
                        ->select(DB::raw('SUM(amount) as value'), DB::raw("DATE_FORMAT(date_approve,'%Y-%m-%d') as date"))->get();
                } else {
                    $data2 = app('Gametech\Payment\Repositories\WithdrawRepository')
                        ->active()->complete()
                        ->whereBetween(DB::raw("DATE_FORMAT(date_approve,'%Y-%m-%d')"), [$startdate, $enddate])
                        ->groupBy(DB::raw('DATE(withdraws.date_approve)'))
                        ->select(DB::raw('SUM(amount) as value'), DB::raw("DATE_FORMAT(date_approve,'%Y-%m-%d') as date"))->get();
                }
                $datas2 = collect($data2->toArray())->mapToGroups(fn ($it) => [$it['date'] => $it['value']])->toArray();

                $data3 = app('Gametech\Payment\Repositories\PaymentPromotionRepository')
                    ->active()->aff()
                    ->whereBetween(DB::raw("DATE_FORMAT(date_create,'%Y-%m-%d')"), [$startdate, $enddate])
                    ->groupBy(DB::raw('DATE(payments_promotion.date_create)'))
                    ->select(DB::raw('SUM(credit_bonus) as value'), DB::raw("DATE_FORMAT(date_create,'%Y-%m-%d') as date"))->get();
                $datas3 = collect($data3->toArray())->mapToGroups(fn ($it) => [$it['date'] => $it['value']])->toArray();

                $data4 = app('Gametech\Payment\Repositories\BillRepository')
                    ->active()->getpro()->where('transfer_type', 1)
                    ->whereBetween(DB::raw("DATE_FORMAT(date_create,'%Y-%m-%d')"), [$startdate, $enddate])
                    ->groupBy(DB::raw('DATE(bills.date_create)'))
                    ->select(DB::raw('SUM(credit_bonus) as value'), DB::raw("DATE_FORMAT(date_create,'%Y-%m-%d') as date"))->get();
                $datas4 = collect($data4->toArray())->mapToGroups(fn ($it) => [$it['date'] => $it['value']])->toArray();

                foreach ($date_arr as $dt) {
                    $a = (int) (($datas[$dt][0]  ?? 0));
                    $b = (int) (($datas2[$dt][0] ?? 0));
                    $c = (int) (($datas3[$dt][0] ?? 0));
                    $d = (int) (($datas4[$dt][0] ?? 0));
                    $balance = ($a - $b);

                    $result['label'][]        = core()->Date($dt, 'd M');
                    $result['line_deposit'][] = $a;
                    $result['line_withdraw'][]= $b;
                    $result['line_bonus'][]   = ($c + $d);
                    $result['line_balance'][] = $balance;
                }
                break;

            case 'topup':
                $data = app('Gametech\Payment\Repositories\BankPaymentRepository')
                    ->income()->active()
                    ->whereIn('status', [0, 1])
                    ->whereBetween(DB::raw("DATE_FORMAT(date_create,'%Y-%m-%d')"), [$startdate, $enddate])
                    ->groupBy(DB::raw('DATE(bank_payment.date_create)'))
                    ->select(DB::raw('SUM(value) as value'), DB::raw("DATE_FORMAT(date_create,'%Y-%m-%d') as date"))->get();

                $datas = collect($data->toArray())->mapToGroups(fn ($it) => [$it['date'] => $it['value']])->toArray();

                foreach ($date_arr as $dt) {
                    $a = (int) (($datas[$dt][0] ?? 0));
                    $result['label'][] = core()->Date($dt, 'd M');
                    $result['bar'][]   = $a;
                }
                break;

            case 'register':
                $data = app('Gametech\Member\Repositories\MemberRepository')
                    ->active()
                    ->whereBetween('date_regis', [$startdate, $enddate])
                    ->groupBy('members.date_regis')
                    ->select(DB::raw('COUNT(*) as value'), DB::raw('date_regis as date'))
                    ->get();

                $datas = collect($data->toArray())->mapToGroups(fn ($it) => [$it['date'] => $it['value']])->toArray();

                foreach ($date_arr as $dt) {
                    $a = (int) (($datas[$dt][0] ?? 0));
                    $result['label'][] = core()->Date($dt, 'd M');
                    $result['bar'][]   = $a;
                }
                break;
        }

        return $this->sendResponseNew($result, 'Complete');
    }

    public function loadBank(Request $request): JsonResponse
    {
        $method = $request->string('method')->toString();
        $list = [];

        switch ($method) {
            case 'bankin':
                $responses = collect(app('Gametech\Payment\Repositories\BankAccountRepository')->getAccountInAll()->toArray());
                $list = $responses->map(function ($items) {
                    $btn = '';
                    $login = 'Y';
                    if ($items['bank']['shortcode'] === 'KBANK' && $items['local'] === 'Y') {
                        $btn = core()->displayBtn($items['code'], $login, 'login');
                    }
                    if ($items['bank']['shortcode'] === 'SCB' && $items['status_auto'] === 'Y') {
                        $btn = core()->displayBtn($items['code'], $login, 'refresh');
                    }
                    return [
                        'date_update' => core()->formatDate($items['checktime'], 'd/m/y H:i:s'),
                        'bank'        => core()->displayBank($items['bank']['shortcode'], $items['bank']['filepic']),
                        'acc_name'    => $items['acc_name'],
                        'acc_no'      => $items['acc_no'],
                        'balance'     => core()->currency($items['balance']),
                        'status'      => $items['api_refresh'],
                        'login'       => $btn,
                    ];
                })->all();
                break;

            case 'bankout':
                $responses = collect(app('Gametech\Payment\Repositories\BankAccountRepository')->getAccountOutAll()->toArray());
                $list = $responses->map(function ($items) {
                    $btn = '';
                    $login = 'Y';
                    if ($items['bank']['shortcode'] === 'SCB' && $items['status_auto'] === 'Y') {
                        $btn = core()->displayBtn($items['code'], $login, 'refresh');
                    }
                    return [
                        'date_update' => core()->formatDate($items['checktime'], 'd/m/y H:i:s'),
                        'bank'        => core()->displayBank($items['bank']['shortcode'], $items['bank']['filepic']),
                        'acc_name'    => $items['acc_name'],
                        'acc_no'      => $items['acc_no'],
                        'balance'     => core()->currency($items['balance']),
                        'login'       => $btn,
                    ];
                })->all();
                break;
        }

        return $this->sendResponseNew(['list' => $list], 'complete');
    }

    public function loadLogin(Request $request): JsonResponse
    {
        $method = $request->string('method')->toString();
        $list = [];

        switch ($method) {
            case 'login':
                $responses = app('Gametech\Member\Repositories\MemberLogRepository')
                    ->where('mode', 'LOGIN')->orderBy('code', 'desc')->take(10)->get();

                $list = collect($responses)->map(function ($items) {
                    return [
                        'user_name'   => ($items->admin ? $items->admin->user_name : ''),
                        'date_update' => $items->date_update->format('Y-m-d H:i:s'),
                        'ip'          => $items->ip,
                    ];
                })->all();
                break;

            case 'logout':
                $responses = app('Gametech\Member\Repositories\MemberLogRepository')
                    ->where('mode', 'LOGOUT')->orderBy('code', 'desc')->take(10)->get();

                $list = collect($responses)->map(function ($items) {
                    return [
                        'user_name'   => ($items->admin ? $items->admin->user_name : ''),
                        'date_update' => $items->date_update->format('Y-m-d H:i:s'),
                        'ip'          => $items->ip,
                    ];
                })->all();
                break;
        }

        return $this->sendResponseNew(['list' => $list], 'complete');
    }

    /** ประกาศ (เวอร์ชันย่อ ใช้ timeout+fallback) */
    public function getAnnounce(): array
    {
        $announceContent   = '';
        $announceUpdatedAt = now()->toDateTimeString();
        $announce_new      = 'N';

        try {
            $response = Http::timeout(10)->retry(2, 200)->get('https://announce.168csn.com/api/announce');
            if ($response->successful()) {
                $json = $response->json();
                if (isset($json['data'])) {
                    $announceContent   = $json['data']['content']    ?? '';
                    $announceUpdatedAt = $json['data']['updated_at'] ?? $announceUpdatedAt;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $startKey = $this->id().'announce_start';
        $stopKey  = $this->id().'announce_stop';

        if (!Cache::has($startKey)) {
            Cache::add($stopKey, $announceUpdatedAt);
        }
        if (!Cache::has($stopKey)) {
            Cache::add($stopKey, $announceUpdatedAt);
        } else {
            Cache::put($stopKey, $announceUpdatedAt);
        }

        $start = Cache::get($startKey);
        $stop  = Cache::get($stopKey);
        if ($start !== $stop) {
            $announce_new = 'Y';
            Cache::put($startKey, $stop);
        }

        return [
            'content' => $announceContent,
            'new'     => $announce_new,
        ];
    }

    /** ปุ่ม action: login/refresh ธนาคาร */
    public function edit(Request $request): JsonResponse
    {
        $id     = $request->input('id');
        $method = $request->input('method');

        if ($method === 'login') {
            $account = app('Gametech\Payment\Repositories\BankAccountRepository')->getAccountInOne($id);

            if ($account->bank->shortcode === 'SCB') {
                $dir = storage_path('cookies');
                @unlink($dir.'/cookies-'.$account->user_name.'.txt');
                @unlink($dir.'/data-'.$account->user_name.'.json');
            } elseif ($account->bank->shortcode === 'KBANK') {
                $accname = str_replace('-', '', $account->acc_no);
                $dir = storage_path('cookies');
                @unlink($dir.'/.kbizcookie'.$accname);
                @unlink($dir.'/.kbizpara'.$accname);
                @unlink($dir.'/.kbizownid'.$accname);
                @unlink($dir.'/.kbizdatarsso'.$accname);
            }

            return $this->sendSuccess('ดำเนินการเสร็จสิ้น');
        }

        if ($method === 'refresh') {
            $bank = app('Gametech\Payment\Repositories\BankAccountRepository')->getAccountInOutOne($id);
            $bank_code = $bank->bank->code;

            if ($bank_code == 2) { // KBANK?
                $bbl = new KbankOut;
                $chk = $bbl->BankCurl($bank['acc_no'], 'getbalance', 'POST');
                if (($chk['status'] ?? false) === true) {
                    $balance = (float) str_replace(',', '', $chk['data']['availableBalance']);
                    if ($balance >= 0) {
                        $bank->balance  = $balance;
                    }
                    $bank->checktime = now()->toDayDateTimeString();
                    $bank->save();
                }
            } elseif ($bank_code == 4) { // SCB?
                $bbl = new ScbOut;
                $chk = $bbl->BankCurl($bank['acc_no'], 'getbalance', 'POST');
                if (($chk['status'] ?? false) === true) {
                    $balance = (float) str_replace(',', '', $chk['data']['availableBalance']);
                    if ($balance >= 0) {
                        $bank->balance  = $balance;
                    }
                    $bank->checktime = now()->toDayDateTimeString();
                    $bank->save();

                    return $this->sendSuccess('ยอดปัจจุบันคือ '.$balance.' บาท');
                }
                return $this->sendSuccess($chk['msg'] ?? 'ไม่สามารถอัปเดตยอดได้');
            }

            return $this->sendSuccess('ดำเนินการเสร็จสิ้น');
        }

        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');
    }
}
