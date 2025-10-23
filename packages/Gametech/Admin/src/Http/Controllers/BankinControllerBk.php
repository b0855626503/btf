<?php

namespace Gametech\Admin\Http\Controllers;


use Gametech\Admin\DataTables\BankinDataTable;
use Gametech\Admin\Models\AdminProxy;
use Gametech\Core\Models\WebsiteProxy;
use Gametech\Member\Models\MemberWebProxy;
use Gametech\Member\Repositories\MemberRepository;
use Gametech\Payment\Models\BankPaymentProxy;
use Gametech\Payment\Repositories\BankPaymentRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PragmaRX\Google2FA\Google2FA;


class BankinControllerBk extends AppBaseController
{
    protected $_config;

    protected $repository;

    protected $memberRepository;

    public function __construct
    (
        BankPaymentRepository $repository,

        MemberRepository      $memberRepository
    )
    {
        $this->_config = request('_config');

        $this->middleware('admin');

        $this->repository = $repository;

        $this->memberRepository = $memberRepository;
    }


    public function index(BankinDataTable $bankinDataTable)
    {
        return $bankinDataTable->render($this->_config['view']);
    }

    public function clear(Request $request)
    {
        $user = $this->user()->name . ' ' . $this->user()->surname;
        $id = $request->input('id');
        $remark = $request->input('remark');

        $chk = $this->repository->find($id);

        if (!$chk) {
            return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        }


        $data['ip_admin'] = $request->ip();
        $data['remark_admin'] = $remark;
        $data['status'] = 2;
        $data['emp_topup'] = $this->user()->code;
        $data['user_update'] = $user;
        $data['date_approve'] = now()->toDateTimeString();
        $this->repository->update($data, $id);

        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');
    }

    public function destroy(Request $request)
    {
        $user = $this->user()->name . ' ' . $this->user()->surname;
        $id = $request->input('id');

        $chk = $this->repository->find($id);

        if (!$chk) {
            return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        }


        $data['enable'] = 'N';
        $data['user_update'] = $user;
        $this->repository->update($data, $id);

        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');
    }

    public function loadData(Request $request)
    {
        $id = $request->input('id');

        $data = $this->repository->find($id)?->only(['value', 'bank', 'tranferer']) + [
                'time' => optional($this->repository->find($id)?->time)->format('d/m/y H:i:s'),
            ];
//        $data = $this->memberRepository->findOneWhere(['user_name' => $id , 'enable' => 'Y']);
        if (empty($data)) {
            return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        }

        return $this->sendResponse($data, 'Complete');

    }

    public function loadUser(Request $request)
    {
        $id = $request->input('id');

        $data = MemberWebProxy::where('user', $id)->with('me')->first();
//        $data = $this->memberRepository->findOneWhere(['user_name' => $id , 'enable' => 'Y']);
        if (empty($data)) {
            return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        }

        return $this->sendResponse($data, 'พบข้อมูลสมาชิก');


    }

    public function update($id, Request $request)
    {
        $ip = $request->ip();
        $admin = $this->user();
        $user = $admin->name . ' ' . $admin->surname;

        $data = json_decode($request['data'], true);

        if (!$data['tranferer']) {
            return $this->sendError('ไม่พบข้อมูลสมาชิก', 200);
        }

        $chk = $this->repository->find($id);
        if (!$chk) {
            return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        }


        $data['check_user'] = $admin->user_name;
        $data['checking'] = 'Y';
        $data['checkstatus'] = 'Y';
        $data['msg'] = 'success';
        $this->repository->update($data, $id);

        return $this->sendSuccess('บันทึกข้อมูลไอดีลูกค้า เรียบร้อยแล้ว');

    }

