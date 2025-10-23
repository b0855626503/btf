<?php

namespace Gametech\Game\Repositories\Games;

use Carbon\Carbon;
use Gametech\API\Models\GameListProxy;
use Gametech\Core\Eloquent\Repository;
use Gametech\Member\Models\MemberProxy;
use GuzzleHttp\Client;
use Illuminate\Container\Container as App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Jenssegers\Agent\Agent;

class GoodgameRepository extends Repository
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
    protected $gamesub;

    protected $sign;

    protected $token = '';

    protected $expire = '';

    protected $membertoken = '';

    public function __construct($method, $debug, App $app)
    {
        $game = 'goodgame';

        $this->method = $method;

        $this->debug = $debug;

        $this->url = config($this->method . '.' . $game . '.apiurl');

        $this->agent = config($this->method . '.' . $game . '.agent');

        $this->agentPass = config($this->method . '.' . $game . '.agentPass');

        $this->login = config($this->method . '.' . $game . '.login');

        $this->auth = config($this->method . '.' . $game . '.auth');

        $this->passkey = config($this->method . '.' . $game . '.passkey');

        $this->secretkey = config($this->method . '.' . $game . '.secretkey');

        $this->sign = '';

        $this->responses = [];

        parent::__construct($app);
    }

    public function GameCurlGet_($param, $action)
    {

//        dd($this->url . $action);

        $response = rescue(function () use ($param, $action) {

            $url = $this->url . $action;

            return Http::timeout(15)->withOptions(['debug' => false])->asJson()->get($url, $param);


        }, function ($e) {

            return false;

        }, false);

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

//        dd($result);

        if ($response->successful()) {
            if (is_null($result)) {
                $result['msg'] = 'พบปัญหาบางประการ';
                $result['success'] = false;
            } else {
                $result['msg'] = ($result['message'] ?? 'พบปัญหาบางประการ');
                if ($result['code'] == 0) {
                    $result['success'] = true;
                } else {
                    $result['success'] = false;
                }

            }
        } else {
            $result['success'] = false;
        }


        return $result;

    }

    public function Debug($response, $custom = false)
    {

        if (!$custom) {
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
        $return['account'] = 'a123456';

        return $return;
    }

    public function addUser($username, $data): array
    {
        $return['success'] = false;


        $param = [
            'brandcode' => $this->login,
            'username' => $data['user_name'],
            'password' => $username,
            'currencycode' => 'THB',
            'ip' => request()->ip(),
            'bankid' => 0,
            'referralcode' => ''
        ];

        if (!$this->token) {
            $this->requestNewToken();
        }

//dd($this->token);
        $response = $this->GameCurl($param, 'ggapi/register');

        $path = storage_path('logs/goodgame/register' . now()->format('Y_m_d') . '.log');
        file_put_contents($path, print_r('-- TRANSACTION --', true), FILE_APPEND);
        file_put_contents($path, print_r($response, true), FILE_APPEND);
        file_put_contents($path, print_r($param, true), FILE_APPEND);


        if ($response['success'] === true) {

            $return['msg'] = 'Complete';
            $return['success'] = true;
            $return['user_name'] = $data['user_name'];
            $return['user_pass'] = $username;

        } else {
            $return['msg'] = $response['msg'];
            $return['success'] = false;

        }


        if ($this->debug) {
            return ['debug' => $this->responses, 'success' => true];
        }
        return $return;
    }

    public function requestNewToken()
    {


        if (Cache::has('goodgame')) {
            $this->token = Cache::get('goodgame');
        } else {
            $this->token = $this->getToken();
        }
    }

    public function getToken()
    {

        $param = [
            'brandcode' => $this->login,
            'agent' => $this->agent,
            'agentKey' => $this->agentPass
        ];


        $response = $this->GameAuth($param, 'ggapi/gettoken');

        if ($response['success'] === true) {

            $expireCarbon = Carbon::parse($response['expiredtime']);
            Cache::put('goodgame', $response['token'], $expireCarbon);
            return $response['token'];

        }else{

            return $this->token;

        }
    }

    public function GameAuth($param, $action)
    {

        $response = rescue(function () use ($param, $action) {

            $url = $this->url . $action;

            return Http::timeout(15)->withOptions(['debug' => false])->asform()->post($url, $param);


        }, function ($e) {

            return false;

        }, true);

//        if ($this->debug) {
        $this->Debug($response);
//        }
//
//        return $this->responses;

        $param['date'] = now()->toDateTimeString();
        $path = storage_path('logs/goodgame/curlauth' . now()->format('Y_m_d') . '.log');
        file_put_contents($path, print_r('-- curl --', true), FILE_APPEND);
        file_put_contents($path, print_r($this->responses, true), FILE_APPEND);
        file_put_contents($path, print_r($param, true), FILE_APPEND);

        if ($response === false) {
//            $result['main'] = false;
            $result['success'] = false;
            $result['msg'] = 'เชื่อมต่อไม่ได้';
            return $result;
        }



        $result = $response->json();




//        $param['date'] = now()->toDateTimeString();
//        $path = storage_path('logs/seamless/curlauth' . now()->format('Y_m_d') . '.log');
//        file_put_contents($path, print_r('-- curl --', true), FILE_APPEND);
//        file_put_contents($path, print_r($result, true), FILE_APPEND);
//        file_put_contents($path, print_r($param, true), FILE_APPEND);
//        file_put_contents($path, print_r($param, true), FILE_APPEND);

        $result['msg'] = ($result['message'] ?? 'พบปัญหาบางประการ');

        if ($response->successful()) {

            if ($result['success'] === true) {
                $result['success'] = true;
            } else {
                $result['success'] = false;
            }

        } else {
            $result['success'] = false;
        }

        return $result;

    }

    public function GameCurl($param, $action)
    {

        $response = rescue(function () use ($param, $action) {

            $url = $this->url . $action;

            return Http::timeout(15)->withOptions(['debug' => false])->withToken($this->token)->asForm()->post($url, $param);


        }, function ($e) {

            return false;

        }, true);

//        if ($this->debug) {
        $this->Debug($response);
//        }

        $param['date'] = now()->toDateTimeString();
        $path = storage_path('logs/goodgame/curl' . now()->format('Y_m_d') . '.log');
        file_put_contents($path, print_r('-- curl --', true), FILE_APPEND);
        file_put_contents($path, print_r($this->responses, true), FILE_APPEND);
        file_put_contents($path, print_r($param, true), FILE_APPEND);

        if ($response === false) {
//            $result['main'] = false;
            $result['success'] = false;
            $result['msg'] = 'เชื่อมต่อไม่ได้';
            return $result;
        }


        $result = $response->json();

//        $param['date'] = $datenow;
        $path = storage_path('logs/goodgame/curl' . now()->format('Y_m_d') . '.log');
        file_put_contents($path, print_r('-- curl --', true), FILE_APPEND);
        file_put_contents($path, print_r($result, true), FILE_APPEND);
        file_put_contents($path, print_r($param, true), FILE_APPEND);
//        file_put_contents($path, print_r($param, true), FILE_APPEND);

        $result['msg'] = ($result['message'] ?? 'พบปัญหาบางประการ');

        if ($response->successful()) {

            if ($result['success'] === true) {
                $result['success'] = true;
            } else {
                $result['success'] = false;
            }

        } else {
            $result['success'] = false;
        }

        return $result;

    }

    public function changePass($data): array
    {
        $return['success'] = false;

        $param = [
            'Method' => 'SP',
            'Password' => $data['user_pass'],
            'Timestamp' => time(),
            'Username' => $data['user_name']
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

    public function viewBalance($username, $product_id=''): array
    {
        if (!$this->token) {
            $this->requestNewToken();
        }
        $return['success'] = false;
        $return['score'] = 0;

        $param = [
            'username' => $username,
            'brandcode' => $this->login,
            'withbank' => false
        ];


        $response = $this->GameCurl($param, 'ggapi/getmemberinfo');

        if ($response['success'] === true) {
            $return['msg'] = 'Complete';
            $return['success'] = true;
            $return['connect'] = true;
            $return['score'] = $response['balance'];
        } else {
            $return['msg'] = 'ไม่สามารถเชื่อมต่อ api ได้';
            $return['connect'] = false;
            $return['success'] = false;
        }

//        dd($return);

        if ($this->debug) {
            return ['debug' => $this->responses, 'success' => true];
        }

        return $return;
    }

    public function deposit($username, $amount, $product_id=''): array
    {

        if (!$this->token) {
            $this->requestNewToken();
        }

        $return['success'] = false;

        $score = $amount;

        if ($score < 0) {
            $return['msg'] = "เกิดข้อผิดพลาด จำนวนยอดเงินไม่ถูกต้อง";
            if ($this->debug) {
                $this->Debug($return, true);
            }
        } elseif (empty($username) || !$username || is_null($username)) {
            $return['msg'] = "เกิดข้อผิดพลาด ไม่พบข้อมูลรหัสสมาชิก";
            if ($this->debug) {
                $this->Debug($return, true);
            }
        } else {
            $transID = "DP" . date('YmdHis') . rand(100, 999);
            $param = [
                'brandcode' => $this->login,
                'username' => $username,
                'amount' => $score,
                'agent' => $this->agent,
                'ip' => request()->ip()
            ];


            $response = $this->GameCurl($param, 'ggapi/deposit');

            if ($response['success'] === true) {
                $return['success'] = true;
                $return['ref_id'] = $transID;
                $return['after'] = $response['balance'];
                $return['before'] = $response['balance'] - $score;
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

    public function withdraw($username, $amount, $product_id=''): array
    {

        if (!$this->token) {
            $this->requestNewToken();
        }

        $return['success'] = false;


        $score = $amount;

        if ($score < 1) {
            $return['msg'] = "เกิดข้อผิดพลาด จำนวนยอดเงินไม่ถูกต้อง";
            if ($this->debug) {
                $this->Debug($return, true);
            }
        } elseif (empty($username)) {
            $return['msg'] = "เกิดข้อผิดพลาด ไม่พบข้อมูลรหัสสมาชิก";
            if ($this->debug) {
                $this->Debug($return, true);
            }
        } else {

            $transID = "WD" . date('YmdHis') . rand(100, 999);
            $param = [
                'brandcode' => $this->login,
                'username' => $username,
                'amount' => $score,
                'agent' => $this->agent,
                'ip' => request()->ip()
            ];

            $response = $this->GameCurl($param, 'ggapi/withdrawal');

            if ($response['success'] === true) {
                $return['success'] = true;
                $return['ref_id'] = $transID;
                $return['after'] = $response['balance'];
                $return['before'] = $response['balance'] + $score;
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

    public function GameCurlGet($param, $action)
    {

//        dd($this->url . $action);


        $response = rescue(function () use ($param, $action) {

            $url = $this->url . $action;

            return Http::timeout(15)->withOptions(['debug' => false])->withHeaders(['x-sign' => $this->sign])->asJson()->post($url, $param);


        }, function ($e) {

            return false;

        }, false);

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
//        dd($result);

        if ($response->successful()) {
            $result['success'] = true;
        } else {
            $result['success'] = false;
        }


        return $result;

    }

    public function gameList($product_id): array
    {
        $return['success'] = false;

        if (config('app.user_url') === '') {
            $baseurl = (is_null(config('app.user_domain_url')) ? config('app.domain_url') : config('app.user_domain_url'));
        } else {
            $baseurl = config('app.user_url') . '.' . (is_null(config('app.user_domain_url')) ? config('app.domain_url') : config('app.user_domain_url'));
        }




        $param = [
            'brandcode' => $this->login,
            'domainname' => $baseurl,
            'providercode'=> strtoupper($product_id),
            'currencycode' => 'THB',
        ];

        if (!$this->token) {
            $this->requestNewToken();
        }

        $response = $this->GameCurl($param, 'ggapi/gamelist');

//        dd($response);

        if ($response['success'] === true) {

            $return['success'] = true;
            $return['msg'] = 'Success';
            if($product_id === ''){
                $games = $response['ProviderData'];
                $game = [];
                foreach ($games as $i => $items) {

                    foreach($items['ProviderList'] as $k => $item){
                        $game[strtolower($items['CategoryName'])][$k]['id'] = $item['ProviderCode'];
                        $game[strtolower($items['CategoryName'])][$k]['name'] = $item['ProviderName'];
                        $game[strtolower($items['CategoryName'])][$k]['filepic'] = $item['ProviderLogo'];
                    }
                }
                $return['games'] = $game;
            }else{
                $games = $response['GameList'];
                $game = [];
                foreach ($games as $i => $items) {
                    $game[$i]['code'] = $items['GameCode'];
                    $game[$i]['name'] = $items['GameName'];
                    $game[$i]['provider'] = $items['ProviderCode'];
                    $game[$i]['img'] = $items['GameLogo'];
                }
                $return['games'] = $game;
            }


        } else {
            $return['msg'] = $response['msg'];
            $return['success'] = false;
        }

//        dd($return);

        return $return;
    }

    public function gameListPC($product_id): array
    {
        $return['success'] = false;

        if (config('app.user_url') === '') {
            $baseurl = (is_null(config('app.user_domain_url')) ? config('app.domain_url') : config('app.user_domain_url'));
        } else {
            $baseurl = config('app.user_url') . '.' . (is_null(config('app.user_domain_url')) ? config('app.domain_url') : config('app.user_domain_url'));
        }

        $param = [
            'token' => $this->secretkey,
            'productCode' => 'PRAGMATIC',
            'urlWebsite' => $baseurl,
        ];

//        dd($product_id);

        $response = $this->GameCurlGet($param, 'seamless/games');

//        dd($response);

        if ($response['success'] === true) {

            $return['success'] = true;
            $return['msg'] = $response['msg'];
            $return['games'] = $response['data']['games'];

            foreach ($return['games'] as $item) {

//                GameListProxy::firstOrCreate(
//                    ['product' => $product_id, 'code' => $item['code'] , 'game' => $item['code']],
//                    ['category' => $item['category'], 'type' => $item['type'], 'img' => $item['img'], 'name' => $item['name'], 'rank' => $item['rank']]
//                );

                GameListProxy::updateOrCreate(
                    ['product' => 'PRAGMATIC', 'code' => $item['gameCode'], 'game' => $item['gameCode']],
                    ['category' => $item['gameType'], 'type' => $item['gameType'], 'img' => $item['imgUrl'], 'name' => $item['gameName'], 'rank' => $item['mode']]
                );
            }


        } else {
            $return['msg'] = $response['msg'];
            $return['success'] = false;
        }

//        dd($return);

        return $return;
    }


    public function login($data)
    {
        $Agent = new Agent();
        if ($Agent->isMobile()) {
            $mobile = true;
        } else {
            $mobile = false;
        }
        $return['success'] = false;



        $param = [
            'brandcode' => $this->login,
            'username' => $data['username'],
            'password' => $data['password'],
            'ip' => request()->ip(),
            'language' => 'en-US'
        ];

        if (!$this->token) {
            $this->requestNewToken();
        }

        if(!$this->membertoken){
            if (session()->has('membertoken')) {
                $this->membertoken = session()->get('membertoken');
            } else {
                $response = $this->GameCurl($param, 'ggapi/login');

                if ($response['success'] === true) {
                    $this->membertoken = $response['token'];
                    session(['membertoken' => $response['token']]);
                    session(['goodgame_url' => $response['membersiteurl']]);
                    $return['success'] = true;
                }
            }
        }


        if($data['gamelist'] === 'Y'){
            return $this->loginlist($data);
        }

        if (session()->has('goodgame_url')) {
            $return['success'] = true;
            $return['url'] = session()->get('goodgame_url');
        }

//        $return['api'] = $response;

        return $return;
    }

    public function loginlist($data)
    {
        $Agent = new Agent();
        if ($Agent->isMobile()) {
            $mobile = true;
        } else {
            $mobile = false;
        }
        $return['success'] = false;



        $param = [
            'membertoken' => $this->membertoken,
            'providercode' => $data['productId'],
            'gamecode' => $data['gameCode'],
            'ismobile' => $mobile,
            'returnurl' => '',
            'language' => 'th-TH'
        ];


        $response = $this->GameCurl($param, 'ggapi/launchgame');

        if ($response['success'] === true) {

            $return['success'] = true;
            $return['url'] = $response['url'];

        }






        $return['api'] = $response;

//        dd($return);

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
            'nextId' => $data['nextId']
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

    /**
     * Specify Model class name
     *
     * @return mixed
     */
    function model(): string
    {
        return 'Gametech\Game\Contracts\User';
    }
}
