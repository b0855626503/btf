<?php

namespace Gametech\Auto\Console\Commands;

use Gametech\API\Models\ScheduleMessage;
use Gametech\Auto\Jobs\BotSchedule;
use Illuminate\Console\Command;
use Telegram\Bot\Api;
use Telegram\Bot\Laravel\Facades\Telegram;

class BotScheduleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bot-telegram:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command For Run Telegram Bot Schedule';

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
     * @return mixed
     */
    public function handle()
    {

        $messages = ScheduleMessage::on('mysqlbot')->with('emojis')->where('scheduled_time', '<=', now())->where('step','completed')->get();

        foreach ($messages as $message) {
            BotSchedule::dispatchNow($message);

        }


        return true;
    }
}
