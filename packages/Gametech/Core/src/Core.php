<?php

namespace Gametech\Core;

use Carbon\Carbon;
use Exception;
use Gametech\Core\Repositories\ConfigRepository;
use Gametech\Core\Repositories\NoticeNewRepository;
use Gametech\Core\Repositories\NoticeRepository;
use Gametech\Game\Repositories\GameRepository;
use Gametech\Game\Repositories\GameTypeRepository;
use Gametech\Game\Repositories\GameUserRepository;
use Gametech\Member\Models\MemberSelectPro;
use Gametech\Member\Repositories\MemberRepository;
use Gametech\Payment\Repositories\BankPaymentRepository;
use Gametech\Payment\Repositories\BankRepository;
use Gametech\Promotion\Models\Promotion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Storage;
use NumberFormatter;

class Core
{
    protected $configRepository;

    protected $noticeRepository;

    protected $noticeNewRepository;

    protected $gameRepository;

    protected $gameUserRepository;

    protected $memberRepository;

    protected $bankPaymentRepository;

    protected $gameTypeRepository;

    protected $bankRepository;

    public function __construct(
        ConfigRepository $configRepository,
        GameRepository $gameRepository,
        NoticeRepository $noticeRepository,
        NoticeNewRepository $noticeNewRepository,
        GameUserRepository $gameUserRepository,
        MemberRepository $memberRepository,
        BankPaymentRepository $bankPaymentRepository,
        BankRepository $bankRepository,
        GameTypeRepository $gameTypeRepository
    ) {
        $this->configRepository = $configRepository;
        $this->noticeRepository = $noticeRepository;
        $this->noticeNewRepository = $noticeNewRepository;
        $this->gameRepository = $gameRepository;
        $this->gameUserRepository = $gameUserRepository;
        $this->memberRepository = $memberRepository;
        $this->bankPaymentRepository = $bankPaymentRepository;
        $this->bankRepository = $bankRepository;
        $this->gameTypeRepository = $gameTypeRepository;

    }

    public function getContact()
    {
        $config = core()->getConfigData();

        $data = app('Gametech\Core\Repositories\ContactChannelRepository')->orderBy('sort')->findWhere(['enable' => 'Y']);

        if ($data) {
            return $data;
        } else {
            return [];
        }

    }

    public function getNoticeNewData()
    {
        $notices = $this->noticeNewRepository->findWhere(['enable' => 'Y']);
        $notice = [];

        foreach ($notices as $item) {
            $route = trim($item['route'] ?? '');
            if ($route === '') continue;

            $notice[$route]['route'] = true;
            $notice[$route]['messages'][] = $item['message'];
        }

        return $notice;

    }

    public function getProfile()
    {
        $datenow = now();
        $today = $datenow->toDateString();
        $config = core()->getConfigData();

        if (Auth::guard('customer')->check()) {
            $data = Auth::guard('customer')->user()->load('bank');
            $user = $this->gameUserRepository->findOneByField('member_code', $data->code);
            $userfree = app('Gametech\Game\Repositories\GameUserFreeRepository')->findOneByField('member_code', $data->code);
            if ($user) {

                $withdraw_today = $this->memberRepository->sumWithdrawSeamless($data->code, $today)->withdraw_seamless_amount_sum;
                $withdraw = (is_null($withdraw_today) ? 0 : $withdraw_today);
                $today_wd = ($config->maxwithdraw_day - $withdraw);

                if ($user->amount_balance > 0) {
                    $pro = true;
                } else {
                    $pro = false;
                }

                if ($config->wallet_withdraw_all == 'Y') {
                    $pro = true;
                }
                $turnpro = $user->amount_balance;
                $limit = $user->withdraw_limit_amount;

                $data->pro = $pro;
                $data->turnpro = $turnpro;
                if ($today_wd > $user->balance) {
                    $data->withdraw = $user->balance;
                } else {
                    $data->withdraw = $today_wd;
                }

                $data->limit = $limit;
            } else {
                $data->pro = false;
                $data->turnpro = 0;

                $data->limit = 0;
            }
            if ($userfree) {
                $data->turnprofree = $userfree->amount_balance;
                $data->limitfree = $userfree->withdraw_limit_amount;
            } else {
                $data->turnprofree = 0;
                $data->limitfree = 0;
            }

            return $data;
        } else {
            return [];
        }

    }

