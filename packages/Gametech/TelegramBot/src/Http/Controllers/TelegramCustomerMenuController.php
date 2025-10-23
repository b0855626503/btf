<?php

namespace Gametech\TelegramBot\Http\Controllers;

use App\Http\Controllers\AppBaseController;
use Gametech\TelegramBot\DataTables\TelegramCustomerMenuDataTable;
use Gametech\TelegramBot\Repositories\TelegramCustomerMenuRepository;
use Illuminate\Http\Request;

class TelegramCustomerMenuController extends AppBaseController
{
    protected $_config;

    protected $repository;

    public function __construct(
        TelegramCustomerMenuRepository $repository
    ) {
        $this->_config = request('_config');

        $this->middleware('admin');

        $this->repository = $repository;

    }

    public function index(TelegramCustomerMenuDataTable $telegramCustomerMenuDataTable)
    {
        return $telegramCustomerMenuDataTable->render($this->_config['view']);
    }

    public function loadData(Request $request)
    {
        $id = $request->input('id');

        $data = $this->repository->find($id);
        if (! $data) {
            return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        }

        return $this->sendResponse($data, 'ดำเนินการเสร็จสิ้น');

    }

    public function create(Request $request)
    {

        $data = $request->input('data');

        $data['sort'] = 1;
        $data['active'] = 1;

        $this->repository->create($data);

        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');

    }

    public function edit(Request $request)
    {

        $id = $request->input('id');
        $status = $request->input('status');
        $method = $request->input('method');

        $data[$method] = $status;

        $chk = $this->repository->find($id);
        if (! $chk) {
            return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        }

        $this->repository->update($data, $id);

        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');

    }

    public function update(Request $request)
    {

        $id = $request->input('id');
        $data = $request->input('data');

        $chk = $this->repository->find($id);
        if (! $chk) {
            return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        }

        $this->repository->update($data, $id);

        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');

    }
}
