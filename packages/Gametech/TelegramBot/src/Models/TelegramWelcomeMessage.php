<?php

namespace Gametech\TelegramBot\Models;

use Gametech\TelegramBot\Contracts\TelegramWelcomeMessage as TelegramWelcomeMessageContract;
use Illuminate\Database\Eloquent\Model;

class TelegramWelcomeMessage extends Model implements TelegramWelcomeMessageContract
{
    protected $table = 'telegram_welcome_messages';

    protected $fillable = [
        'message',
        'media_url',
        'media_type', // photo, video, animation
        'lang',
    ];
}
