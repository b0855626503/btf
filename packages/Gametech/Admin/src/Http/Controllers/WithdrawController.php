<?php

namespace Gametech\Admin\Http\Controllers;

use Gametech\Admin\DataTables\WithdrawDataTable;
use Gametech\Auto\Jobs\PaymentOutKbank;
use Gametech\Auto\Jobs\PaymentOutLuckyPay;
use Gametech\Auto\Jobs\PaymentOutPapayaPay;
use Gametech\Auto\Jobs\PaymentOutPomPay;
use Gametech\Auto\Jobs\PaymentOutScb;
use Gametech\Member\Repositories\MemberCreditLogRepository;
use Gametech\Payment\Repositories\WithdrawRepository;
use Illuminate\Http\Request;

// ✅ เพิ่มเติม
use Gametech\Member\Models\MemberWebProxy;
use Gametech\Core\Models\WebsiteProxy;
use Gametech\Integrations\ProviderManager;
use Gametech\Integrations\Contracts\BalanceContext;
use Illuminate\Support\Facades\Cache;


class WithdrawController extends AppBaseController
{
    protected $_config;
    protected $repository;
    protected $memberCreditLogRepository;

    // ✅ เพิ่ม property
    protected ProviderManager $providers;

    public function __construct(
        WithdrawRepository        $repository,
        MemberCreditLogRepository $memberCreditLogRepo,
        ProviderManager           $providers // ✅ ฉีดเข้ามา
    )
    {
        $this->_config = request('_config');
        $this->middleware('admin');

        $this->repository               = $repository;
        $this->memberCreditLogRepository = $memberCreditLogRepo;
        $this->providers                = $providers; // ✅ เก็บไว้ใช้
    }


    public function index(WithdrawDataTable $withdrawDataTable)
    {
        return $withdrawDataTable->render($this->_config['view']);
    }

    public function loadData(Request $request)
    {
        $id = $request->input('id');


        $data = $this->repository->with(['member', 'bank'])->find($id);

        if (!$data) {
            return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        }


        return $this->sendResponse($data, 'ดำเนินการเสร็จสิ้น');

    }

    public function edit(Request $request)
    {
        $user = $this->user()->name . ' ' . $this->user()->surname;
        $id = $request->input('id');
        $status = $request->input('status');
        $method = $request->input('method');


        $data[$method] = $status;

        $chk = $this->repository->find($id);
        if (!$chk) {
            return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        }

        $data['user_update'] = $user;
        $this->repository->update($data, $id);

        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');

    }

