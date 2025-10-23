<?php

namespace Gametech\API\Models;

use Illuminate\Database\Eloquent\Model;
use Gametech\API\Contracts\Emoji as EmojiContract;

class Emoji extends Model implements EmojiContract
{

    protected $table = 'emojis';

    protected $fillable = ['emoji_type', 'offset', 'length', 'custom_emoji_id','schedule_message_id'];

    public function message()
    {
        return $this->belongsTo(ScheduleMessage::class);
    }
}