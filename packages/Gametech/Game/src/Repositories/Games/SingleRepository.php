<?php

namespace Gametech\Game\Repositories\Games;

use Gametech\API\Models\GameListProxy;
use Gametech\Core\Eloquent\Repository;
use Gametech\Member\Models\MemberCreditLogProxy;
use Illuminate\Container\Container as App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Jenssegers\Agent\Agent;

class SingleRepository extends Repository
{
    protected $responses;

    protected $method;

    protected $debug;

    protected $url;

    protected $agent;

    protected $agentPass;

    protected $passkey;

    protected $secretkey;

    protected $login;

    protected $auth;

    public function __construct($method, $debug, App $app)
    {
        $game = 'single';

        $this->method = $method;

        $this->debug = $debug;

        $this->url = config($this->method.'.'.$game.'.apiurl');

        $this->agent = config($this->method.'.'.$game.'.agent');

        $this->agentPass = config($this->method.'.'.$game.'.agent_pass');

        $this->login = config($this->method.'.'.$game.'.login');

        $this->auth = config($this->method.'.'.$game.'.auth');

        $this->passkey = config($this->method.'.'.$game.'.passkey');

        $this->secretkey = config($this->method.'.'.$game.'.secretkey');

        $this->responses = [];

        parent::__construct($app);
    }

    public function addGameAccount($data): array
    {
        $result = $this->newUser();
        if ($result['success'] === true) {
            $account = $result['account'];
            $result = $this->addUser($account, $data);
        }

        return $result;
    }

    public function newUser(): array
    {
        $return['success'] = true;
        $return['account'] = '';

        return $return;
    }

    public function addUser($username, $data): array
    {
        $return['success'] = false;

        //        $param = [
        //            'username' => $data['username'],
        //            'productId' => 'JOKER'
        //        ];

        //        $response = $this->GameCurl($param, 'seamless/member');

        //        $path = storage_path('logs/seamless/register' . now()->format('Y_m_d') . '.log');
        //        file_put_contents($path, print_r('-- TRANSACTION --', true), FILE_APPEND);
        //        file_put_contents($path, print_r($response, true), FILE_APPEND);
        //        file_put_contents($path, print_r($param, true), FILE_APPEND);

        //        if ($response['success'] === true) {

        $return['msg'] = 'Complete';
        $return['success'] = true;
        $return['user_name'] = $data['username'];
        $return['user_pass'] = $username;

        //        } else {
        //            $return['msg'] = $response['msg'];
        //            $return['success'] = false;
        //
        //        }

        if ($this->debug) {
            return ['debug' => $this->responses, 'success' => true];
        }

        return $return;
    }

    public function changePass($data): array
    {
        $return['success'] = false;

        $param = [
            'Method' => 'SP',
            'Password' => $data['user_pass'],
            'Timestamp' => time(),
            'Username' => $data['user_name'],
        ];

        $response = $this->GameCurl($param, '');

        if ($response['success'] === true) {
            if ($response['Status'] === 'OK') {
                $return['msg'] = 'เปลี่ยนรหัสผ่านเกม เรียบร้อย';
                $return['success'] = true;
            }
        } else {
            $return['msg'] = $response['msg'];
            $return['success'] = false;
        }

        if ($this->debug) {
            return ['debug' => $this->responses, 'success' => true];
        }

        return $return;
    }

    public function GameCurl($param, $action)
    {

        $response = rescue(function () use ($param, $action) {

            $url = $this->url.$action;

            return Http::timeout(10)->withOptions(['debug' => false])->asJson()->post($url, $param);

        }, function ($e) {

            return false;

        }, true);

        if ($this->debug) {
            $this->Debug($response);
        }

        if ($response === false) {
            //            $result['main'] = false;
            $result['success'] = false;
            $result['msg'] = 'เชื่อมต่อไม่ได้';

            return $result;
        }

        $result = $response->json();

        $result['msg'] = ($result['message'] ?? 'พบปัญหาบางประการ');

        if ($response->successful()) {
            if ($result['status'] == 'SUCCESS') {
                $result['success'] = true;
            } else {
                $result['success'] = false;
            }

        } else {
            $result['success'] = false;
        }

        return $result;

    }