    public function update($id, Request $request)
    {
        $ip = $request->ip();
        $user = $this->user()->name . ' ' . $this->user()->surname;
        $datenow = now()->toDateTimeString();

        $data = json_decode($request['data'], true);


        $chk = $this->repository->find($id);
        if (!$chk) {
            return $this->sendSuccess('ไม่พบข้อมูลดังกล่าว');
        }

        $status_wd = ($chk->status_withdraw ?? null);
        if (!is_null($status_wd)) {


            if ($chk->emp_approve > 0 || $chk->status_withdraw != 'W') {
                return $this->sendSuccess('รายการนี้ นี้มีผู้ทำรายการแล้ว');
            }


            $data['member_code'] = $chk->member_code;
            $data['amount'] = $chk->amount;
            $data['emp_approve'] = $this->id();
            $data['ip_admin'] = $ip;
            $data['user_update'] = $user;
            $data['date_approve'] = $datenow;
            $this->repository->update($data, $id);

            if ($data['account_code'] != 0) {

                $bank = app('Gametech\Payment\Repositories\BankAccountRepository')->getAccountOutOne($data['account_code']);
                if (isset($bank)) {
                    $bank_code = $bank->bank->code;
                    if ($bank_code == 2) {
                        $return = PaymentOutKbank::dispatchNow($id);
                    } elseif ($bank_code == 4) {
                        $return = PaymentOutScb::dispatchNow($id);
                    } elseif ($bank_code == 99) {
                        $return = PaymentOutPomPay::dispatchNow($id);
                    } elseif ($bank_code == 101) {
                        $return = PaymentOutLuckyPay::dispatchNow($id);
                    } elseif ($bank_code == 102) {
                        $return = PaymentOutPapayaPay::dispatchNow($id);
                    } else {
                        $return['success'] = 'NORMAL';
                        $return['complete'] = true;
                        $return['msg'] = 'อนุมัติรายการเรียบร้อยแล้ว (รายการทั่วไป)';
                    }
                } else {
                    $return['success'] = 'NORMAL';
                    $return['complete'] = true;
                    $return['msg'] = 'อนุมัติรายการเรียบร้อยแล้ว (รายการทั่วไป)';
                }
            } else {
                $return['success'] = 'NORMAL';
                $return['complete'] = true;
                $return['msg'] = 'อนุมัติรายการเรียบร้อยแล้ว (รายการทั่วไป)';
            }


            switch ($return['success']) {
                case 'NORMAL':
                    $datanew['status'] = 1;
                    $this->repository->update($datanew, $id);
                    break;

                case 'NOMONEY':
                case 'FAIL_AUTO':
                    $datanew['txid'] = '';
                    $datanew['account_code'] = 0;
                    $datanew['status_withdraw'] = 'W';
                    $datanew['status'] = 0;
                    $datanew['emp_approve'] = 0;
                    $datanew['ip_admin'] = '';
                    $this->repository->update($datanew, $id);
                    break;

                case 'COMPLETE':
                case 'NOTWAIT':
                case 'MONEY':
                    break;

            }

            if ($return['complete'] === true) {


                $member = app('Gametech\Member\Repositories\MemberRepository')->find($chk->member_code);


                $game_user = app('Gametech\Game\Repositories\GameUserRepository')->findOneByField('member_code', $chk->member_code);


                $this->memberCreditLogRepository->create([
                    'ip' => $ip,
                    'credit_type' => 'D',
                    'balance_before' => $member->balance,
                    'balance_after' => $member->balance,
                    'credit' => 0,
                    'total' => $chk->amount,
                    'credit_bonus' => 0,
                    'credit_total' => 0,
                    'credit_before' => $member->balance,
                    'credit_after' => $member->balance,
                    'pro_code' => 0,
                    'bank_code' => $chk->bankm_code,
                    'auto' => 'N',
                    'enable' => 'Y',
                    'user_create' => "System Auto",
                    'user_update' => "System Auto",
                    'refer_code' => $id,
                    'refer_table' => 'withdraws',
                    'remark' => 'เครดิตที่หักออกจากระบบ ' . $chk->balance . ' / จะได้รับยอดเงินผ่านเลขที่บัญชี : ' . $member->acc_no,
                    'kind' => 'CONFIRM_WD',
                    'amount' => $chk->amount,
                    'amount_balance' => $game_user->amount_balance,
                    'withdraw_limit' => $game_user->withdraw_limit,
                    'withdraw_limit_amount' => $game_user->withdraw_limit_amount,
                    'method' => 'D',
                    'member_code' => $chk->member_code,
                    'user_name' => $member->user_name,
                    'emp_code' => $this->id(),
                    'emp_name' => $this->user()->name . ' ' . $this->user()->surname
                ]);
            }

            return $this->sendSuccess($return['msg']);

        } else {

            if ($chk->emp_approve > 0) {
                return $this->sendSuccess('รายการนี้ นี้มีผู้ทำรายการแล้ว');
            }

            $data['emp_approve'] = $this->id();
            $data['status'] = 1;
            $data['ip_admin'] = $ip;
            $data['user_update'] = $user;
            $data['date_approve'] = $datenow;
            $this->repository->update($data, $id);

            return $this->sendSuccess('ดำเนินการสำเร็จแล้ว');
        }


    }

