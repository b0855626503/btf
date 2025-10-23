<?php

namespace Gametech\TelegramBot\Repositories;

use Gametech\Core\Eloquent\Repository;

class TelegramCustomerMenuRepository extends Repository
{
    /**
     * Specify Model class name
     *
     * @return mixed
     */
    public function model()
    {
        return 'Gametech\TelegramBot\Contracts\TelegramCustomerMenu';
    }
}
