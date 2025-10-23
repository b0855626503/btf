<?php

namespace Gametech\TelegramBot\Models;

use Gametech\TelegramBot\Contracts\TelegramAdmin as TelegramAdminContract;
use Illuminate\Database\Eloquent\Model;

class TelegramAdmin extends Model implements TelegramAdminContract
{
    protected $table = 'telegram_admins';

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'username',
        'registered_at',
    ];

    protected $dates = [
        'registered_at',
        'created_at',
        'updated_at',
    ];
}