    public function Debug($response, $custom = false)
    {

        if (! $custom) {
            $return['body'] = $response->body();
            $return['json'] = $response->json();
            $return['successful'] = $response->successful();
            $return['failed'] = $response->failed();
            $return['clientError'] = $response->clientError();
            $return['serverError'] = $response->serverError();
        } else {
            $return['body'] = json_encode($response);
            $return['json'] = $response;
            $return['successful'] = 1;
            $return['failed'] = 1;
            $return['clientError'] = 1;
            $return['serverError'] = 1;
        }

        $this->responses[] = $return;

    }

    public function viewBalance($username, $product_id = ''): array
    {
        $return['success'] = false;
        $return['score'] = 0;

        $param = [
            'username' => $username,
            'companyKey' => $this->secretkey,
        ];

        $response = $this->GameCurl($param, 'transaction/iog_get_balance');
        dd($response);

        if ($response['success'] === true) {

            $return['msg'] = 'Complete';
            $return['success'] = true;
            $return['connect'] = true;
            $return['score'] = $response['data']['balance'];

        } else {
            $return['msg'] = 'ไม่สามารถเชื่อมต่อ api ได้';
            $return['connect'] = false;
            $return['success'] = false;
        }

        if ($this->debug) {
            return ['debug' => $this->responses, 'success' => true];
        }

        return $return;
    }

    public function deposit($username, $amount, $product_id): array
    {
        $return['success'] = false;

        $score = $amount;

        if ($score < 0) {
            $return['msg'] = 'เกิดข้อผิดพลาด จำนวนยอดเงินไม่ถูกต้อง';
            if ($this->debug) {
                $this->Debug($return, true);
            }
        } elseif (empty($username) || ! $username || is_null($username)) {
            $return['msg'] = 'เกิดข้อผิดพลาด ไม่พบข้อมูลรหัสสมาชิก';
            if ($this->debug) {
                $this->Debug($return, true);
            }
        } else {
            $transID = 'DP'.date('YmdHis').rand(100, 999);
            $param = [
                'username' => $username,
                'amount' => $score,
                'transactionRef' => $transID,
                'productId' => $product_id,
            ];

            $response = $this->GameCurl($param, 'deposit');

            if ($response['success'] === true) {
                if ($response['data']['status'] == 'SUCCESS') {
                    $return['success'] = true;
                    $return['ref_id'] = $response['data']['txId'];
                    $return['after'] = $response['data']['balance'];
                    $return['before'] = $response['data']['beforeBalance'];

                }
            } else {
                $return['msg'] = 'พบข้อผิดพลาด ลองใหม่ในภายหลัง';
                $return['success'] = false;
            }

        }

        if ($this->debug) {
            return ['debug' => $this->responses, 'success' => true];
        }

        return $return;
    }

    public function withdraw($username, $amount, $product_id): array
    {
        $return['success'] = false;

        $score = $amount;

        if ($score < 1) {
            $return['msg'] = 'เกิดข้อผิดพลาด จำนวนยอดเงินไม่ถูกต้อง';
            if ($this->debug) {
                $this->Debug($return, true);
            }
        } elseif (empty($username)) {
            $return['msg'] = 'เกิดข้อผิดพลาด ไม่พบข้อมูลรหัสสมาชิก';
            if ($this->debug) {
                $this->Debug($return, true);
            }
        } else {

            $transID = 'WD'.date('YmdHis').rand(100, 999);
            $param = [
                'username' => $username,
                'amount' => $score,
                'transactionRef' => $transID,
                'productId' => $product_id,
            ];

            $response = $this->GameCurl($param, 'withdraw');

            if ($response['success'] === true) {
                if ($response['data']['status'] == 'SUCCESS') {
                    $return['success'] = true;
                    $return['ref_id'] = $response['data']['txId'];
                    $return['after'] = $response['data']['balance'];
                    $return['before'] = $response['data']['beforeBalance'];

                }
            } else {
                $return['msg'] = 'พบข้อผิดพลาด ลองใหม่ในภายหลัง';
                $return['success'] = false;
            }

        }

        if ($this->debug) {
            return ['debug' => $this->responses, 'success' => true];
        }

        return $return;
    }

