<?php

namespace Gametech\Admin\Http\Controllers;


use Gametech\Admin\DataTables\NoticeNewDataTable;
use Gametech\Core\Repositories\NoticeNewRepository;
use Illuminate\Http\Request;


class NoticeNewController extends AppBaseController
{
    protected $_config;

    protected $repository;

    public function __construct
    (
        NoticeNewRepository $repository
    )
    {
        $this->_config = request('_config');

        $this->middleware('admin');

        $this->repository = $repository;
    }


    public function index(NoticeNewDataTable  $noticeDataTable)
    {
        return $noticeDataTable->render($this->_config['view']);
    }

    public function loadData(Request $request)
    {
        $id = $request->input('id');

        $data = $this->repository->find($id);
        if(!$data){
            return $this->sendError('ไม่พบข้อมูลดังกล่าว',200);
        }

        return $this->sendResponse($data,'ดำเนินการเสร็จสิ้น');

    }

    public function create(Request $request)
    {
        $user = $this->user()->name.' '.$this->user()->surname;
        $data = $request->input('data');

        $data['user_create'] = $user;
        $data['user_update'] = $user;

        $this->repository->create($data);

        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');

    }

    public function update(Request $request)
    {
        $user = $this->user()->name.' '.$this->user()->surname;
        $id = $request->input('id');
        $data = $request->input('data');


        $chk = $this->repository->find($id);
        if(!$chk){
            return $this->sendError('ไม่พบข้อมูลดังกล่าว',200);
        }


        $data['user_update'] = $user;
        $this->repository->update($data, $id);

        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');

    }

    public function edit(Request $request)
    {
        $user = $this->user()->name.' '.$this->user()->surname;
        $id = $request->input('id');
        $status = $request->input('status');
        $method = $request->input('method');


        $data[$method] = $status;

        $chk = $this->repository->find($id);
        if(!$chk){
            return $this->sendError('ไม่พบข้อมูลดังกล่าว',200);
        }

        $data['user_update'] = $user;
        $this->repository->update($data, $id);

        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');

    }

    public function destroy(Request $request)
    {
        $id = $request->input('id');

        $chk = $this->repository->find($id);

        if(!$chk){
            return $this->sendError('ไม่พบข้อมูลดังกล่าว',200);
        }

        $this->repository->delete($id);

        return $this->sendSuccess('ดำเนินการเสร็จสิ้น');
    }


}
