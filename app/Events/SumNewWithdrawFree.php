<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class SumNewWithdrawFree implements ShouldBroadcast
{
    use SerializesModels;

    public $sum;

    public function __construct($sum)
    {
        $this->sum = $sum;
    }

    public function broadcastOn()
    {
        return new PrivateChannel(env('APP_NAME').'_events');
    }


}