    public function approve_(Request $request)
    {
        $ip = $request->ip();
        $admin = $this->user();
        $user = $admin->name . ' ' . $admin->surname;
        $id = $request->input('id');

        $chk = $this->repository->find($id);
        if (!$chk) {
            return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        }

        if ($chk->topupstatus == 'Y' || $chk->status === 1) {
            return $this->sendError('รายการนี้ ได้เติมเข้าไอดี แล้ว');
        }

        $member = MemberWebProxy::where('user', $chk->tranferer)->first();
        if (!$member) {
            return $this->sendError('ไม่พบข้อมูลสมาชิก', 200);
        }

        $website = WebsiteProxy::where('code', $member->web_code)->first();
        if (!$website) {
            return $this->sendError('ไม่พบข้อมูล Agent', 200);
        }

        $group_bot = $website->group_bot ?? '';
        $webbefore = $website->balance;
        $webafter = $website->balance - $chk->value;

        switch ($group_bot) {

            case '1':
                $amount = $chk->value;
                $userid = $chk->tranferer;
                $aguser = $chk->tranferer;

                $auth = $this->VegusAUTH($aguser);
                if (!$auth) {
                    $auth = $this->VegusAUTH($aguser);
                }
                $token = $auth['result']['tokenType'] . ' ' . $auth['result']['accessToken'];
                $oldcredit = $this->ViewbalanceVegus($token, $userid);

                $param = [
                    "username" => $userid,
                    "type" => "deposit",
                    "amount" => $amount
                ];

                $url = 'https://vegusapi.asgard-serv.com/gateway/users/finance';
                $response = Http::timeout(15)->withHeaders([
                    'Authorization' => $token,
                ])->asJson()->post($url, $param);

                if ($response->successful()) {
                    $result = $response->json();
                    $aftercredit = $this->ViewbalanceVegus($token, $userid);
                    if ($result['status'] == 'SUCCESS') {

                        $chk->webcode = $member->web_code;
                        $chk->status = 1;
                        $chk->oldcredit = $oldcredit['result']['credit'];
                        $chk->score = $chk->value;
                        $chk->aftercredit = $aftercredit['result']['credit'];
                        $chk->webbefore = $webbefore;
                        $chk->webafter = $webafter;
                        $chk->user_id = $admin->user_name;
                        $chk->topupstatus = 'Y';
                        $chk->date_topup = now()->toDateTimeString();
                        $chk->save();


                        $website->balance = $webafter;
                        $website->save();

                        $return['success'] = true;
                        $return['msg'] = 'เติมเงิน สำเร็จ';
                    } else {
                        $return['success'] = false;
                        $return['msg'] = 'มีปัญหาบางประการ ในการเติมเงิน';
                    }
                } else {
                    $return['success'] = false;
                    $return['msg'] = 'เชื่อมต่อ API ไม่ได้';
                }

                break;

            case '2':

                $dataapi = [
                    'amount' => $chk->value,
                    'user' => $chk->tranferer,
                    'aguser' => $website->user,
                    'agpass' => $website->pass,
                    'scode' => $website->scode
                ];

                $url = 'https://bot.ipzeroline.com/igoal/deposit.php';
                $response = Http::timeout(15)->asJson()->post($url, $dataapi);
                if ($response->successful()) {
                    $result = $response->json();
                    if ($result['success'] == '200') {
                        $chk->webcode = $member->web_code;
                        $chk->status = 1;
                        $chk->oldcredit = $result['oldcredit'];
                        $chk->score = $chk->value;
                        $chk->aftercredit = $result['credit'];
                        $chk->webbefore = $webbefore;
                        $chk->webafter = $webafter;
                        $chk->user_id = $admin->user_name;
                        $chk->topupstatus = 'Y';
                        $chk->date_topup = now()->toDateTimeString();
                        $chk->save();


                        $website->balance = $webafter;
                        $website->save();

                        $return['success'] = true;
                        $return['msg'] = 'เติมเงิน สำเร็จ';
                    } else {
                        $return['success'] = false;
                        $return['msg'] = 'มีปัญหาบางประการ ในการเติมเงิน';
                    }
                } else {
                    $return['success'] = false;
                    $return['msg'] = 'เชื่อมต่อ API ไม่ได้';
                }

                break;

            case '3':
                $param = [
                    'credit' => $chk->value,
                    'username' => $chk->tranferer,
                    'agentUsername' => $website->user,
                    'agentPassword' => $website->pass,
                ];
                $url = 'https://kraken.mrwed.cloud/partner/user/credit/add';
                $response = Http::timeout(15)->withHeaders([
                    'x-api-key' => '2b6e6699-1a5c-448c-a5b8-47d1038eb2b4',
                ])->asJson()->post($url, $param);
                if ($response->successful()) {
                    $result = $response->json();
                    if ($result['status'] == 'success') {
                        $chk->webcode = $member->web_code;
                        $chk->status = 1;
                        $chk->oldcredit = $result['old_credit'];
                        $chk->score = $chk->value;
                        $chk->aftercredit = $result['current_credit'];
                        $chk->webbefore = $webbefore;
                        $chk->webafter = $webafter;
                        $chk->user_id = $admin->user_name;
                        $chk->topupstatus = 'Y';
                        $chk->date_topup = now()->toDateTimeString();
                        $chk->save();


                        $website->balance = $webafter;
                        $website->save();

                        $return['success'] = true;
                        $return['msg'] = 'เติมเงิน สำเร็จ';
                    } else {
                        $return['success'] = false;
                        $return['msg'] = 'มีปัญหาบางประการ ในการเติมเงิน';
                    }
                } else {
                    $return['success'] = false;
                    $return['msg'] = 'เชื่อมต่อ API ไม่ได้';
                }

                break;

            case '4':

                $time = round(microtime(true) * 1000);
                $transID = "DEP" . $time;
                $param = [
                    "appid" => $website->user,
                    "credit" => $chk,
                    "transactionId" => $transID,
                    "username" => $chk->tranferer,
                ];

                $postString = http_build_query($param, '', '&');
                $signature = base64_encode(hash_hmac('SHA1', $postString, $website->appID, true));
                $param['signature'] = $signature;


                $url = 'https://apiv2.lsmapi.net/api/member/balance/deposit';
                $response = Http::timeout(15)->asJson()->post($url, $param);
                if ($response->successful()) {
                    $result = $response->json();
                    if ($result['status'] == 'success') {
                        $chk->webcode = $member->web_code;
                        $chk->status = 1;
                        $chk->oldcredit = $result['data']['credit'] - $chk->value;
                        $chk->score = $chk->value;
                        $chk->aftercredit = $result['data']['credit'];
                        $chk->webbefore = $webbefore;
                        $chk->webafter = $webafter;
                        $chk->user_id = $admin->user_name;
                        $chk->topupstatus = 'Y';
                        $chk->date_topup = now()->toDateTimeString();
                        $chk->save();


                        $website->balance = $webafter;
                        $website->save();

                        $return['success'] = true;
                        $return['msg'] = 'เติมเงิน สำเร็จ';
                    } else {
                        $return['success'] = false;
                        $return['msg'] = 'มีปัญหาบางประการ ในการเติมเงิน';
                    }

                } else {
                    $return['success'] = false;
                    $return['msg'] = 'เชื่อมต่อ API ไม่ได้';
                }

                break;


        }



        return $this->sendResponse($return,'complete');
    }