    public function clear(Request $request)
    {
        $config = core()->getConfigData();
        $user = $this->user()->name . ' ' . $this->user()->surname;
        $id = $request->input('id');
        $remark = $request->input('remark');

        $chk = $this->repository->find($id);

        if (!$chk) {
            return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        }

        if ($chk->emp_approve > 0) {
            return $this->sendSuccess('รายการนี้ นี้มีผู้ทำรายการแล้ว');
        }


        $datanew = [
            'refer_code' => $id,
            'refer_table' => 'withdraws',
            'remark' => 'คืนยอดจากการถอน',
            'kind' => 'ROLLBACK',
            'amount' => $chk->amount,
            'method' => 'D',
            'member_code' => $chk->member_code,
            'emp_code' => $this->id(),
            'emp_name' => $this->user()->name . ' ' . $this->user()->surname
        ];

        if ($config->multigame_open == 'Y') {
            $response = $this->memberCreditLogRepository->setWallet($datanew);
        } else {
            $response = $this->memberCreditLogRepository->setWalletSingle($datanew);
        }


        if ($response) {
            $data['ip_admin'] = $request->ip();
            $data['remark_admin'] = $remark;
            $data['status'] = 2;
            $data['emp_approve'] = $this->id();
            $data['user_update'] = $user;
            $data['date_approve'] = now()->toDateTimeString();
            $this->repository->update($data, $id);
        }


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

    public function fixSubmit(Request $request)
    {
        $user = $this->user()->name . ' ' . $this->user()->surname;
        $id = $request->input('id');

        $chk = $this->repository->find($id);

        if (!$chk) {
            return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        }


        $data['emp_approve'] = 0;
        $data['status_withdraw'] = 'W';
        $data['user_update'] = $user;
        $this->repository->update($data, $id);

        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');
    }

    public function loadBank()
    {
        $banks = [
            'value' => '0',
            'text' => 'ไม่ระบุบัญชี'
        ];

        $responses = app('Gametech\Payment\Repositories\BankAccountRepository')->getAccountOutAll()->toArray();

        $responses = collect($responses)->map(function ($items) {
            $item = (object)$items;
//            dd($item);
            return [
                'value' => $item->code,
                'text' => $item->bank['name_th'] . ' [' . $item->acc_no . ']' . $item->acc_name
            ];

        })->prepend($banks);

//        $responses = collect(app('Gametech\Payment\Repositories\BankRepository')->getBankOutAccount()->toArray());
//
//        $responses = $responses->map(function ($items) {
//            $item = (object)$items;
//            return [
//                'value' => $item->bank_account['code'],
//                'text' => $item->name_th . ' [' . $item->bank_account['acc_no'] . ']'
//            ];
//
//        })->prepend($banks);


        $result['banks'] = $responses;
        return $this->sendResponseNew($result, 'complete');
    }

    public function loadUser(Request $request)
    {
        $id = $request->input('id');

        // ดึง member + ความสัมพันธ์ที่ต้องการ (เหมือนเดิม)
        $member = MemberWebProxy::where('user', $id)->with('me')->first();
        if (empty($member)) {
            return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        }

        // หา website/agent เพื่อรู้ group_bot
        $website = WebsiteProxy::where('code', $member->web_code)->first();
        if (!$website) {
            // คืนข้อมูลสมาชิกตามเดิม แต่แจ้งว่าไม่พบ agent
            return $this->sendResponse([
                'member' => $member,
                'balance' => [
                    'success' => false,
                    'credit'  => null,
                    'message' => 'ไม่พบข้อมูล Agent ของสมาชิก',
                    'group_bot' => null,
                ],
            ], 'พบข้อมูลสมาชิก (ไม่มี Agent)');
        }

        $groupBot = (string) ($website->group_bot ?? '');

        // คีย์แคชย่อย ลดการยิง API ถี่ ๆ  (TTL 10 วินาที ตามเหมาะสม)
        $cacheKey = "userbalance:{$groupBot}:{$member->user}";
        $balancePayload = Cache::remember($cacheKey, 10, function () use ($member, $website, $groupBot) {
            try {
                $provider  = $this->providers->resolve($groupBot);
                $ctx       = new BalanceContext(
                    username:  $member->user,
                    website:   $website,
                    timeoutSec: (int) config('integrations.providers.timeouts', 15),
                    retryTimes: (int) config('integrations.providers.retries.times', 2),
                    retrySleepMs: (int) config('integrations.providers.retries.sleep_ms', 300),
                );
                $res = $provider->balance($ctx);

                return [
                    'success'   => (bool) $res->success,
                    'credit'    => $res->credit,
                    'message'   => $res->msg ?: ($res->success ? 'OK' : 'ไม่รองรับการดึงยอด'),
                    'group_bot' => $groupBot,
                    'raw'       => $res->raw, // เผื่อดีบักฝั่งหน้าแอดมิน (อย่าโชว์ฝั่งลูกค้า)
                ];
            } catch (\Throwable $e) {
                // fallback กรณี provider โยน exception
                return [
                    'success'   => false,
                    'credit'    => null,
                    'message'   => 'ดึงยอดล้มเหลว: '.$e->getMessage(),
                    'group_bot' => $groupBot,
                ];
            }
        });

        // รูปแบบ response เดิม + พ่วง balance จาก provider
        // หมายเหตุ: ถ้า code เดิมของคุณ front รับเฉพาะ object เดียว ให้คง key 'data' ตามที่เคย; ที่นี่ใช้ sendResponse()
        return $this->sendResponse([
            'member'  => $member,
            'balance' => $balancePayload, // => {success, credit, message, group_bot}
        ], 'พบข้อมูลสมาชิก + ยอดคงเหลือล่าสุดจาก provider');
    }


}
