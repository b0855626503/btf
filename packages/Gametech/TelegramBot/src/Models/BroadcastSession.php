<?php

namespace Gametech\TelegramBot\Models;

use Gametech\TelegramBot\Contracts\BroadcastSession as BroadcastSessionContract;
use Illuminate\Database\Eloquent\Model;

class BroadcastSession extends Model implements BroadcastSessionContract
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'created_at'];
}