    public function getConfigData()
    {
        return $this->configRepository->first();

    }

    public function getGameType()
    {
        $gametypes = $this->gameTypeRepository->orderBy('sort')->findWhere(['enable' => 'Y'])
            ->map(function ($g) {
                return [
                    'key' => strtolower($g->id),
                    'label' => strtolower($g->id),
                ];
            })->values()->toArray();

        return $gametypes;
    }

    public function getSelectPro()
    {
        $userId = 0;
        if (Auth::guard('customer')->check()) {
            $userId = Request::user('customer')->code;
        }
        $data = MemberSelectPro::where('member_code', $userId)->first();
        if ($data) {
            $promotion = Promotion::where('code', $data['pro_code'])->first()->toArray();

            return $promotion;
        } else {
            return [];
        }
    }

    public function getBankTopup()
    {
        $getbank = $this->bankRepository->getBankInAccountAll();

        if (count($getbank) === 0) {
            return [];
        }

        // แปลงข้อมูลเป็น array ก่อน
        $banksArray = collect($getbank)->toArray();

        // เตรียมข้อมูล bank + account หลัก
        $newbank = [];
        foreach ($banksArray as $i => $banks) {
            $newbank[$i] = $banks;
            foreach ($banks['banks_account'] as $item) {
                $newbank[$i]['sort']    = $item['sort'];
                $newbank[$i]['qrcode']  = $item['qrcode'];
                $newbank[$i]['filepic2'] = $item['filepic'] ?: 'noimage.png';
            }
        }

        // เรียงตาม sort
        $keys = array_column($newbank, 'sort');
        array_multisort($keys, SORT_ASC, $newbank);

        // กรองเฉพาะ account ที่ payment = 'Y' หรือ slip = 'Y'
        $bankss = collect($newbank)->map(function ($bank) {
            $bank['banks_account'] = collect($bank['banks_account'])
                ->filter(function ($acc) {
                    return $acc['payment'] !== 'Y' && $acc['slip'] !== 'Y';
                })

                ->values()
                ->toArray();

            return $bank;
        })->filter(function ($bank) {
            return count($bank['banks_account']) > 0;
        })->transform(function ($item) {
            $item['filepic']  = Storage::url('bank_img/'.$item['filepic']);
            $item['filepic2'] = Storage::url('bank_qr/'.$item['filepic2']);
            return $item;
        })->values();

        return $bankss;
    }


    public function getRefill()
    {
        $userId = 0;
        if (Auth::guard('customer')->check()) {
            $userId = Request::user('customer')->code;
            $getpro = Request::user('customer')->promotion;
            if ($getpro == 'N') {
                return '';
            } else {
                return $this->bankPaymentRepository->orderBy('code', 'desc')->findOneWhere(['pro_check' => 'N', 'status' => 1, 'enable' => 'Y', 'member_topup' => $userId]);

            }

        }

        return '';
    }

    public function getNoticeData()
    {
        $notice = [];
        $notices = $this->noticeRepository->findWhere(['enable' => 'Y']);
        foreach ($notices as $item) {
            $notice[$item['route']]['route'] = true;
            $notice[$item['route']]['msg'] = $item['message'];
        }

        return $notice;
    }

    public function getGameUser($id, $method = '')
    {
        $game = $this->getGame($method);
        $member = $this->memberRepository->find($id);

        $user = $this->gameUserRepository->getOneUser($member->code, $game->code, true);
        if ($user['success'] === true) {
            $member->balance = $user['data']['balance'];
            $member->save();

            return $user['data'];
        } else {
            return ['balance' => 0];
        }

    }

    public function getGame($method = '')
    {
        $game = null;
        if ($method == '') {
            $game = $this->gameRepository->findOneWhere(['enable' => 'Y', 'status_open' => 'Y']);

        } else {
            $game = $this->gameRepository->findOneWhere(['enable' => 'Y', 'status_open' => 'Y', 'id' => $method]);

        }

        if (isset($game)) {
            $game->image = Storage::url('game_img/'.$game->filepic);
        }

        //        dd($game);
        //        $game->image = Storage::url('game_img/' . $game->filepic);
        return $game;

    }

