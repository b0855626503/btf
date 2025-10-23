<?php

namespace Gametech\TelegramBot\Models;

use Gametech\TelegramBot\Contracts\TelegramConfig as TelegramConfigContract;
use Illuminate\Database\Eloquent\Model;

class TelegramConfig extends Model implements TelegramConfigContract
{
    protected $table = 'telegram_config';

    protected $fillable = [
        'bot_token',
        'register_code',
        'channel_chat_id',
        'channel_title',
        'channel_type',
        'channel_registered_at',
    ];

    protected $dates = [
        'channel_registered_at',
        'created_at',
        'updated_at',
    ];
}
