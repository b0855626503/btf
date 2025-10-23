<?php

namespace Gametech\Admin\Http\Controllers;


use Gametech\Admin\DataTables\CouponDataTable;
use Gametech\Core\Repositories\CouponListRepository;
use Gametech\Core\Repositories\CouponRepository;
use Illuminate\Http\Request;


class CouponController extends AppBaseController
{
    protected $_config;

    protected $repository;

    protected $couponListRepository;

    public function __construct
    (
        CouponRepository $repository,
        CouponListRepository $couponListRepository
    )
    {
        $this->_config = request('_config');

        $this->middleware('admin');

        $this->repository = $repository;

        $this->couponListRepository = $couponListRepository;
    }


    public function index(CouponDataTable $couponDataTable)
    {
        return $couponDataTable->render($this->_config['view']);
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

        if($data['refill_start']  != '') {
            $data['refill_start'] = $data['refill_start'] . ' 00:00:00';
        }
        if($data['refill_stop']  != '') {
            $data['refill_stop'] = $data['refill_stop'] . ' 23:59:59';
        }

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

        if($data['refill_start']  != '') {
            $data['refill_start'] = $data['refill_start'] . ' 00:00:00';
        }
        if($data['refill_stop']  != '') {
            $data['refill_stop'] = $data['refill_stop'] . ' 23:59:59';
        }


//        $data['sort'] = 1;
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

    public function gen(Request $request)
    {
        $user = $this->user()->name.' '.$this->user()->surname;
        $id = $request->input('id');


        $chk = $this->repository->find($id);
        if(!$chk){
            return $this->sendError('ไม่พบข้อมูลดังกล่าว',200);
        }

        $result = $this->couponListRepository->genCouponList($id);
        if(!$result){
            return $this->sendError('ผิดพลาดในการ GEN',200);
        }

        $data['gen'] = 'Y';
        $data['user_update'] = $user;
        $this->repository->update($data, $id);



        return $this->sendSuccess('GEN คูปองเสร็จสิ้น');

    }

    public function couponlist(Request $request)
    {
        $id = $request->input('id');
//        dd($id);
        $header = '';
//        $member = $this->couponListRepository->find($id);
        $responses = [];
        $header = 'ข้อมูลคูปอง';

//        $data = $this->repository->find($id);
//        $data = $this->couponListRepository->where('coupon_code',$id)->get();
//dd($data);
        $response = $this->couponListRepository->findByField('coupon_code',$id);
//dd($response);

        $response = collect($response)->map(function ($item) {
//            $item = (object)$items;
            return [
                'code' => $item->name,
                'member_code' => ($item->member_code == 0 ? '' : $item->members->user_name),
                'status' => ($item->status == 'N' ? 'ยังไม่ใช้งาน' : 'ใช้งานแล้ว'),
                'date' => ($item->status == 'N' ? '' : core()->formatDate($item->date_update,'Y-m-d H:i:s')),
                ];


        });

        $responses = $response->values()->all();


        $result['name'] = $header;
        $result['list'] = $responses;

        return $this->sendResponseNew($result, 'complete');
    }


}
