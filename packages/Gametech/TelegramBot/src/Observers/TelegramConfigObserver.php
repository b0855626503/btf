<?php

namespace Gametech\TelegramBot\Observers;

use Gametech\Core\Models\Log as Log;
use Gametech\TelegramBot\Models\TelegramConfig as EventData;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Telegram\Bot\Api;

class TelegramConfigObserver
{
    public function updated(EventData $data)
    {
        $userId = 0;
        $userName = '';

        $admin = Auth::guard('admin')->user();
        if ($admin) {
            $userId = $admin->code;
            $userName = $admin->user_name;
        }

        if ($userId > 0) {
            $log = new Log;
            $log->emp_code = $userId;
            $log->mode = 'EDIT';
            $log->menu = 'telegram_config';
            $log->record = $data->id;
            $log->item_before = json_encode($data->getOriginal());
            $log->item = json_encode($data->getChanges());
            $log->ip = Request::ip();
            $log->user_create = $userName;
            $log->save();

            if ($data->wasChanged('bot_token')) {

                $data->register_code = $this->generate6DigitCode();
                $data->saveQuietly();

                $this->registerTelegramWebhook($data->bot_token);

            }
        }

    }

    protected function generate6DigitCode()
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    protected function registerTelegramWebhook($token)
    {
        $url = 'https://api.'.(is_null(config('app.admin_domain_url')) ? config('app.domain_url') : config('app.admin_domain_url')).'/api/telegram/webhook';
        $telegram = new Api($token);
        $telegram->setWebhook(['url' => $url]);
    }
}
