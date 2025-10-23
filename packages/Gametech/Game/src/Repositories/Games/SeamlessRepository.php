<?php

namespace Gametech\Game\Repositories\Games;

use Gametech\API\Models\GameListProxy;
use Gametech\API\Traits\LogSeamless;
use Gametech\Core\Eloquent\Repository;
use Gametech\Game\Models\GameSeamlessProxy;
use Gametech\Member\Models\MemberCreditLogProxy;
use Gametech\Member\Models\MemberProxy;
use Illuminate\Container\Container as App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Jenssegers\Agent\Agent;

class SeamlessRepository extends Repository
{
    use LogSeamless;

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
        $game = 'seamless';

        $this->method = $method;

        $this->debug = $debug;

        $this->url = config($this->method . '.' . $game . '.apiurl');

        $this->agent = config($this->method . '.' . $game . '.agent');

        $this->agentPass = config($this->method . '.' . $game . '.agent_pass');

        $this->login = config($this->method . '.' . $game . '.login');

        $this->auth = config($this->method . '.' . $game . '.auth');

        $this->passkey = config($this->method . '.' . $game . '.passkey');

        $this->secretkey = config($this->method . '.' . $game . '.secretkey');

        $this->responses = [];

        parent::__construct($app);
    }

    public function GameCurlPg($param, $action)
    {

        $response = rescue(function () use ($param, $action) {

            $url = 'https://test.ambsuperapi.com/' . $action;

            return Http::timeout(10)->withHeaders([
                'Authorization' => 'Basic ' . base64_encode($this->agent . ':' . $this->secretkey),
            ])->withOptions(['debug' => false])->asJson()->post($url, $param);

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
            if ($result['code'] == 0) {
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
        if ($result['success'] == true) {
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

        $param = [
            'username' => $data['username'],
            'productId' => 'JOKER',
        ];

        $response = $this->GameCurl($param, 'seamless/member');

        //        $path = storage_path('logs/seamless/register' . now()->format('Y_m_d') . '.log');
        //        file_put_contents($path, print_r('-- TRANSACTION --', true), FILE_APPEND);
        //        file_put_contents($path, print_r($response, true), FILE_APPEND);
        //        file_put_contents($path, print_r($param, true), FILE_APPEND);

        if ($response['success'] === true) {

            $return['msg'] = 'Complete';
            $return['success'] = true;
            $return['user_name'] = $response['data']['username'];
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

    public function GameCurl($param, $action)
    {

        $response = rescue(function () use ($param, $action) {

            $url = $this->url . $action;

            return Http::timeout(10)->withHeaders([
                'Authorization' => 'Basic ' . base64_encode($this->agent . ':' . $this->secretkey),
            ])->withOptions(['debug' => false])->asJson()->post($url, $param);

        }, function ($e) {

            return false;

        }, true);

        //        if ($this->debug) {
        if ($response !== false) {
            $this->Debug($response);
        } else {
            // debug แบบ custom หรือ log error
            Log::error('Failed to connect or error in request', ['response' => true]);
        }

        //        }

        if ($response === false) {
            //            $result['main'] = false;
            $result['success'] = false;
            $result['msg'] = 'เชื่อมต่อไม่ได้';

            return $result;
        }

        $result = $response->json();

        $result['msg'] = ($result['message'] ?? 'พบปัญหาบางประการ');

        if ($response->successful()) {
            if ($result['code'] == 0) {
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

    public function viewBalance($username, $product_id): array
    {
        $return['success'] = false;
        $return['score'] = 0;

        $param = [
            'username' => $username,
            'productId' => $product_id,
        ];

        $response = $this->GameCurlGet($param, 'balance');

        if ($response['success'] === true) {
            if ($response['data']['status'] == 'SUCCESS') {
                $return['msg'] = 'Complete';
                $return['success'] = true;
                $return['connect'] = true;
                $return['score'] = $response['balance'];

            } else {
                $return['msg'] = 'เกิดข้อผิดพลาด';
                $return['connect'] = true;
                $return['success'] = false;
            }
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

    public function GameCurlGet($param, $action)
    {

        $response = rescue(function () use ($param, $action) {

            $url = $this->url . $action;

            return Http::timeout(15)->withHeaders([
                'Authorization' => 'Basic ' . base64_encode($this->agent . ':' . $this->secretkey),
            ])->asJson()->get($url, $param);

        }, function ($e) {

            return false;

        }, true);

        //        if ($this->debug) {
        if ($response !== false) {
            $this->Debug($response);
        } else {
            Log::error('Failed to connect or error in request', ['response' => true]);
        }
        //        }

        if ($response === false) {
            //            $result['main'] = false;
            $result['success'] = false;
            $result['msg'] = 'เชื่อมต่อไม่ได้';

            return $result;
        }

        $result = $response->json();

        //        dd($result);

        $result['msg'] = ($result['message'] ?? 'พบปัญหาบางประการ');

        if ($response->successful()) {
            if ($result['code'] == 0) {
                $result['success'] = true;
            } else {
                $result['success'] = false;
            }
        } else {
            $result['success'] = false;
        }

        return $result;

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
        } elseif (empty($username) || !$username || is_null($username)) {
            $return['msg'] = 'เกิดข้อผิดพลาด ไม่พบข้อมูลรหัสสมาชิก';
            if ($this->debug) {
                $this->Debug($return, true);
            }
        } else {
            $transID = 'DP' . date('YmdHis') . rand(100, 999);
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

            $transID = 'WD' . date('YmdHis') . rand(100, 999);
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

    public function gameList_aa($product_id): array
    {

        $param = ['productId' => $product_id];
        $response = $this->GameCurlGet($param, 'seamless/games');

        if ($response['success'] !== true) {
            return [
                'success' => false,
                'msg' => $response['msg'] ?? 'Unknown error',
                'games' => []
            ];
        }

        $games = $response['data']['games'];

        $path = storage_path('logs/seamless/gamelist' . now()->format('Y_m_d') . '.log');
        file_put_contents($path, print_r($param, true), FILE_APPEND);
        file_put_contents($path, print_r($games, true), FILE_APPEND);

        foreach ($games as $item) {
            if ($product_id === 'COCKFIGHT' || $product_id === 'AOG') {
                $item['category'] = 'COCK';
            }

            if ($product_id === 'AMBPOKER') {
                $item['category'] = 'POKER';
            }

            if ($product_id === 'KINGMAKER') {
                $item['category'] = 'CARD';
            }

            GameListProxy::updateOrCreate(
                ['product' => $product_id, 'code' => $item['code']],
                [
                    'category' => $item['category'],
                    'type' => $item['type'] ?? 'SLOT',
                    'img' => $item['img'],
                    'name' => $item['name'],
                    'rank' => $item['rank'],
                    'game' => $item['code']
                ]
            );
        }

        return [
            'success' => true,
            'msg' => $response['msg'],
            'games' => $games
        ];

    }

    public function gameList__($product_id): array
    {
        $product_id = strtoupper(trim($product_id));
        $param = ['productId' => $product_id];

        $response = $this->GameCurlGet($param, 'seamless/games');

        if (($response['success'] ?? false) !== true) {
            return ['success' => false, 'msg' => $response['msg'] ?? 'Unknown error', 'games' => []];
        }

        $games = (array)($response['data']['games'] ?? []);

        foreach ($games as &$item) {
            if ($product_id === 'COCKFIGHT' || $product_id === 'AOG') {
                $item['category'] = 'COCK';
            }
            if ($product_id === 'AMBPOKER') {
                $item['category'] = 'POKER';
            }
            if ($product_id === 'KINGMAKER') {
                $item['category'] = 'CARD';
            }
            $item['type'] = $item['type'] ?? 'SLOT';
        }

        /** @var \Gametech\Game\Services\GameListSyncService $sync */
        $sync = app(\Gametech\Game\Services\GameListSyncService::class);
        $sync->syncFromApi($product_id, $games, ['disable_missing' => true]);

        return ['success' => true, 'msg' => 'OK', 'games' => $games];
    }

    public function gameList(string $productId): array
    {
        $productId = strtoupper(trim($productId));

        // ===== API =====
        $param = ['productId' => $productId];
        $response = $this->GameCurlGet($param, 'seamless/games');

        $ok = is_array($response)
            && ($response['success'] ?? false) === true
            && isset($response['data']['games'])
            && is_array($response['data']['games']);

        if (!$ok) {
            return [
                'success' => false,
                'msg' => $response['msg'] ?? 'Unknown error',
                'games' => [],
            ];
        }

        $games = $response['data']['games'];

        // ===== normalize =====
        $catMap = [
            'COCKFIGHT' => 'COCK',
            'AOG' => 'COCK',
            'AMBPOKER' => 'POKER',
            'KINGMAKER' => 'CARD',
        ];

        foreach ($games as &$item) {
            $code = (string)($item['code'] ?? '');
            $item['code'] = $code;
            $item['name'] = (string)($item['name'] ?? $code);
            $item['img'] = $item['img'] ?? null;
            $item['type'] = (string)($item['type'] ?? 'SLOT');
            $item['rank'] = is_numeric($item['rank'] ?? null) ? (int)$item['rank'] : 0;
            $item['category'] = $catMap[$productId] ?? ($item['category'] ?? 'SLOT');
        }
        unset($item);

        $now = now();
        $nowMs = (int)round(microtime(true) * 1000);
        $rows = array_map(function ($it) use ($productId, $now) {
            return [
                'product' => $productId,
                'code' => $it['code'],
                'category' => $it['category'],
                'type' => $it['type'],
                'img' => $it['img'],
                'name' => $it['name'],
                'rank' => $it['rank'],
                'game' => $it['code'],
                'updated_at' => $now,
                'created_at' => $now,
            ];
        }, $games);

        // ===== Mongo bulk upsert =====
        if (!empty($rows)) {
            $model = GameListProxy::query()->getModel();
            $connName = $model->getConnectionName() ?: config('database.default');
            $table = $model->getTable();

            \Illuminate\Support\Facades\DB::connection($connName)
                ->collection($table)
                ->raw(function ($collection) use ($rows, $nowMs) {
                    $ops = [];

                    foreach ($rows as $r) {
                        $ops[] = [
                            'updateOne' => [
                                ['product' => $r['product'], 'code' => $r['code']],
                                [
                                    '$set' => [
                                        'category' => $r['category'],
                                        'type' => $r['type'],
                                        'img' => $r['img'],
                                        'name' => $r['name'],
                                        'rank' => $r['rank'],
                                        'game' => $r['game'],
                                        'updated_at' => new \MongoDB\BSON\UTCDateTime($nowMs),
                                    ],
                                    '$setOnInsert' => [
                                        'product' => $r['product'],
                                        'code' => $r['code'],
                                        'enable' => true,
                                        'click' => 0,
                                        'created_at' => new \MongoDB\BSON\UTCDateTime($nowMs),
                                    ],
                                ],
                                ['upsert' => true],
                            ],
                        ];
                    }

                    if ($ops) {
                        $collection->bulkWrite($ops, ['ordered' => false]);
                    }
                });
        }

        // ===== Soft disable removed games (enable=false) =====
        $codes = array_values(array_unique(array_map(static function ($g) {
            return (string)($g['code'] ?? '');
        }, $games)));

        $model = GameListProxy::query()->getModel();
        $connName = $model->getConnectionName() ?: config('database.default');
        $table = $model->getTable();

        \Illuminate\Support\Facades\DB::connection($connName)
            ->collection($table)
            ->raw(function ($collection) use ($productId, $codes, $nowMs) {
                $filter = ['product' => $productId];
                if (!empty($codes)) {
                    $filter['code'] = ['$nin' => $codes];
                }
                $filter = array_merge($filter, ['enable' => ['$ne' => false]]);

                $collection->updateMany(
                    $filter,
                    [
                        '$set' => [
                            'enable' => false,
                            'updated_at' => new \MongoDB\BSON\UTCDateTime($nowMs),
                        ],
                    ]
                );
            });

        return [
            'success' => true,
            'msg' => $response['msg'] ?? 'OK',
            'games' => $games,
        ];
    }


    public function login($data)
    {
        $pid = Str::upper($data['productId']);
        $return['game'] = $pid;
        $Agent = new Agent;

        $lang = app()->getLocale();
        if ($lang !== 'th') {
            $lang = 'en';
        }

        $setting = GameSeamlessProxy::where('id', $pid)->first();

        //        $gameid = [ 'LALIKA' , 'AFB1188' , 'VIRTUAL_SPORT' , 'COCKFIGHT' , 'AMBSPORTBOOK', 'SABASPORTS' , 'UMBET' , 'SBO'];

        if ($Agent->isMobile()) {
            if ($setting->mobile == 'Y') {
                $mobile = true;
            } else {
                $mobile = false;
            }

        } else {
            $mobile = false;
        }


        $return['success'] = false;
        $response = [];
        $member = MemberProxy::select('user_name', 'code', 'balance')->where('session_id', request()->session()->getId())->first();
//        $member = DB::table('members')->select('user_name', 'code', 'balance')->where('session_id', request()->session()->getId())->first();
        //        dd($member);

        if ($member) {

            if ($member->user_name == $data['username']) {

                if ($pid == 'RELAX') {
                    $session = Str::limit(request()->session()->getId(), 20, '');
                } else {
                    $session = request()->session()->getId();
                }

                if ($setting->limit != '') {
                    $param = [
                        'username' => $data['username'],
                        'productId' => $pid,
                        'gameCode' => $data['gameCode'],
                        'isMobileLogin' => $mobile,
                        'currency' => 'THB',
                        'language' => $lang,
                        'limit' => (int)$setting->limit,
                        'sessionToken' => $session,
                    ];
                } else {

                    $param = [
                        'username' => $data['username'],
                        'productId' => $pid,
                        'gameCode' => $data['gameCode'],
                        'isMobileLogin' => $mobile,
                        'currency' => 'THB',
                        'language' => $lang,
                        'sessionToken' => $session,
                    ];
                }
                //                dd($param);

                //                if($pid = 'PGSOFT2'){
                //                    $response = $this->GameCurlPg($param, 'seamless/logIn');
                //
                //                }else{
                //                    $response = $this->GameCurl($param, 'seamless/logIn');
                //                }

                $response = $this->GameCurl($param, 'seamless/logIn');
                $response['param'] = $param;
                $response['datetime'] = now()->toDateTimeString();
                $response['api'] = $this->responses;
                $path = storage_path('logs/seamless/login_' . now()->format('Y_m_d') . '.log');
                //                file_put_contents($path, print_r($param, true), FILE_APPEND);

                //                dd($response);
                //                $path = storage_path('logs/seamless/seamlesslogin_' . now()->format('Y_m_d') . '.log');
                file_put_contents($path, print_r($response, true), FILE_APPEND);
                //                file_put_contents($path, print_r($this->responses, true), FILE_APPEND);

                if ($response['success'] === true && isset($response['data']['url'])) {

                    if (isset($response['data']['url']['errors'])) {
                        $return['success'] = false;
                        $return['url'] = '';
                    } else {
                        $userId = $member->user_name;
                        $gameId = $data['gameCode'];
                        $productId = $pid;
                        if ($productId == 'PGSOFT2') {
                            $productId = 'PGSOFT';
                        }

                        // LOG: extracted values
//                        Log::info('[LOGIN] GAME', [
//                            'userId' => $userId,
//                            'gameId' => $gameId,
//                            'productId' => $productId,
//                        ]);

                        $gameUser = app(\Gametech\Game\Repositories\GameUserRepository::class);

                        // บันทึกสถานะใหม่ (หรืออัปเดตเวลา)
                        Redis::connection('game')->setex("user_game_status:{$userId}", 600, json_encode([
                            'gameCode' => $gameId,
                            'productId' => $productId,
                            'last_active_at' => now()->toDateTimeString(),
                        ]));

                        $gameUser->incrementClick($pid, $data['gameCode']);
//                        GameListProxy::where('code', $data['gameCode'])->where('product', Str::upper($data['productId']))->increment('click', 1);
                        $member->session_page = $session;
                        $member->saveQuietly();

                        $param = [
                            'id' => time(),
                            'roundId' => time(),
                            'playInfo' => $gameUser->findGameName($pid, $data['gameCode']),
                            'status' => 'LOGIN',
                        ];
                        LogSeamless::log($pid, $member->user_name, $param, $member->balance, $member->balance);
                        $return['success'] = true;
                        //                        $return['game'] = $pid;
                        $return['url'] = $response['data']['url'];
                    }

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

    public function betLimit($product_id)
    {
        $pid = Str::upper($product_id);
        $betLimit = [];
        $param = [
            'productId' => $product_id,
        ];

        $response = $this->GameCurlGet($param, 'seamless/betLimitsV2');
        //        dd($response);
        if ($response['code'] == 0) {
            $betLimit = $response['data'][0]['BetLimit'];
            //            foreach($betLimits as $item){
            //                $betLimit[] = $item;
            //            }
        }

        //        dd($betLimit);

        //        $path = storage_path('logs/seamless/betlimit' . now()->format('Y_m_d') . '.log');
        //        file_put_contents($path, print_r($param, true), FILE_APPEND);
        //        file_put_contents($path, print_r($response, true), FILE_APPEND);

        return $betLimit;
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
        $path = storage_path('logs/seamless/freegame_' . now()->format('Y_m_d') . '.log');
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
                    'remark' => 'ได้รับ Free Game จำนวน ' . $data['game_count'] . ' ที่ Bet ' . $data['bet_amount'] . ' ค่าย ' . $data['product_id'] . ' เกม ' . $data['game_name'],
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