    public function gameList_($product_id): array
    {
        $return['success'] = false;

        $param = ['productId' => $product_id];

        //        dd($product_id);

        $response = $this->GameCurlGet($param, 'seamless/games');
        if ($response['success'] == true) {

            $return['success'] = true;
            $return['msg'] = $response['msg'];
            $return['games'] = $response['data']['games'];

            foreach ($return['games'] as $item) {
                GameListProxy::updateOrCreate(
                    ['product' => $product_id, 'game' => $item['code']]
                );
            }

        } else {
            $return['msg'] = $response['msg'];
            $return['success'] = false;
        }

        //        dd($return);

        return $return;
    }

    public function gameList($product_id): array
    {
        $return['success'] = false;

        $param = ['productId' => $product_id];

        $lotto = ['GAME_LOTTO888', 'GAME_LOTTO90', 'GAME_LOTTO12'];
        $cock = ['GAME_COCK', 'GAME_COCK_W168', 'GAME_COCK_NG', 'GAME_COCK_XCF', 'GAME_COCK_CFWA'];

        //        dd($product_id);

        //        $response = $this->GameCurlGet($param, 'seamless/games');

        //        dd($response);

        if (in_array($product_id, $lotto)) {
            $item = [
                'code' => 'lobby',
                'category' => 'huay',
                'type' => 'lotto',
                'name' => 'Lobby',
                'img' => 'https://user.168csn.com/storage/game_img/lotto_lobby.png',
                'rank' => 0,
            ];
        }

        if (in_array($product_id, $cock)) {
            $item = [
                'code' => 'lobby',
                'category' => 'sport',
                'type' => 'cockfight',
                'name' => 'Lobby',
                'img' => 'https://user.168csn.com/storage/game_img/cockfight_lobby.png',
                'rank' => 0,
            ];
        }

        $return['success'] = true;
        $return['msg'] = 'Success';
        $return['games'] = $item;

        GameListProxy::updateOrCreate(
            ['product' => $product_id, 'code' => $item['code'], 'game' => $item['code']],
            ['category' => $item['category'], 'type' => $item['type'], 'img' => $item['img'], 'name' => $item['name'], 'rank' => $item['rank']]
        );

        //        dd($return);

        return $return;
    }

    public function login($data)
    {
        $pid = Str::upper($data['productId']);
        $return['game'] = $pid;
        $Agent = new Agent;

        $gameid = ['LALIKA', 'AFB1188', 'VIRTUAL_SPORT', 'COCKFIGHT', 'AMBSPORTBOOK', 'SABASPORTS', 'UMBET', 'SBO'];

        if (in_array($pid, $gameid)) {
            $mobile = false;
        } else {
            if ($Agent->isMobile()) {
                $mobile = true;
            } else {
                $mobile = false;
            }
        }
        //        if($pid = 'PGSOFT2'){
        //            $html = true;
        //        }else{
        //            $html = false;
        //        }

        //        $this->betLimit($pid,$data['username']);

        $return['success'] = false;
        $response = [];
        $member = DB::table('members')->select('user_name', 'code')->where('session_id', request()->session()->getId())->first();
        //        dd($member);

        if ($member) {

            if ($member->user_name == $data['username']) {

                if ($pid == 'RELAX') {
                    $session = Str::limit(request()->session()->getId(), 20, '');
                } else {
                    $session = request()->session()->getId();
                }

                $param = [
                    'username' => $data['username'],
                    'game_code' => Str::upper($data['productId']),
                    'keys' => $this->secretkey,
                    'hideBar' => false,
                    'lng' => 'en',
                ];

                //                dd($param);

                //                if($pid = 'PGSOFT2'){
                //                    $response = $this->GameCurlPg($param, 'seamless/logIn');
                //
                //                }else{
                //                    $response = $this->GameCurl($param, 'seamless/logIn');
                //                }

                $response = $this->GameCurl($param, 'wallet/login/single');
                $response['param'] = $param;
                $response['datetime'] = now()->toDateTimeString();
                $path = storage_path('logs/seamless/login_'.now()->format('Y_m_d').'.log');
                //                file_put_contents($path, print_r($param, true), FILE_APPEND);

                //                dd($response);
                //                $path = storage_path('logs/seamless/seamlesslogin_' . now()->format('Y_m_d') . '.log');
                file_put_contents($path, print_r($response, true), FILE_APPEND);

                if ($response['success'] === true && isset($response['respBody']['result'])) {

                    $return['success'] = true;
                    //                        $return['game'] = $pid;
                    $return['url'] = $response['respBody']['result'];

                } else {
                    $return['success'] = false;
                }
            } else {
                $return['success'] = false;
            }
        }

        $return['api'] = $response;

        return $return;
    }

