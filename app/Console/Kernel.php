<?php

namespace App\Console;

use Gametech\Auto\Console\Commands\AddCashback;
use Gametech\Auto\Console\Commands\AddCashbackSeamless;
use Gametech\Auto\Console\Commands\AddMemberIC;
use Gametech\Auto\Console\Commands\AddMemberICSeamless;
use Gametech\Auto\Console\Commands\AutoPayOut;
use Gametech\Auto\Console\Commands\BotScheduleCommand;
use Gametech\Auto\Console\Commands\Cashback;
use Gametech\Auto\Console\Commands\CheckFastStart;
use Gametech\Auto\Console\Commands\CheckPayment;
use Gametech\Auto\Console\Commands\ClearDB;
use Gametech\Auto\Console\Commands\DailyStat;
use Gametech\Auto\Console\Commands\DailyStatMonth;
use Gametech\Auto\Console\Commands\DiamondClear;
use Gametech\Auto\Console\Commands\GetPayment;
use Gametech\Auto\Console\Commands\GetPaymentAcc;
use Gametech\Auto\Console\Commands\MemberIC;
use Gametech\Auto\Console\Commands\NewCashback;
use Gametech\Auto\Console\Commands\NewCashbackSeamless;
use Gametech\Auto\Console\Commands\NewMemberIC;
use Gametech\Auto\Console\Commands\NewMemberICSeamless;
use Gametech\Auto\Console\Commands\OptimizeTable;
use Gametech\Auto\Console\Commands\PostUpdate;
use Gametech\Auto\Console\Commands\PreUpdate;
use Gametech\Auto\Console\Commands\TopupPayment;
use Gametech\Auto\Console\Commands\UpdateHash;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        CheckPayment::class,
        TopupPayment::class,
        GetPayment::class,
        GetPaymentAcc::class,
        Cashback::class,
        MemberIC::class,
        DailyStat::class,
        CheckFastStart::class,
        DailyStatMonth::class,
        UpdateHash::class,
        PostUpdate::class,
        OptimizeTable::class,
        PreUpdate::class,
        NewCashback::class,
        NewMemberIC::class,
        AddCashback::class,
        AddMemberIC::class,
        ClearDB::class,
        NewCashbackSeamless::class,
        NewMemberICSeamless::class,
        AddCashbackSeamless::class,
        AddMemberICSeamless::class,
        DiamondClear::class,
        AutoPayOut::class,
        BotScheduleCommand::class,
        \App\Console\Commands\FlushRedisLogsUser::class,
    ];


    /**
     * Define the application's command schedule.
     *
     * @param Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $yesterday = now()->subDays(1)->toDateString();


        $schedule->command('cleanup:inactive-users')->everyFiveMinutes();



//        $schedule->command('bot-telegram:run')->everyMinute();
        $schedule->command('payment:get kbank')->everyMinute();






    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');
        $this->load(__DIR__ . '/../../packages/Gametech/Auto/src/Console/Commands');

        require base_path('routes/console.php');
    }
}
