<?php

namespace Gametech\Auto\Console\Commands;

use Gametech\API\Models\GameLogProxy;
use Gametech\Auto\Jobs\AutoPayOutNormal as AutoPayOutNormalJob;
use Gametech\Auto\Jobs\AutoPayOutSeamless as AutoPayOutSeamlessJob;
use Gametech\Auto\Jobs\NewMemberCashback as NewMemberCashbackJob;
use Gametech\Member\Models\MemberWinloseProxy;
use Gametech\Payment\Models\BankAccount;
use Gametech\Payment\Models\Withdraw;
use Gametech\Payment\Models\WithdrawSeamless;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckWinlose extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'winlose:start {date?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get Transaction and insert to Bank Payment By Bank Account';

    protected $config;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->config = core()->getConfigData();
        parent::__construct();

    }

    /**
     * Execute the console command.
     *
     * @return int
     */

    public function handle()
    {
        $startdate = $this->argument('date');

        if (empty($startdate)) {
            $startdate = now()->subDays(1)->toDateString();
        }

        $this->info($startdate);
        $this->info('Request Common Flow Game');

        $lists = DB::connection('mongodb')->collection('gamelog')->where('response', 'in')
            ->where('method', 'betsub')
            ->where('con_3', 'OPEN')
            ->where('con_4', 'regexp', '/^settle_*/')
            ->where('date_create', 'regexp', '/^' . $startdate . ' */')->orderBy('created_at');

        $bar = $this->output->createProgressBar($lists->count());
        $bar->start();

        $lists->chunk(1000, function ($itemlist) use ($bar) {
            $items = [];
            foreach ($itemlist as $item) {

                $items[] = [
                    'member_user' => $item['game_user'],
                    'company' => $item['company'],
                    'method' => ($item['method'] == 'betsub' ? 'BET' : 'SETTLE'),
                    'roundid' => $item['con_2'],
                    'amount' => $item['amount'],
                    'date_event' => $item['date_create'],
                ];

                $bar->advance();
            }

            MemberWinloseProxy::insert(collect($items)->toArray());
        });

        $lists = DB::connection('mongodb')->collection('gamelog')->where('response', 'in')
            ->where('method', 'paysub')
            ->whereNull('con_4')
            ->where('date_create', 'regexp', '/^' . $startdate . ' */')->orderBy('created_at');
        $bar = $this->output->createProgressBar($lists->count());
        $bar->start();

        $lists->chunk(1000, function ($itemlist) use ($bar) {
            $items = [];
            foreach ($itemlist as $item) {

                $items[] = [
                    'member_user' => $item['game_user'],
                    'company' => $item['company'],
                    'method' => ($item['method'] == 'betsub' ? 'BET' : 'SETTLE'),
                    'roundid' => $item['con_2'],
                    'amount' => $item['amount'],
                    'date_event' => $item['date_create'],
                ];

                $bar->advance();
            }

            MemberWinloseProxy::insert(collect($items)->toArray());
        });

    }

    public function handle2()
    {
        $startdate = $this->argument('date');

        if (empty($startdate)) {
            $startdate = now()->subDays(1)->toDateString();
        }

        $this->info($startdate);

        $lists = DB::connection('mongodb')->collection('gamelog')->where('response', 'in')
            ->where('method', 'betsub')
//            ->where(function($query) {
//                $query->where('con_3' , 'OPEN')
//                    ->where('con_4', 'regexp', '/^settle_*/');
//            })
            ->whereRaw(['$where' => "this.con_1 == this.con_3"])
//            ->whereColumn('con_2','con_3')
            ->whereNull('con_4')
//            ->orWhere(function ($query) {
//                return $query->where('con_3', 'OPEN')
//                    ->where('con_4', 'regexp', '/^settle_*/');
//            })
//            ->where(function($query) {
//                $query->whereNotIn('con_3', ['WAITING','OPEN'])
//                    ->whereNull('con_4');
//            })
//            ->where('con_3', '!=' , 'WAITING')
//            ->where('con_4', 'regexp', '/^settle_*/')
//            ->orWhere('con_4',null)
            ->where('date_create', 'regexp', '/^' . $startdate . ' */')->orderBy('created_at');

        $bar = $this->output->createProgressBar($lists->count());
        $bar->start();

        $lists->chunk(1000, function ($itemlist) use ($bar) {
            foreach ($itemlist as $item) {

                $log = MemberWinloseProxy::create([
                    'member_user' => $item['game_user'],
                    'company' => $item['company'],
                    'method' => ($item['method'] == 'betsub' ? 'BET' : 'SETTLE'),
                    'roundid' => $item['con_2'],
                    'amount' => $item['amount'],
                    'date_event' => $item['date_create'],
                ]);
                $bar->advance();
            }
        });

        $lists = DB::connection('mongodb')->collection('gamelog')->where('response', 'in')
            ->where('method', 'betsub')
//            ->where(function($query) {
//                $query->where('con_3' , 'OPEN')
//                    ->where('con_4', 'regexp', '/^settle_*/');
//            })
            ->whereRaw(['$where' => "this.con_2 == this.con_3"])
//            ->whereColumn('con_2','con_3')
            ->where('con_4', 'regexp', '/^settle_*/')
//            ->orWhere(function ($query) {
//                return $query->where('con_3', 'OPEN')
//                    ->where('con_4', 'regexp', '/^settle_*/');
//            })
//            ->where(function($query) {
//                $query->whereNotIn('con_3', ['WAITING','OPEN'])
//                    ->whereNull('con_4');
//            })
//            ->where('con_3', '!=' , 'WAITING')
//            ->where('con_4', 'regexp', '/^settle_*/')
//            ->orWhere('con_4',null)
            ->where('date_create', 'regexp', '/^' . $startdate . ' */')->orderBy('created_at');

        $bar = $this->output->createProgressBar($lists->count());
        $bar->start();

        $lists->chunk(1000, function ($itemlist) use ($bar) {
            foreach ($itemlist as $item) {

                $log = MemberWinloseProxy::create([
                    'member_user' => $item['game_user'],
                    'company' => $item['company'],
                    'method' => ($item['method'] == 'betsub' ? 'BET' : 'SETTLE'),
                    'roundid' => $item['con_2'],
                    'amount' => $item['amount'],
                    'date_event' => $item['date_create'],
                ]);
                $bar->advance();
            }
        });


        $lists = DB::connection('mongodb')->collection('gamelog')->where('response', 'in')
            ->where('method', 'betsub')
//            ->where(function($query) {
//                $query->where('con_3' , 'OPEN')
//                    ->where('con_4', 'regexp', '/^settle_*/');
//            })
            ->whereRaw(['$where' => "this.con_2 == this.con_3"])
//            ->whereColumn('con_2','con_3')
            ->whereNull('con_4')
//            ->orWhere(function ($query) {
//                return $query->where('con_3', 'OPEN')
//                    ->where('con_4', 'regexp', '/^settle_*/');
//            })
//            ->where(function($query) {
//                $query->whereNotIn('con_3', ['WAITING','OPEN'])
//                    ->whereNull('con_4');
//            })
//            ->where('con_3', '!=' , 'WAITING')
//            ->where('con_4', 'regexp', '/^settle_*/')
//            ->orWhere('con_4',null)
            ->where('date_create', 'regexp', '/^' . $startdate . ' */')->orderBy('created_at');

        $bar = $this->output->createProgressBar($lists->count());
        $bar->start();

        $lists->chunk(1000, function ($itemlist) use ($bar) {
            foreach ($itemlist as $item) {

                $log = MemberWinloseProxy::create([
                    'member_user' => $item['game_user'],
                    'company' => $item['company'],
                    'method' => ($item['method'] == 'betsub' ? 'BET' : 'SETTLE'),
                    'roundid' => $item['con_2'],
                    'amount' => $item['amount'],
                    'date_event' => $item['date_create'],
                ]);
                $bar->advance();
            }
        });
//
        $lists = DB::connection('mongodb')->collection('gamelog')->where('response', 'in')
            ->where('method', 'betsub')
//            ->where(function($query) {
//                $query->where('con_3' , 'OPEN')
//                    ->where('con_4', 'regexp', '/^settle_*/');
//            })
            ->where('con_3', 'OPEN')
            ->where('con_4', 'regexp', '/^settle_*/')
//            ->where(function($query) {
//                $query->whereNotIn('con_3', ['WAITING','OPEN'])
//                    ->whereNull('con_4');
//            })
//            ->where('con_3', '!=' , 'WAITING')
//            ->where('con_4', 'regexp', '/^settle_*/')
//            ->orWhere('con_4',null)
            ->where('date_create', 'regexp', '/^' . $startdate . ' */')->orderBy('created_at');

        $bar = $this->output->createProgressBar($lists->count());
        $bar->start();

        $lists->chunk(1000, function ($itemlist) use ($bar) {
            foreach ($itemlist as $item) {

                $log = MemberWinloseProxy::create([
                    'member_user' => $item['game_user'],
                    'company' => $item['company'],
                    'method' => ($item['method'] == 'betsub' ? 'BET' : 'SETTLE'),
                    'roundid' => $item['con_2'],
                    'amount' => $item['amount'],
                    'date_event' => $item['date_create'],
                ]);
                $bar->advance();
            }
        });
//
//
        $lists = DB::connection('mongodb')->collection('gamelog')->where('response', 'in')
            ->where('method', 'paysub')
            ->whereNull('con_4')
            ->where('date_create', 'regexp', '/^' . $startdate . ' */')->orderBy('created_at');
//            ->whereRaw([
//                'date_create' => '/^2024-09-11 */',
//            ])->orderBy('created_at');
//            ->where('company', 'JOKER')->orderBy('created_at');
//            ->whereRaw('DATE(date_create) = ?', [$startdate])->orderBy('created_at');

        $bar = $this->output->createProgressBar($lists->count());
        $bar->start();

        $lists->chunk(1000, function ($itemlist) use ($bar) {
            foreach ($itemlist as $item) {


                MemberWinloseProxy::create([
                    'member_user' => $item['game_user'],
                    'company' => $item['company'],
                    'method' => ($item['method'] == 'betsub' ? 'BET' : 'SETTLE'),
                    'roundid' => $item['con_2'],
                    'amount' => $item['amount'],
                    'date_event' => $item['date_create'],
                ]);
                $bar->advance();
            }
        });


//
//        $itemlist = GameLogProxy::where('response', 'in')
//            ->where('method', 'paysub')
//            ->whereDate('date_create',$startdate)->get();
//
//        $this->info(count($itemlist));
//        return true;
//
//
//        GameLogProxy::where('response', 'in')
//            ->where('method', 'paysub')
//            ->whereDate('date_create',$startdate)
//            ->chunk(200, function ($itemlist) {
//                foreach ($itemlist as $item) {
//                    $this->info($item);
//                    MemberWinloseProxy::create([
//                        'member_user' => $item->game_user,
//                        'company' => $item->company,
//                        'method' => ($item->method == 'betsub' ? 'BET': 'SETTLE'),
//                        'roundid' => $item->con_2,
//                        'amount' => $item->amount,
//                        'date_event' => $item->date_create,
//                    ]);
//                }
//            });


    }


}
