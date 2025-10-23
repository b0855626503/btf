<?php

namespace Gametech\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Gametech\Core\Contracts\FailedRequest as FailedRequestContract;

class FailedRequest extends Model implements FailedRequestContract
{
    protected $table = 'failed_requests';
}