    public function gameLog($data): array
    {
        $return['success'] = false;

        //        $param = [
        //            'username' => $data['username'],
        //            'productId' => $data['productId'],
        //            'startTime' => $data['startTime'],
        //            'endTime' => $data['endTime'],
        //            'offset' => $data['offset'],
        //            'limit' => $data['limit'],
        //        ];

        $param = [
            'productId' => $data['productId'],
            'startTime' => $data['startTime'],
            'endTime' => $data['endTime'],
            'nextId' => $data['nextId'],
        ];

        //        dd($product_id);

        $response = $this->GameCurlGet($param, 'seamless/betTransactionsV2');
        if ($response['success'] === true) {

            $return['success'] = true;
            $return['msg'] = $response['msg'];
            $return['data'] = $response['data']['txns'];

        } else {
            $return['msg'] = $response['msg'];
            $return['success'] = false;
        }

        return $return;
    }

    public function betLimit($product_id, $username)
    {
        $pid = Str::upper($product_id);

        $param = [
            'productId' => $product_id,
            'username' => $username,
            'currency' => 'THB',
        ];

        $response = $this->GameCurlGet($param, 'seamless/betLimitsV2');

        //        $path = storage_path('logs/seamless/betlimit' . now()->format('Y_m_d') . '.log');
        //        file_put_contents($path, print_r($param, true), FILE_APPEND);
        //        file_put_contents($path, print_r($response, true), FILE_APPEND);
    }

    public function freeGame($data)
    {
        //        $pid = Str::upper($product_id);

        $param = [
            'productId' => strtoupper($data['product_id']),
            'player_name' => $data['member_user'],
            'free_game_name' => $data['free_game_name'],
            'expired_date' => $data['expired_date'],
            'bet_amount' => $data['bet_amount'],
            'game_count' => $data['game_count'],
            'game_ids' => $data['game_ids'],
        ];

        $response = $this->GameCurl($param, 'seamless/free-game');
        $path = storage_path('logs/seamless/freegame_'.now()->format('Y_m_d').'.log');
        file_put_contents($path, print_r($param, true), FILE_APPEND);
        file_put_contents($path, print_r($response, true), FILE_APPEND);

        if ($response['success'] === true) {

            if (isset($response['data']['freeGameId'])) {
                $return['success'] = true;
                $return['msg'] = $response['msg'];
                $return['freeGameId'] = $response['data']['freeGameId'];

                $newparam = [
                    'ip' => request()->ip(),
                    'credit_type' => 'D',
                    'balance_before' => 0,
                    'balance_after' => 0,
                    'credit' => 0,
                    'total' => 0,
                    'credit_bonus' => 0,
                    'credit_total' => 0,
                    'credit_before' => 0,
                    'credit_after' => 0,
                    'pro_code' => 0,
                    'bank_code' => 0,
                    'auto' => 'Y',
                    'enable' => 'Y',
                    'user_create' => 'System Auto',
                    'user_update' => 'System Auto',
                    'refer_code' => 0,
                    'refer_table' => 'freegame',
                    'remark' => 'ได้รับ Free Game จำนวน '.$data['game_count'].' ที่ Bet '.$data['bet_amount'].' ค่าย '.$data['product_id'].' เกม '.$data['game_name'],
                    'kind' => 'FREEGAME',
                    'amount' => 0,
                    'amount_balance' => 0,
                    'withdraw_limit' => 0,
                    'withdraw_limit_amount' => 0,
                    'method' => 'D',
                    'gameuser_code' => 0,
                    'member_code' => $data['member_code'],
                ];

                MemberCreditLogProxy::create($newparam);
            } else {
                $return['msg'] = 'ไม่สามารถเพิ่มฟรีเกมได้';
                $return['success'] = false;
            }

        } else {
            $return['msg'] = $response['msg'];
            $return['success'] = false;
        }

        return $return;

    }

    /**
     * Specify Model class name
     *
     * @return mixed
     */
    public function model(): string
    {
        return 'Gametech\Game\Contracts\User';
    }
}
