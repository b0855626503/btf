<?php

namespace Gametech\Admin\Http\Controllers;

use Gametech\Admin\DataTables\FailedRequestDataTable;

class FailedRequestController extends AppBaseController
{
    protected $_config;

    protected $repository;

    public function __construct(

    ) {
        $this->_config = request('_config');

        $this->middleware('admin');

    }

    public function index(FailedRequestDataTable $failedRequestDataTable)
    {
        return $failedRequestDataTable->render($this->_config['view']);
    }
}
