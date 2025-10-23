<?php

namespace Gametech\Auto\Console\Commands;

use Illuminate\Console\Command;

class TelegramScheduleMessage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:name';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        $update = Telegram::commandsHandler(true);

        $chatId = $update->getMessage()->getChat()->getId();
        $text = $update->getMessage()->getText();

        // เพิ่มคำสั่งเพื่อบันทึกข้อมูลลงในฐานข้อมูล
        if (preg_match('/^schedule (.+) at (\d{4}-\d{2}-\d{2} \d{2}:\d{2})$/', $text, $matches)) {
            $message = $matches[1];
            $scheduledTime = Carbon::createFromFormat('Y-m-d H:i', $matches[2]);

            ScheduledMessage::create([
                'message' => $message,
                'scheduled_time' => $scheduledTime
            ]);

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => 'Message scheduled successfully!'
            ]);
        }
    }
}
