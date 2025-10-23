<?php

namespace Gametech\Admin\Repositories;

use Gametech\Core\Eloquent\Repository;

class AdminRepository extends Repository
{
    /**
     * Specify Model class name
     *
     * @return mixed
     */
    function model()
    {
        return 'Gametech\Admin\Contracts\Admin';
    }
}