    public function ViewbalanceVegus($agent, $username)
    {
        $url = 'https://vegusapi.asgard-serv.com/gateway/users/info?username=' . $username;
        $response = Http::timeout(15)->withHeaders([
            'Authorization' => $agent,
        ])->asJson()->get($url);
        if ($response->successful()) {
            return $response->json();
        } else {
            return false;
        }
    }

    public function VegusAUTH(string $agent)
    {
        $param = [
            "username"  => 'vegusapi',
            "password"  => 'vegus168@iap#P@ssw0rd884@!fktxs',
            "agentName" => $agent,
        ];
        $url = 'https://vegusapi.asgard-serv.com/gateway/auth/signin';

        $response = Http::timeout(15)->retry(2, 300)->asJson()->post($url, $param);
        return $response->successful() ? $response->json() : false;
    }

    public function refill(Request $request)
    {
        $user = $this->user();
        $google2fa = new Google2FA();

        $return['success'] = false;

        $datenow = now()->toDateTimeString();
        $date = now()->toDateString();
        $fulluser = $user->name . ' ' . $user->surname;
        $ip = $request->ip();

        $config = core()->getConfigData();

        $request->validate([
            'id' => 'required',
            'amount' => 'required|numeric',
            'account_code' => 'required|string',
            'date_bank' => 'required|date_format:Y-m-d',
            'time_bank' => 'required|date_format:H:i',
        ]);

        $id = $request->input('id');
        $amount = $request->input('amount');

        $account = $request->input('account_code');
        $banks = explode('_',$account);
        $bank = $banks[0];

        $banktime = Carbon::createFromFormat('Y-m-d H:i', $request->date_bank . ' ' . $request->time_bank);

//        $member = $this->memberRepository->findByField('user',$id)->first();

//        $bank_account = app('Gametech\Payment\Repositories\BankAccountRepository')->findByField(''$account);

//        $bank = app('Gametech\Payment\Repositories\BankRepository')->find($bank_account->banks);

        if ($amount < 1) {
            return $this->sendError('ยอดเงินไม่ถูกต้อง', 200);
        }

        $detail = 'เพิ่มรายการฝากเงินโดย Staff : ' . $fulluser;


        $hash = md5($account . $datenow . $amount . $detail);


        $checktime = strtotime(date('Y-m-d H:i:s'));
        $newpayment = BankPaymentProxy::firstOrNew(['tx_hash' => $hash, 'bank' => $account]);
        $newpayment->bank = $account;
        $newpayment->bankstatus = 1;
        $newpayment->bankname = strtoupper($bank);
        $newpayment->checktime = $checktime;
        $newpayment->time = $banktime;
        $newpayment->channel = 'MANUAL';
        $newpayment->value = $amount;
        $newpayment->tx_hash = $hash;
        $newpayment->detail = 'เพิ่มรายการฝากให้กับ User : ' . $id;
        $newpayment->atranferer = '';
        $newpayment->tranferer = $id;
        $newpayment->check_user = $user->user_name;
        $newpayment->checking = 'Y';
        $newpayment->checkstatus = 'Y';
        $newpayment->save();

        $approvalResult = $this->processApproval($newpayment, $user);
        if (!$approvalResult['success']) {
            return $this->sendError('สร้างรายการฝากใหม่ สำเร็จ แต่ไม่สามารถ เติมเงินเข้าไอดีสมาชิกได้ : ' . $approvalResult['msg'], 200);
        }

        return $this->sendSuccess('ดำเนินการ ทำรายการฝากเงินและเติมเข้าไอดีลูกค้า เรียบร้อยแล้ว');


    }

