<?php

namespace Gametech\API\Models;

use Illuminate\Database\Eloquent\Model;
use Gametech\API\Contracts\ScheduleMessage as ScheduleMessageContract;

class ScheduleMessage extends Model implements ScheduleMessageContract
{
    protected $table = 'schedule_messages';

    protected $fillable = ['scheduled_time','message','user_id','media_url','step','chat_id','entities'];

    public function emojis()
    {
        return $this->hasMany(Emoji::class);
    }
}