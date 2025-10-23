<?php

namespace Gametech\TelegramBot\Repositories;

use Gametech\Core\Eloquent\Repository;

class TelegramConfigRepository extends Repository
{
    /**
     * Specify Model class name
     *
     * @return mixed
     */
    public function model()
    {
        return 'Gametech\TelegramBot\Contracts\TelegramConfig';
    }
}
