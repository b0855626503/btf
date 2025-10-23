<?php

namespace Gametech\Auto\Console\Commands;

use Gametech\Auto\Jobs\CheckPayments as CheckPaymentsJob;
use Gametech\Core\Models\AllLog;
use Gametech\Payment\Models\BankPayment;
use Illuminate\Console\Command;

class FixPayment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:fix';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check Bank Payment prepare for refill to user';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $data = BankPayment::where('amount',null)->where('autocheck','Y')->where('status',1)->whereDate('date_create','>=','2024-08-30')->get();
        foreach($data as $item){
            AllLog::where('bank_payment_id',$item->code)->where('remark','')->delete();
            $item->autocheck = 'N';
            $item->status = 0;
            $item->save();
        }

//        $this->call("payment:emp-topup 50");
        return 0;
    }


}
