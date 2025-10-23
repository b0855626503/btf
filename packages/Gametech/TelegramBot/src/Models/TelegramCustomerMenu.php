<?php

namespace Gametech\TelegramBot\Models;

use Gametech\TelegramBot\Contracts\TelegramCustomerMenu as TelegramCustomerMenuContract;
use Illuminate\Database\Eloquent\Model;

class TelegramCustomerMenu extends Model implements TelegramCustomerMenuContract
{
    protected $table = 'telegram_customer_menus';

    protected $fillable = [
        'title', 'type', 'value', 'position', 'active',
    ];
}
