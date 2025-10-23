<?php
	
	namespace Gametech\Wallet\Http\Controllers;
	
	use Gametech\Member\Repositories\MemberRepository;
	use Gametech\Payment\Repositories\BankAccountRepository;
	use Gametech\Payment\Repositories\BankPaymentRepository;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Http;
	use Illuminate\Support\Facades\Storage;
	
	class SlipController extends AppBaseController
	{
		/**
		 * Contains route related configuration
		 *
		 * @var array
		 */
		protected $_config;
		
		protected $ocr;
		
		protected $bankAccountRepository;
		
		protected $memberRepository;
		
		protected $bankPaymentRepository;
		
		/**
		 * Create a new Repository instance.
		 */
		public function __construct(
			
			BankAccountRepository $bankAccountRepository,
			MemberRepository      $memberRepository,
			BankPaymentRepository $bankPaymentRepository
		)
		{
			$this->middleware('customer');
			
			$this->_config = request('_config');
			
			$this->memberRepository = $memberRepository;
			
			$this->bankAccountRepository = $bankAccountRepository;
			
			$this->bankPaymentRepository = $bankPaymentRepository;
		}
		
		public function loadBank(Request $request)
		{
			$response['success'] = false;
			$response['bank'] = '';
			$result = [];
			
			$method = $request->input('method');

            switch ($method){

                case 'bank':

                    $datas = $this->bankAccountRepository->where('bank_type', 1)->where('enable', 'Y')->where('display_wallet', 'Y')->where('slip', 'N')->where('payment', 'N')->get();

                    if (count($datas) > 0) {

                        foreach ($datas as $i => $data) {

                            if ($data->bank->shortcode == 'TW') {
                                continue;
                            }

                            $result[$i] = [
                                'acc_no' => $data->acc_no,
                                'acc_name' => $data->acc_name,
                                'bank_name' => $data->bank->name_th,
                                'bank_pic' => Storage::url('bank_img/' . $data->bank->filepic),
                                'qr_pic' => Storage::url('bank_qr/' . $data->filepic),
                                'qrcode' => $data->qrcode == 'Y' ? true : false,
                                'code' => $data->code,
                                'deposit_min' => $data->deposit_min,
                            ];
                        }

                        $response['success'] = true;
                        $response['bank'] = $result;

                        return response()->json($response);
                    }
                    break;

                case 'tw':
                    $datas = $this->bankAccountRepository->where('bank_type', 1)->where('enable', 'Y')->where('display_wallet', 'Y')->where('slip', 'N')->where('payment', 'N')->get();

                    if (count($datas) > 0) {

                        foreach ($datas as $i => $data) {

                            if ($data->bank->shortcode != 'TW') {
                                continue;
                            }

                            $result[$i] = [
                                'acc_no' => $data->acc_no,
                                'acc_name' => $data->acc_name,
                                'bank_name' => $data->bank->name_th,
                                'bank_pic' => Storage::url('bank_img/' . $data->bank->filepic),
                                'qr_pic' => Storage::url('bank_qr/' . $data->filepic),
                                'qrcode' => $data->qrcode == 'Y' ? true : false,
                                'deposit_min' => $data->deposit_min,
                            ];
                        }

                        $response['success'] = true;
                        $response['bank'] = $result;

                        return response()->json($response);
                    }
                    break;

                case 'slip':
                    $data = $this->bankAccountRepository->where('bank_type', 1)->where('enable', 'Y')->where('display_wallet', 'Y')->where('slip', 'Y')->inRandomOrder()->first();

                    if ($data) {

                        $result = [
                            'acc_no' => $data->acc_no,
                            'acc_name' => $data->acc_name,
                            'bank_name' => $data->bank->name_th,
                            'bank_pic' => Storage::url('bank_img/' . $data->bank->filepic),
                            'qr_pic' => Storage::url('bank_qr/' . $data->filepic),
                            'qrcode' => $data->qrcode == 'Y' ? true : false,
                            'slip_bank' => $this->getBankCode($data->bank->shortcode),
                            'code' => $data->code
                        ];

                        $response['success'] = true;
                        $response['bank'] = $result;

                        return response()->json($response);
                    }
                    break;

                case 'payment':
                    $datas = $this->bankAccountRepository->with('bank')->where('bank_type', 1)->where('enable', 'Y')->where('display_wallet', 'Y')->where('slip', 'N')->where('payment', 'Y')->orderBy('sort')->get();


//                    dd(config('wildpay.min_deposit'));
                    if (count($datas) > 0) {

                        foreach ($datas as $i => $data) {

                            $key = strtolower($data->bank->shortcode);

                            $result[$key] = [
                                'id' => $key,
                                'min_deposit' => config("$key.min_deposit"),
                                'deposit_range' => config("$key.deposit_range"),
                                'payment_url' => route("api.$key.deposit"),
                                'name' => Ucfirst($key)
                            ];
                        }

                        $response['success'] = true;
                        $response['bank'] = $result;

                        return response()->json($response);


                    }
                    break;

            }
			

			
			return response()->json($response);
			
		}
		
		public function getBankCode($bank)
		{
			switch ($bank) {
				case 'BBL':
					return "01002";
				case 'KBANK':
					return "01004";
				case 'KTB':
					return "01006";
				case 'SCB':
					return "01014";
				case 'GHBANK':
					return "01033";
				case 'CIMB':
					return "01022";
				case 'IBANK':
					return "01066";
				case 'TISCO':
					return "01067";
				case 'TTB':
					return "01011";
				case 'BAAC':
					return "01034";
				case 'TW':
					return "04000";
				
			}
		}
		
		public function getBank($bank)
		{
			switch ($bank) {
				case '01002':
					return "1";
				case '01004':
					return "2";
				case '01006':
					return "3";
				case '01014':
					return "4";
				case '01033':
					return "5";
				case '01022':
					return "7";
				case '01066':
					return "13";
				case '01067':
					return "9";
				case '01069':
					return "19";
				case '01034':
					return "17";
				case '04000':
					return "18";
				
			}
		}
		
		public function verifySlip(Request $request)
		{
			// ตรวจสอบว่าไฟล์กับ payload ถูกส่งมาหรือไม่
			$secretKey = config('slip2go.secret_key');
			
			if (!$request->hasFile('file')) {
				return response()->json(['success' => false, 'message' => 'ข้อมูลไม่ครบ'], 422);
			}
			//        if (! $request->hasFile('file') || ! $request->has('payload')) {
			//            return response()->json(['success' => false, 'message' => 'ข้อมูลไม่ครบ'], 422);
			//        }
			
			$file = $request->file('file');
			
			$payload = json_decode($request->input('payload'), true);
			
			// ใช้ Guzzle ส่ง multipart
			$response = Http::withHeaders([
				'Authorization' => "Bearer $secretKey",
			])->attach(
				'file', file_get_contents($file->getRealPath()), $file->getClientOriginalName()
			)->post('https://connect.slip2go.com/api/verify-slip/qr-image/info', [
				['name' => 'payload', 'contents' => json_encode($payload)],
			]);
			
			$data = $response->json();
			if ($data['code'] === '200200') {
				$datenow = now()->toDateTimeString();
				$info = json_decode($request->input('info'), true);
				
				$member = $this->user();
				$account = $info['code'];
				$amount = $data['data']['amount'];
				$detail = 'รายการฝากเงิน ผ่านการอัพสลิป transRef : ' . $data['data']['transRef'] . ' referenceId ' . $data['data']['referenceId'];
				$bank_account = app('Gametech\Payment\Repositories\BankAccountRepository')->find($account);
				
				$bank = app('Gametech\Payment\Repositories\BankRepository')->find($bank_account->banks);
				
				$hash = md5($account . $datenow . $amount . $detail);
				
				$data = [
					'bank' => strtolower($bank->shortcode . '_' . $bank_account->acc_no),
					'txid' => $data['data']['transRef'],
					'report_id' => $data['data']['referenceId'],
					'detail' => $detail,
					'account_code' => $account,
					'autocheck' => 'W',
					'bankstatus' => 1,
					'bank_name' => $bank->shortcode,
					'bank_time' => $datenow,
					'channel' => 'SLIP',
					'value' => $amount,
					'tx_hash' => $hash,
					'status' => 0,
					'ip_admin' => $request->ip(),
					'member_topup' => $member->code,
					'remark_admin' => '',
					'emp_topup' => 0,
					'user_create' => 'SYSAUTO',
					'create_by' => 'SYSAUTO'
				];
				
				$this->bankPaymentRepository->create($data);
				
				
			}
			
			return response()->json($response->json(), $response->status());
		}
		
		public function uploadQr(Request $request)
		{
			if (!$request->hasFile('file')) {
				return response()->json(['success' => false, 'message' => 'ข้อมูลไม่ครบ'], 200);
			}
			
			$user = auth()->guard('customer')->user();
			$filename = $user->user_name . '.' . $request->file('file')->getClientOriginalExtension();
			$path = $request->file('file')->storeAs('qr', $filename, 'public');
			if ($path) {
				$user->pic_id = $path;
				$user->save();
			} else {
				return response()->json(['success' => false, 'message' => 'อัพรูปไม่สำเร็จ'], 200);
			}
			return response()->json(['success' => true, 'message' => 'สำเร็จ', 'img_url' => asset('storage/' . $path)], 200);
		}
	}
