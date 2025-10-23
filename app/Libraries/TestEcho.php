<?php

namespace App\Libraries;

use App\Events\RealTimeMessage;
use App\Events\RealTimeMessageAll;

class TestEcho
{
    public function Send($message)
    {
        broadcast(new RealTimeMessage($message));

        return 'Ok';
    }

    public function SendAll($message)
    {
        broadcast(new RealTimeMessageAll($message));

        return 'Ok';
    }
}