    private function processApproval($payment,$admin)
    {
        if ($payment->topupstatus === 'Y' || (int)$payment->status === 1) {
            return ['success' => false, 'msg' => 'รายการนี้ ได้เติมเข้าไอดี แล้ว'];
        }

        $member = MemberWebProxy::where('user', $payment->tranferer)->first();
        if (!$member) return ['success' => false, 'msg' => 'ไม่พบข้อมูลสมาชิก'];

        $website = WebsiteProxy::where('code', $member->web_code)->lockForUpdate()->first();
        if (!$website) return ['success' => false, 'msg' => 'ไม่พบข้อมูล Agent'];

        $groupBot = (string)($website->group_bot ?? '');
        $amount   = (float)$payment->value;

        if ($amount <= 0) return ['success' => false, 'msg' => 'จำนวนเงินไม่ถูกต้อง'];

        $updated = DB::table($payment->getTable())
            ->where('id', $payment->id)
            ->where(function ($q) {
                $q->whereNull('topupstatus')->orWhere('topupstatus', '!=', 'Y');
            })
            ->update(['topupstatus' => 'Y']);

        if ($updated === 0) {
            return ['success' => false, 'msg' => 'รายการนี้ถูกดำเนินการโดยผู้อื่นแล้ว'];
        }

        try {
            $result = DB::transaction(function () use ($admin, $payment, $website, $member, $groupBot, $amount) {
                $providerRes = $this->depositByProvider($groupBot, [
                    'amount'   => $amount,
                    'username' => $payment->tranferer,
                    'website'  => $website,
                    'member'   => $member,
                ]);

                if (!$providerRes['success']) {
                    throw new \RuntimeException($providerRes['msg'] ?? 'เติมเงินล้มเหลว');
                }

                $webBefore = (float)$website->balance;
                $webAfter  = $webBefore - $amount;

                $payment->fill([
                    'webcode'     => $member->web_code,
                    'status'      => 1,
                    'oldcredit'   => $providerRes['old_credit']   ?? null,
                    'score'       => $amount,
                    'aftercredit' => $providerRes['after_credit'] ?? null,
                    'webbefore'   => $webBefore,
                    'webafter'    => $webAfter,
                    'user_id'     => $admin->user_name,
                    'topupstatus' => 'Y',
                    'date_topup'  => Carbon::now()->toDateTimeString(),
                ]);
                $payment->save();

                $website->balance = $webAfter;
                $website->save();

                return ['success' => true, 'msg' => 'เติมเงิน สำเร็จ'];
            });

            return $result;

        } catch (\Throwable $e) {
            DB::table($payment->getTable())->where('id', $payment->id)->update(['topupstatus' => 'N']);

            Log::error('Auto-approve failed', [
                'id' => $payment->id,
                'user' => $payment->tranferer,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'msg' => $e->getMessage()];
        }
    }



    public function approve(Request $request)
    {
        $admin = $this->user();
        $id    = $request->input('id');

        // 1) ดึงรายการ + guard
        $chk = $this->repository->find($id);
        if (!$chk) {
            return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        }
        if ($chk->topupstatus === 'Y' || (int)$chk->status === 1) {
            return $this->sendError('รายการนี้ ได้เติมเข้าไอดี แล้ว');
        }

        // 2) ดึง member/website
        $member   = MemberWebProxy::where('user', $chk->tranferer)->first();
        if (!$member) return $this->sendError('ไม่พบข้อมูลสมาชิก', 200);

        /** @var WebsiteProxy $website */
        $website  = WebsiteProxy::where('code', $member->web_code)->lockForUpdate()->first(); // lock แถวนี้ใน Tx
        if (!$website) return $this->sendError('ไม่พบข้อมูล Agent', 200);

        $groupBot = (string)($website->group_bot ?? '');
        $amount   = (float)$chk->value;

        // 3) ตรวจเงิน agent พอก่อนไหม
        if ($amount <= 0) return $this->sendError('จำนวนเงินไม่ถูกต้อง', 200);
//        if ($website->balance < $amount) {
//            return $this->sendError('ยอดเงินของ Agent ไม่เพียงพอ', 200);
//        }

        // 4) กัน double approve แบบ atomic ด้วย “compare-and-set”
        //    อัพเดตสถานะเฉพาะเมื่อยังไม่เคย approve มาก่อน
        $updated = DB::table($chk->getTable())
            ->where('id', $chk->id)
            ->where(function($q){ $q->whereNull('topupstatus')->orWhere('topupstatus','!=','Y'); })
            ->update(['topupstatus' => 'Y']); // P = processing
        if ($updated === 0) {
            return $this->sendError('รายการนี้ถูกดำเนินการโดยผู้อื่นแล้ว', 200);
        }

        // 5) ทำใน Transaction ให้ครบทั้งฝั่งเรา (ไม่รวม API ภายนอก)
        try {
            $result = DB::transaction(function () use ($admin, $chk, $website, $member, $groupBot, $amount) {

                // --- ยิง API ตาม provider ---
                $providerRes = $this->depositByProvider($groupBot, [
                    'amount'   => $amount,
                    'username' => $chk->tranferer,
                    'website'  => $website,
                    'member'   => $member,
                ]);

                if (!$providerRes['success']) {
                    // rollback โดยการ throw
                    throw new \RuntimeException($providerRes['msg'] ?? 'เติมเงินล้มเหลว');
                }

                // --- บันทึกผลฝั่งเรา (atomic) ---
                $webBefore = (float)$website->balance;
                $webAfter  = $webBefore - $amount;

                // อัปเดตตารางรายการ
                $chk->fill([
                    'webcode'     => $member->web_code,
                    'status'      => 1,
                    'oldcredit'   => $providerRes['old_credit']   ?? null,
                    'score'       => $amount,
                    'aftercredit' => $providerRes['after_credit'] ?? null,
                    'webbefore'   => $webBefore,
                    'webafter'    => $webAfter,
                    'user_id'     => $admin->user_name,
                    'topupstatus' => 'Y',
                    'date_topup'  => Carbon::now()->toDateTimeString(),
                ]);
                $chk->save();

                // อัปเดตยอด Agent
                $website->balance = $webAfter;
                $website->save();

                return [
                    'success' => true,
                    'msg'     => 'เติมเงิน สำเร็จ',
                    'old'     => $providerRes['old_credit'] ?? null,
                    'after'   => $providerRes['after_credit'] ?? null,
                ];
            }, 1); // retry 1 ครั้งถ้าล็อกชน

            return response()->json([
                'success' => true,
                'message' => $result['msg'] ?? 'เติมเงิน สำเร็จ',
                'old'     => $result['old'] ?? null,
                'after'   => $result['after'] ?? null,
            ]);

        } catch (\Throwable $e) {

            // คืนสถานะจาก 'P' → ยังไม่สำเร็จ (กัน pending ค้าง)
            DB::table($chk->getTable())->where('id', $chk->id)->update(['topupstatus' => 'N']);

            Log::error('Topup failed', [
                'id'        => $chk->id,
                'user'      => $chk->tranferer,
                'provider'  => $website->group_bot,
                'website'   => $website->code ?? null,
                'error'     => $e->getMessage(),
            ]);

            return $this->sendError($e->getMessage() ?: 'มีปัญหาบางประการ ในการเติมเงิน', 200);
        }
    }

    /**
     * ยิง API ตาม provider และคืนค่าในรูปแบบกลาง
     * return [
     *   'success' => bool,
     *   'msg' => string,
     *   'old_credit' => mixed|null,
     *   'after_credit' => mixed|null,
     * ]
     */
    protected function depositByProvider(string $groupBot, array $ctx): array
    {
        $amount   = (float)$ctx['amount'];
        $username = (string)$ctx['username'];
        /** @var WebsiteProxy $website */
        $website  = $ctx['website'];

        try {
            switch ($groupBot) {
                case '1': // VEGUS
                    $aguser = $website->user;
                    $auth   = $this->VegusAUTH($aguser) ?: $this->VegusAUTH($aguser);
                    if (!$auth || empty($auth['result']['accessToken'])) {
                        return ['success'=>false, 'msg'=>'Auth Vegus ล้มเหลว'];
                    }
                    $token = $auth['result']['tokenType'].' '.$auth['result']['accessToken'];
                    $old   = $this->ViewbalanceVegus($token, $username);
                    $oldCr = $old['result']['credit'] ?? null;

                    $url = 'https://vegusapi.asgard-serv.com/gateway/users/finance';
                    $param = ["username" => $username, "type" => "deposit", "amount" => $amount];

                    $res = Http::timeout(15)->withHeaders(['Authorization'=>$token])
                        ->retry(2, 300) // retry เบาๆ
                        ->asJson()->post($url, $param);

                    if (!$res->successful()) return ['success'=>false, 'msg'=>'เชื่อมต่อ API ไม่ได้'];

                    $json = $res->json();
                    if (($json['status'] ?? null) !== 'SUCCESS') {
                        return ['success'=>false, 'msg'=>'เติมเงิน Vegus ไม่สำเร็จ'];
                    }

                    $after = $this->ViewbalanceVegus($token, $username);
                    $afterCr = $after['result']['credit'] ?? null;

                    return ['success'=>true, 'msg'=>'OK', 'old_credit'=>$oldCr, 'after_credit'=>$afterCr];

                case '2': // igoal bot
                    $url = 'https://bot.ipzeroline.com/igoal/deposit.php';
                    $payload = [
                        'amount' => $amount,
                        'user'   => $username,
                        'aguser' => $website->user,
                        'agpass' => $website->pass,
                        'scode'  => $website->scode,
                    ];
                    $res = Http::timeout(15)->retry(2, 300)->asJson()->post($url, $payload);
                    if (!$res->successful()) return ['success'=>false, 'msg'=>'เชื่อมต่อ API ไม่ได้'];

                    $json = $res->json();
                    if (($json['success'] ?? '') !== '200') {
                        return ['success'=>false, 'msg'=>'เติมเงิน IGoal ไม่สำเร็จ'];
                    }
                    return [
                        'success'      => true,
                        'msg'          => 'OK',
                        'old_credit'   => $json['oldcredit'] ?? null,
                        'after_credit' => $json['credit'] ?? null,
                    ];

                case '3': // kraken
                    $url   = 'https://kraken.mrwed.cloud/partner/user/credit/add';
                    $param = [
                        'credit'        => $amount,
                        'username'      => $username,
                        'agentUsername' => $website->user,
                        'agentPassword' => $website->pass,
                    ];
                    $res = Http::timeout(15)->withHeaders([
                        'x-api-key' => config('services.kraken.key', '2b6e6699-1a5c-448c-a5b8-47d1038eb2b4'),
                    ])->retry(2, 300)->asJson()->post($url, $param);

                    if (!$res->successful()) return ['success'=>false, 'msg'=>'เชื่อมต่อ API ไม่ได้'];

                    $json = $res->json();
                    if (($json['status'] ?? null) !== 'success') {
                        return ['success'=>false, 'msg'=>'เติมเงิน Kraken ไม่สำเร็จ'];
                    }
                    return [
                        'success'      => true,
                        'msg'          => 'OK',
                        'old_credit'   => $json['old_credit'] ?? null,
                        'after_credit' => $json['current_credit'] ?? null,
                    ];

                case '4': // lsmapi
                    $time    = (string) round(microtime(true) * 1000);
                    $transID = "DEP{$time}";
                    $param = [
                        "appid"         => $website->user,
                        "credit"        => $amount,             // ← แก้จาก $chk เป็น $amount
                        "transactionId" => $transID,
                        "username"      => $username,
                    ];
                    $postString = http_build_query($param, '', '&');
                    $signature  = base64_encode(hash_hmac('SHA1', $postString, $website->appID, true));
                    $param['signature'] = $signature;

                    $url = 'https://apiv2.lsmapi.net/api/member/balance/deposit';
                    $res = Http::timeout(15)->retry(2, 300)->asJson()->post($url, $param);

                    if (!$res->successful()) return ['success'=>false, 'msg'=>'เชื่อมต่อ API ไม่ได้'];

                    $json = $res->json();
                    if (($json['status'] ?? null) !== 'success') {
                        return ['success'=>false, 'msg'=>'เติมเงิน LSM ไม่สำเร็จ'];
                    }

                    $afterCredit = data_get($json, 'data.credit');
                    return [
                        'success'      => true,
                        'msg'          => 'OK',
                        'old_credit'   => $afterCredit !== null ? $afterCredit - $amount : null,
                        'after_credit' => $afterCredit,
                    ];

                default:
                    return ['success'=>false, 'msg'=>'ไม่รู้จัก provider group'];
            }

        } catch (\Throwable $e) {
            Log::warning('Provider deposit error', [
                'provider' => $groupBot,
                'user'     => $username,
                'error'    => $e->getMessage(),
            ]);
            return ['success'=>false, 'msg'=>'เชื่อมต่อ API ไม่ได้'];
        }
    }


    public function cancel(Request $request)
    {
        $ip = $request->ip();
        $admin = $this->user();
        $user = $admin->name . ' ' . $admin->surname;
        $id = $request->input('id');

        $chk = $this->repository->find($id);
        if (!$chk) {
            return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        }


        $data['tranferer'] = '';
        $data['check_user'] = '';
        $data['checking'] = 'N';
        $this->repository->update($data, $id);

        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');
    }


}