    public function getGameUserSeamless($id, $method = '')
    {
        $game = $this->getGame($method);
        $member = $this->memberRepository->find($id);

        $user = $this->gameUserRepository->getOneUser($member->code, $game->code, true);
        if ($user['success'] === true) {
            $member->balance = $user['data']['balance'];
            $member->save();

            return $user['data'];
        } else {
            return ['balance' => 0];
        }

    }

    /**
     * Format and convert price with currency symbol
     *
     * @param  int  $amount
     * @return string
     */
    public function currency($amount = 0, $decimal = 2)
    {
        if (is_null($amount)) {
            $amount = 0;
        }

        return number_format($amount, $decimal);

    }

    public function action_exists($action)
    {
        try {
            action($action);
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    public function textcolor($text, $color = 'text-success')
    {

        return "<span class='$color'>".$text.'</span>';

    }

    public function checkDisplay($text)
    {

        if ($text == 'Y') {
            return "<span class='text-success'><i class='fa fa-check'></i> Yes</span>";
        } else {
            return "<span class='text-muted'><i class='fa fa-times'></i> No</span>";
        }
    }

    public function imgurl($img, $path)
    {
        if (! $img) {
            return '';
        }

        return Storage::url($path.'/'.$img);

    }

    public function displayBtn($code, $method, $methodtxt)
    {
        return '<button type="button" class="btn '.($method == 'Y' ? 'btn-success' : 'btn-danger').' btn-xs icon-only" onclick="editdata('.$code.','."'".core()->flip($method)."'".','."'$methodtxt'".')">'.($method == 'Y' ? '<i class="fa fa-check"></i>' : '<i class="fa fa-times"></i>').'</button>';
    }

    public function flip($data)
    {
        return $data === 'Y' ? 'N' : 'Y';
    }

    public function displayBank($name, $pic)
    {
        return $this->showImgBank($pic, 'img', '20px', '20px').' '.$name;
    }

    public function showImg($img, $path, $width, $height, $class = 'rounded')
    {
        if (! $img) {
            return '';
        }
        if ($width != '' && $height != '') {
            return '<img src="'.Storage::url($path.'/'.$img).'" class="'.$class.'" style="width:'.$width.';height:'.$height.';">';
        } else {
            return '<img src="'.Storage::url($path.'/'.$img).'" class="'.$class.'">';
        }
    }

    public function showImgBank($img, $path, $width, $height, $class = 'rounded')
    {
        if (! $img) {
            return '';
        }
        if ($width != '' && $height != '') {
            return '<img src="https://office.superrich69.com/'.($path.'/'.$img).'" class="'.$class.'" style="width:'.$width.';height:'.$height.';">';
        } else {
            return '<img src="https://office.superrich69.com/'.($path.'/'.$img).'" class="'.$class.'">';
        }
    }

    /**
     * Format and convert price with currency symbol
     *
     *
     * @return string
     */
    public function formatPrice(float $price, $currency)
    {
        $region = config('app.locale').'_'.strtoupper(config('app.locale'));
        if (is_null($price)) {
            $price = 0;
        }

        $formatter = new NumberFormatter($region, NumberFormatter::CURRENCY);

        return $formatter->parseCurrency($price, $currency);

    }

    public function TypeDisplay($type, $transfer, $remark, $bank, $game, $promotion, $refer_table, $refer_code)
    {
        $remark = $remark.'<br> อ้างอิง table : '.$refer_table.' , code : '.$refer_code;
        $result = '';
        switch ($type) {
            case 'TOPUP':
                $result = "<span class='text-success' data-html='true' data-toggle='popover' data-placement='top' data-content='$remark'>ฝากเงิน ($bank)</span>";
                break;
            case 'WITHDRAW':
                $result = "<span class='text-danger' data-html='true' data-toggle='popover' data-placement='top' data-content='$remark'>แจ้งถอนเงิน ($bank)</span>";
                break;

            case 'CONFIRM_WD':
                $result = "<span class='text-pink' data-html='true' data-toggle='popover' data-placement='top' data-content='$remark'>อนุมัติการถอน ($bank)</span>";
                break;

            case 'AUTO_WDS':
                $result = "<span class='text-success' data-html='true' data-toggle='popover' data-placement='top' data-content='$remark'>ถอนออโต้ (<i class='fa fa-check'></i>)</span>";
                break;

            case 'AUTO_WDF':
                $result = "<span class='text-info' data-html='true' data-toggle='popover' data-placement='top' data-content='$remark'>ถอนออโต้ (<i class='fa fa-times'></i>)</span>";
                break;

            case 'TRANSFER':
                if ($transfer == 'W') {
                    $result = "<span class='text-info' data-html='true' data-toggle='popover' data-placement='top' data-content='$remark'>Wallet ไป $game</span>";

                } elseif ($transfer == 'D') {
                    $result = "<span class='text-orange' data-html='true' data-toggle='popover' data-placement='top' data-content='$remark'>$game มา Wallet</span>";
                }
                break;
            case 'SETWALLET':
                if ($transfer == 'D') {
                    $result = "<span class='text-indigo' data-html='true' data-toggle='popover' data-placement='top' data-content='$remark'>เพิ่ม โดยทีมงาน</span>";
                } elseif ($transfer == 'W') {
                    $result = "<span class='text-gray-dark' data-html='true' data-toggle='popover' data-placement='top' data-content='$remark'>ลด โดยทีมงาน</span>";
                }
                break;

            case 'SETPOINT':
                if ($transfer == 'D') {
                    $result = "<span class='text-indigo' data-html='true' data-toggle='popover' data-placement='top' data-content='$remark'>เพิ่ม Point</span>";
                } elseif ($transfer == 'W') {
                    $result = "<span class='text-gray-dark' data-html='true' data-toggle='popover' data-placement='top' data-content='$remark'>ลด Point</span>";
                }
                break;

            case 'SETCREDIT':
                if ($transfer == 'D') {
                    $result = "<span class='text-indigo' data-html='true' data-toggle='popover' data-placement='top' data-content='$remark'>เพิ่ม Free Credit</span>";
                } elseif ($transfer == 'W') {
                    $result = "<span class='text-gray-dark' data-html='true' data-toggle='popover' data-placement='top' data-content='$remark'>ลด Free Credit</span>";
                }
                break;

            case 'TRAN_USER':
                if ($transfer == 'D') {
                    $result = "<span class='text-lime' data-html='true' data-toggle='popover' data-placement='top' data-content='$remark'>รับโอน</span>";
                } elseif ($transfer == 'W') {
                    $result = "<span class='text-pink' data-html='true' data-toggle='popover' data-placement='top' data-content='$remark'>โอนเงิน</span>";
                }
                break;

            case 'ROLLBACK':
                //                $result = "<span class='text-success'>$remark</span>";
                $result = "<span class='success' data-html='true' data-toggle='popover' data-placement='top' data-content='$remark'>คืนยอดที่แจ้งถอน</span>";

                break;
            case 'SPIN':
                $result = "<span class='text-smoke' data-html='true' data-toggle='popover' data-placement='top' data-content='$remark'>วงล้อมหาสนุก</span>";
                break;

            case 'FASTSTART':
                $result = "<span class='text-info' data-html='true' data-toggle='popover' data-placement='top' data-content='$remark'>$promotion (มอบโดยระบบ)</span>";
                break;

            case 'FASTSTARTS':
                $result = "<span class='text-info' data-html='true' data-toggle='popover' data-placement='top' data-content='$remark'>$promotion (แจ้งเตือน)</span>";
                break;

            case 'POMPAY_S':
                $result = "<span class='success'>PomPay</span>";
                break;

            case 'OTHER':
                $result = "<span class='text-gray'>อื่นๆ</span>";
                break;

            case 'TRANBONUS':
                $result = "<span class='text-blue'>โยก โบนัส</span>";
                break;

            case 'TRANFT':
                $result = "<span class='text-blue'>โยก ค่าแนะนำ</span>";
                break;

            case 'TRANCB':
                $result = "<span class='text-blue'>โยก Cashback</span>";
                break;

            case 'TRANIC':
                $result = "<span class='text-blue'>โยก IC</span>";
                break;

            case 'PROMOTION':
                $result = "<span class='text-dark'>รับโปร</span>";
                break;

            case 'CASHBACK':
                $result = "<span class='text-dark'>Cashback</span>";
                break;

            case 'IC':
                $result = "<span class='text-dark'>IC</span>";
                break;
        }

        return $result;
    }

    /**
     * Check whether sql date is empty
     *
     *
     * @return bool
     */
    public function is_empty_date(string $date)
    {
        return preg_replace('#[ 0:-]#', '', $date) === '';
    }

    /**
     * Format date using current channel.
     *
     * @param  \Illuminate\Support\Carbon|null  $date
     * @param  string  $format
     */
    public function formatDate($value, $format = 'Y-m-d H:i:s')
    {
        if (is_numeric($value)) {
            return date($format, (int)$value);
        }
        if ($value instanceof \Carbon\Carbon) {
            return $value->format($format);
        }
        if (is_string($value)) {
            return date($format, strtotime($value));
        }
        return '';
    }

    public function DateDiff($start)
    {
        $datenow = now()->toDateTimeString();
        $date = new Carbon($start, config('app.timezone'));

        return $date->floatDiffInHours($datenow, false);
    }

    public function DateDiffMin($start)
    {
        $datenow = now()->toDateTimeString();
        $date = new Carbon($start, config('app.timezone'));

        return $date->floatDiffInMinutes($datenow, false);
    }

    public function Date($date = null, $format = 'd-m-Y'): string
    {
        $timezone = config('app.timezone');
        $locale = config('app.locale');

        if (is_null($date)) {
            $date = Carbon::now();
        }
        $date = Carbon::parse($date, $timezone);

        //        $date = Carbon::parse($date);
        //        dd($date);
        //        $date = Carbon::createFromFormat('Y-m-d',$date,$timezone);
        //        $date->setTimezone($timezone);

        return $date->format($format);
    }

    public function flip2($data)
    {
        return $data === true ? false : true;
    }

    public function flipnum($data)
    {
        return $data === 1 ? 0 : 1;
    }

    /**
     * Returns time intervals
     *
     *
     * @return array
     */
    public function getTimeInterval(\Illuminate\Support\Carbon $startDate, \Illuminate\Support\Carbon $endDate)
    {
        $timeIntervals = [];

        $totalDays = $startDate->diffInDays($endDate) + 1;
        $totalMonths = $startDate->diffInMonths($endDate) + 1;

        $startWeekDay = Carbon::createFromTimeString($this->xWeekRange($startDate, 0).' 00:00:01');
        $endWeekDay = Carbon::createFromTimeString($this->xWeekRange($endDate, 1).' 23:59:59');
        $totalWeeks = $startWeekDay->diffInWeeks($endWeekDay);

        if ($totalMonths > 5) {
            for ($i = 0; $i < $totalMonths; $i++) {
                $date = clone $startDate;
                $date->addMonths($i);

                $start = Carbon::createFromTimeString($date->format('Y-m-d').' 00:00:01');
                $end = $totalMonths - 1 == $i
                    ? $endDate
                    : Carbon::createFromTimeString($date->format('Y-m-d').' 23:59:59');

                $timeIntervals[] = ['start' => $start, 'end' => $end, 'formatedDate' => $date->format('M')];
            }
        } elseif ($totalWeeks > 6) {
            for ($i = 0; $i < $totalWeeks; $i++) {
                $date = clone $startDate;
                $date->addWeeks($i);

                $start = $i == 0
                    ? $startDate
                    : Carbon::createFromTimeString($this->xWeekRange($date, 0).' 00:00:01');
                $end = $totalWeeks - 1 == $i
                    ? $endDate
                    : Carbon::createFromTimeString($this->xWeekRange($date, 1).' 23:59:59');

                $timeIntervals[] = ['start' => $start, 'end' => $end, 'formatedDate' => $date->format('d M')];
            }
        } else {
            for ($i = 0; $i < $totalDays; $i++) {
                $date = clone $startDate;
                $date->addDays($i);

                $start = Carbon::createFromTimeString($date->format('Y-m-d').' 00:00:01');
                $end = Carbon::createFromTimeString($date->format('Y-m-d').' 23:59:59');

                $timeIntervals[] = ['start' => $start, 'end' => $end, 'formatedDate' => $date->format('d M')];
            }
        }

        return $timeIntervals;
    }

    /**
     * @return string
     */
    public function xWeekRange(string $date, int $day)
    {
        $ts = strtotime($date);

        if (! $day) {
            $start = (date('D', $ts) == 'Sun') ? $ts : strtotime('last sunday', $ts);

            return date('Y-m-d', $start);
        } else {
            $end = (date('D', $ts) == 'Sat') ? $ts : strtotime('next saturday', $ts);

            return date('Y-m-d', $end);
        }
    }

    /**
     * Method to sort through the acl items and put them in order
     *
     *
     * @return array
     */
    public function sortItems(array $items)
    {
        foreach ($items as &$item) {
            if (count($item['children'])) {
                $item['children'] = $this->sortItems($item['children']);
            }
        }

        usort($items, function ($a, $b) {
            if ($a['sort'] == $b['sort']) {
                return 0;
            }

            return ($a['sort'] < $b['sort']) ? -1 : 1;
        });

        return $this->convertToAssociativeArray($items);
    }

    /**
     * @return array
     */
    public function convertToAssociativeArray(array $items)
    {
        foreach ($items as $key1 => $level1) {
            unset($items[$key1]);
            $items[$level1['key']] = $level1;

            if (count($level1['children'])) {
                foreach ($level1['children'] as $key2 => $level2) {
                    $temp2 = explode('.', $level2['key']);
                    $finalKey2 = end($temp2);
                    unset($items[$level1['key']]['children'][$key2]);
                    $items[$level1['key']]['children'][$finalKey2] = $level2;

                    if (count($level2['children'])) {
                        foreach ($level2['children'] as $key3 => $level3) {
                            $temp3 = explode('.', $level3['key']);
                            $finalKey3 = end($temp3);
                            unset($items[$level1['key']]['children'][$finalKey2]['children'][$key3]);
                            $items[$level1['key']]['children'][$finalKey2]['children'][$finalKey3] = $level3;
                        }
                    }

                }
            }
        }

        return $items;
    }

    /**
     * @param  string|int|float  $value
     * @return array|float|int|string
     */
    public function array_set(&$array, string $key, $value)
    {
        if (is_null($key)) {
            return $array = $value;
        }

        $keys = explode('.', $key);
        count($keys);

        while (count($keys) > 1) {
            $key = array_shift($keys);

            if (! isset($array[$key]) || ! is_array($array[$key])) {
                $array[$key] = [];
            }

            $array = &$array[$key];
        }

        $finalKey = array_shift($keys);

        if (isset($array[$finalKey])) {
            $array2 = (array) $value;
            $array[$finalKey] = $this->arrayMerge($array[$finalKey], $array2);
        } else {
            $array[$finalKey] = $value;
        }

        return $array;
    }

    /**
     * @return array
     */
    protected function arrayMerge(array $array1, array &$array2)
    {
        $merged = $array1;

        foreach ($array2 as $key => &$value) {
            if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
                $merged[$key] = $this->arrayMerge($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    /**
     * @return array
     */
    public function convertEmptyStringsToNull($array)
    {
        foreach ($array as $key => $value) {
            if ($value == '' || $value == 'null') {
                $array[$key] = null;
            }
        }

        return $array;
    }

    /**
     * Create singletom object through single facade
     *
     *
     * @return object
     */
    public function getSingletonInstance(string $className)
    {
        static $instance = [];

        if (array_key_exists($className, $instance)) {
            return $instance[$className];
        }

        return $instance[$className] = app($className);
    }

    public function generateDateRange($start_date, $end_date)
    {
        $diff = (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24);
        $day = [];
        for ($i = 0; $i <= $diff; $i++) {
            $daycheck = date('Y-m-d', strtotime($start_date.' + '.$i.' days'));
            $day[] = $daycheck;
        }

        return $day;
    }
}
