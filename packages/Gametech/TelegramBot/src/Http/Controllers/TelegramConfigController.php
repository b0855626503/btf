<?php

namespace Gametech\TelegramBot\Http\Controllers;

use App\Http\Controllers\AppBaseController;
use Gametech\TelegramBot\Repositories\TelegramConfigRepository;
use Illuminate\Http\Request;

class TelegramConfigController extends AppBaseController
{
    protected $_config;

    protected $repository;

    public function __construct(
        TelegramConfigRepository $repository
    ) {
        $this->_config = request('_config');

        $this->middleware('admin');

        $this->repository = $repository;

    }

    public function index()
    {
        $configs = $this->repository->findOrFail(1);
        $configs = collect($configs)->toArray();

        return view($this->_config['view'])->with('configs', $configs);
    }

    public function update($id, Request $request)
    {

        $chk = $this->repository->findOrFail($id);

        if (empty($chk)) {
            return $this->sendError('ไม่พบข้อมูลดังกล่าว', 200);
        }

        $data = $request->all();

        $this->repository->update($data, $id);

        return $this->sendSuccess('ดำเนินการเสร็จสิ้น โปรดกด รีเฟรช หรือ F5 จะเห็น Register Code');

    }
}
