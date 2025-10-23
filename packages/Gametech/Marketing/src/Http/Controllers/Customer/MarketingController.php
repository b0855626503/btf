<?php
	
	namespace Gametech\Marketing\Http\Controllers\Customer;
	
	use App\Http\Controllers\AppBaseController;
	use App\Providers\RouteServiceProvider;
	use Exception;
	use Gametech\Marketing\Repositories\MarketingCampaignRepository;
	use Gametech\Marketing\Repositories\MarketingTeamRepository;
	use Gametech\Marketing\Repositories\RegistrationLinkClickRepository;
	use Gametech\Marketing\Repositories\RegistrationLinkRepository;
	use Illuminate\Foundation\Auth\AuthenticatesUsers;
	use Illuminate\Http\JsonResponse;
	use Illuminate\Http\RedirectResponse;
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Auth;
	use Illuminate\Support\Facades\Event;
	use Illuminate\Support\Facades\Hash;
	use Illuminate\Support\Facades\Http;
	use Illuminate\Support\Facades\Lang;
	use Illuminate\Support\Facades\Redis;
	use Illuminate\Support\Facades\Validator;
	use Illuminate\Support\Str;
	use Illuminate\Validation\Rule;
	
	class MarketingController extends AppBaseController
	{
		use AuthenticatesUsers;
		
		protected $_config;
		
		protected $redirectTo = RouteServiceProvider::HOME;
		
		protected $marketingCampaignRepository;
		
		protected $registerLinkRepository;
		
		protected $marketingTeamRepository;
		
		protected $registrationLinkClickRepository;
		
		public function __construct(
			MarketingCampaignRepository     $marketingCampaignRepository,
			MarketingTeamRepository         $marketingTeamRepository,
			RegistrationLinkRepository      $registerLinkRepository,
			RegistrationLinkClickRepository $registrationLinkClickRepository
		)
		{
			$this->_config = request('_config');
			
			$this->middleware('guest');
			
			$this->marketingCampaignRepository = $marketingCampaignRepository;
			
			$this->registerLinkRepository = $registerLinkRepository;
			
			$this->marketingTeamRepository = $marketingTeamRepository;
			
			$this->registrationLinkClickRepository = $registrationLinkClickRepository;
			
		}
		
		public function store(Request $request, $id = null, $refer = null)
		{
			if (Auth::guard('customer')->check()) {
				return redirect()->route('customer.home.index'); // หรือหน้าอื่นที่ต้องการ
			}
			// ถ้าไม่มี ID แต่มี session => redirect ไป register/{code}
			if (!$id && session()->has('marketing_id')) {
				$urlRefer = session()->get('marketing_refer') ? '/' . session()->get('marketing_refer') : '';
				return redirect()->route('customer.session.store', ['id' => session('marketing_id'), 'refer' => $urlRefer]);
			}
			
			// ถ้ายังไม่มีโค้ดหลังจากนั้น = ไม่มี session และไม่มี query
			if (!$id) {
				$banks = app('Gametech\Payment\Repositories\BankRepository')->findWhere([
					'enable' => 'Y',
					'show_regis' => 'Y',
					['code', '<>', 0],
				]);
				
				$refers = app('Gametech\Core\Repositories\ReferRepository')->findWhere([
					'enable' => 'Y',
					['code', '<>', 0],
				]);
				
				// แสดง view
				return view('wallet::customer.marketing.register', compact('banks', 'refers', 'id', 'refer'));
			}
			
			// ค้นหา register link
			$data = $this->registerLinkRepository->findOneWhere(['code' => $id]);
			if (!$data) {
				return redirect()->route('customer.home.index');
			}
			
			if ($data->campaign_id != null && $data->load('campaign')->campaign->is_ended) {
				return redirect()->route('customer.home.index');
			}
			
			$userAgent = $request->userAgent();
			if (Str::contains(strtolower($userAgent), ['bot', 'crawl', 'spider'])) {
				return redirect()->route('customer.home.index');
			}
			
			$ip = $request->ip();
			$redisKey = "reg_click:{$data->id}:{$ip}";
			$ttlSeconds = 30 * 60;
			
			try {
				if (!Redis::connection('session')->get($redisKey)) {
					$this->registrationLinkClickRepository->create([
						'registration_link_id' => $data->id,
						'ip' => $request->ip(),
						'user_agent' => $request->userAgent(),
						'referrer' => $request->headers->get('referer'),
						'created_at' => now(),
					]);
					Redis::connection('session')->setex($redisKey, $ttlSeconds, now()->toDateTimeString());
				}
			} catch (Exception $e) {
				logger()->warning('Failed to log registration click: ' . $e->getMessage());
			}
			
			// เก็บโค้ดใน session เผื่อ reload
			session(['marketing_code' => $id]);
			if ($refer) {
				session(['marketing_refer' => $refer]);
			} else {
				session()->forget('marketing_refer');
			}
			
			// โหลดข้อมูล bank / refer
			$banks = app('Gametech\Payment\Repositories\BankRepository')->findWhere([
				'enable' => 'Y',
				'show_regis' => 'Y',
				['code', '<>', 0],
			]);
			
			$refers = app('Gametech\Core\Repositories\ReferRepository')->findWhere([
				'enable' => 'Y',
				['code', '<>', 0],
			]);
			
			
			if ($refer) {
				$refer = $refer->code;
			} else {
				$refer = null;
			}
			
			
			// แสดง view
			return view('wallet::customer.marketing.register', compact('banks', 'refers', 'id', 'refer'));
		}
		
		protected function guard()
		{
			return Auth::guard('customer');
		}
		
		public function storeUser(Request $request, $id = null, $refer = null)
		{
			if (Auth::guard('customer')->check()) {
				return redirect()->route('customer.home.index'); // หรือหน้าอื่นที่ต้องการ
			}
			// ถ้าไม่มี ID แต่มี session => redirect ไป register/{code}
			if (!$id && session()->has('marketing_id')) {
				$urlRefer = session()->get('marketing_refer') ? '/' . session()->get('marketing_refer') : '';
				return redirect()->route('customer.session.store', ['id' => session('marketing_id'), 'refer' => $urlRefer]);
			}
			
			// ถ้ายังไม่มีโค้ดหลังจากนั้น = ไม่มี session และไม่มี query
			if (!$id) {
				$banks = app('Gametech\Payment\Repositories\BankRepository')->findWhere([
					'enable' => 'Y',
					'show_regis' => 'Y',
					['code', '<>', 0],
				]);
				
				$refers = app('Gametech\Core\Repositories\ReferRepository')->findWhere([
					'enable' => 'Y',
					['code', '<>', 0],
				]);
				
				// แสดง view
				return view('wallet::customer.marketing.register_username', compact('banks', 'refers', 'id', 'refer'));
			}
			
			// ค้นหา register link
			$data = $this->registerLinkRepository->findOneWhere(['code' => $id]);
			if (!$data) {
				return redirect()->route('customer.home.index');
			}
			
			if ($data->campaign_id != null && $data->load('campaign')->campaign->is_ended) {
				return redirect()->route('customer.home.index');
			}
			
			$userAgent = $request->userAgent();
			if (Str::contains(strtolower($userAgent), ['bot', 'crawl', 'spider'])) {
				return redirect()->route('customer.home.index');
			}
			
			$ip = $request->ip();
			$redisKey = "reg_click:{$data->id}:{$ip}";
			$ttlSeconds = 30 * 60;
			
			try {
				if (!Redis::connection('session')->get($redisKey)) {
					$this->registrationLinkClickRepository->create([
						'registration_link_id' => $data->id,
						'ip' => $request->ip(),
						'user_agent' => $request->userAgent(),
						'referrer' => $request->headers->get('referer'),
						'created_at' => now(),
					]);
					Redis::connection('session')->setex($redisKey, $ttlSeconds, now()->toDateTimeString());
				}
			} catch (Exception $e) {
				logger()->warning('Failed to log registration click: ' . $e->getMessage());
			}
			
			// เก็บโค้ดใน session เผื่อ reload
			session(['marketing_code' => $id]);
			if ($refer) {
				session(['marketing_refer' => $refer]);
			} else {
				session()->forget('marketing_refer');
			}
			
			// โหลดข้อมูล bank / refer
			$banks = app('Gametech\Payment\Repositories\BankRepository')->findWhere([
				'enable' => 'Y',
				'show_regis' => 'Y',
				['code', '<>', 0],
			]);
			
			$refers = app('Gametech\Core\Repositories\ReferRepository')->findWhere([
				'enable' => 'Y',
				['code', '<>', 0],
			]);
			
			
			if ($refer) {
				$refer = $refer->code;
			} else {
				$refer = null;
			}
			
			
			// แสดง view
			return view('wallet::customer.marketing.register_username', compact('banks', 'refers', 'id', 'refer'));
		}
		
		
		public function register(Request $request)
		{
			$otp = '';
			$config = core()->getConfigData();
			
			$datenow = now()->toDateTimeString();
			$today = now()->toDateString();
			$ip = $request->ip();
			//        $data = $request->input();
			
			$data = $request->all();
			
			$data['user_name'] = Str::of($data['user_name'])->replaceMatches('/[^0-9]++/', '')->trim()->__toString();
			$username = strip_tags($data['user_name']);
			$tel = $username;
			$data['tel'] = $tel;
			
			$acc_no = Str::of($data['acc_no'])->replaceMatches('/[^0-9]++/', '')->trim()->__toString();
			$data['acc_no'] = $acc_no;
			$bank_code = $data['bank'];
			$data['wallet_id'] = strip_tags($data['tel']);
			
			$lineid = trim(strip_tags($data['lineid']));
			
			$wallet_id = trim($data['wallet_id']);
			
			if ($config->freecredit_all === 'Y') {
				$freecredit = 'Y';
			} else {
				$freecredit = 'N';
			}
			
			if ($config->verify_open === 'Y') {
				$verify = 'N';
				if ($config->verify_sms === 'Y') {
					$otp = rand(100001, 999999);
				}
				
			} else {
				$verify = 'Y';
			}
			
			$validator = Validator::make($data, [
				'acc_no' => [
					'required',
					'digits_between:1,14',
					Rule::unique('members', 'acc_no')->where(function ($query) use ($bank_code) {
						return $query->where('bank_code', $bank_code);
					}),
				],
				//            'wallet_id' => [
				//                'required',
				//                Rule::unique('members', 'wallet_id')->where(function ($query) use ($wallet_id) {
				//                    return $query->where('wallet_id', $wallet_id);
				//                })
				//            ],
				'firstname' => 'required|alpha',
				'lastname' => ['required', 'regex:/^[\pL\pM\s\-]+$/u'],
				'password' => 'required|min:4|max:10',
				'user_name' => 'required|numeric|unique:members,user_name',
				'wallet_id' => 'required|numeric|unique:members,wallet_id',
				'tel' => 'required|numeric|unique:members,tel',
				'bank' => 'required|numeric',
				'refer' => 'required|numeric',
				//            'g-recaptcha-response' => 'required'
			]);
			
			//        dd($validator);
			if ($validator->fails()) {
				
				if ($request->expectsJson() || $request->ajax()) {
					return $this->sendError(Lang::get('app.register.fail'), 200);
				}
				
				session()->flash('error', Lang::get('app.register.fail'));
				
				return redirect()->back()->withErrors($validator)->withInput();
				
			}
			
			Event::dispatch('customer.register.before', $data);
			
			$upline = '0';
			$team_id = null;
			$campaign_id = null;
			if (isset($data['marketing'])) {
				$marketing = $this->registerLinkRepository->findOneWhere(['code' => $data['marketing']]);
				if ($marketing) {
					$team_id = $marketing->team_id;
					$campaign_id = $marketing->campaign_id;
				}
				unset($data['marketing']);
			}
			
			$refer = $data['refer'];
			unset($data['refer']);
			
			$pass = $data['password'];
			//        $pass_confirm = $data['password_confirm'];
			//        unset($data['password_confirm']);
			unset($data['password']);
			$data['firstname'] = strip_tags($data['firstname']);
			$data['lastname'] = strip_tags($data['lastname']);
			$name = $data['firstname'] . ' ' . $data['lastname'];
			if (isset($data['promotion'])) {
				$pro = $data['promotion'];
			} else {
				$pro = 'N';
			}
			
			unset($data['g-recaptcha-response']);
			
			unset($data['bank']);
			if ($bank_code == 4) {
				$acc_check = substr($acc_no, -4);
			} else {
				$acc_check = substr($acc_no, -6);
			}
			$acc_bay = substr($acc_no, -7);
			
			$data = array_merge($data, [
				'password' => Hash::make($pass),
				'refer_code' => $refer,
				'upline_code' => $upline,
				'user_name' => $username,
				'user_pass' => $pass,
				'wallet_id' => $wallet_id,
				'tel' => $tel,
				'lineid' => $lineid,
				'acc_no' => $acc_no,
				'acc_check' => $acc_check,
				'acc_bay' => $acc_bay,
				'acc_kbank' => '',
				'bank_code' => $bank_code,
				'confirm' => $verify,
				'freecredit' => $freecredit,
				'check_status' => 'N',
				'promotion' => $pro,
				'name' => $name,
				'user_create' => $name,
				'user_update' => $name,
				'lastlogin' => $datenow,
				'date_regis' => $today,
				'birth_day' => $today,
				'session_limit' => null,
				'payment_limit' => null,
				'payment_delay' => null,
				'remark' => '',
				'gender' => 'M',
				'team_id' => $team_id,
				'campaign_id' => $campaign_id,
				'otp' => $otp,
				'ip' => $ip,
			]);
			
			$response = app('Gametech\Marketing\Repositories\MarketingMemberRepository')->create($data);
			
			if (!$response->code) {
				
				if ($request->expectsJson() || $request->ajax()) {
					return $this->sendError(Lang::get('app.register.fail2'), 200);
				}
				
				session()->flash('error', Lang::get('app.register.fail2'));
				
				return redirect()->back();
			}
			
			if ($config->verify_open == 'N') {
				
				if ($config->seamless == 'Y') {
					
					$game = app('Gametech\Game\Repositories\GameRepository')->findOneWhere(['enable' => 'Y', 'status_open' => 'Y', 'id' => 'seamless']);
					$member = app('Gametech\Member\Repositories\MemberRepository')->find($response->code);
					$res = app('Gametech\Game\Repositories\GameUserRepository')->addGameUser($game->code, $member->code, ['username' => $username, 'password' => $pass, 'name' => $name, 'user_create' => $name]);
					if ($res['success'] === true) {
						
						session()->flash('success', Lang::get('app.register.success'));
						if ($this->attemptLogin($request)) {
							return $this->sendLoginResponse($request);
						}
						
						return $this->sendFailedLoginResponse($request);
						
					} else {
						app('Gametech\Member\Repositories\MemberRepository')->delete($response->code);
						
						if ($request->expectsJson() || $request->ajax()) {
							return $this->sendError($res['msg'], 200);
						}
						
						session()->flash('error', $res['msg']);
						
						return redirect()->back();
					}
					
				} else {
					
					if ($config->multigame_open === 'N') {
						$game = app('Gametech\Game\Repositories\GameRepository')->findOneWhere(['enable' => 'Y', 'status_open' => 'Y']);
						$member = app('Gametech\Member\Repositories\MemberRepository')->find($response->code);
						$res = app('Gametech\Game\Repositories\GameUserRepository')->addGameUser($game->code, $member->code, $member);
						
						if ($res['success'] === true) {
							session()->flash('success', 'สมัครสมาชิกสำเร็จแล้ว ยินดีต้อนรับเข้าสู่ระบบ');
							Auth::guard('customer')->login($response);
							
							return redirect()->intended(route($this->_config['redirect']));
						} else {
							app('Gametech\Member\Repositories\MemberRepository')->delete($response->code);
							session()->flash('error', $res['msg']);
							
							return redirect()->back();
						}
					} else {
						session()->flash('success', 'สมัครสมาชิกสำเร็จแล้ว ยินดีต้อนรับเข้าสู่ระบบ');
						Auth::guard('customer')->login($response);
						
						return redirect()->intended(route($this->_config['redirect']));
						
					}
				}
			} else {
				
				session()->flash('success', 'ขณะนี้ข้อมูลการสมัครของท่าน อยู่ในกระบวนการตรวจสอบโดยทีมงาน เมื่อทีมงานดำเนินการเสร็จ ท่านสมาชิกจะสามารถเข้าสู่ระบบของเวบไซต์ได้');
				if ($config->verify_sms === 'Y') {
					return redirect()->route($this->_config['redirect'])->withInput(['user_name' => $username, 'password' => $pass]);
				} else {
					return redirect()->back();
				}
				
			}
			
		}
		
		protected function sendFailedLoginResponse(Request $request)
		{
			//        dd($request);
			$username = $request->input('user_name');
			$password = $request->input('password');
			Event::dispatch('customer.login.fail', $username . '|' . $password);
			
			session()->flash('error', Lang::get('app.login.fail'));
			
			return redirect()->back();
			
		}
		
		public function registerUser(Request $request)
		{
			$otp = '';
			$config = core()->getConfigData();
			
			$datenow = now()->toDateTimeString();
			$today = now()->toDateString();
			$ip = $request->ip();
			//        $data = $request->input();
			
			$data = $request->all();
			
			//        $data['user_name'] = Str::of($data['user_name'])->replaceMatches('/[^0-9]++/', '')->trim()->__toString();
			$username = strip_tags(strtolower($data['user_name']));
			
			$tel = Str::of($data['tel'])->replaceMatches('/[^0-9]++/', '')->trim()->__toString();
			
			$data['tel'] = $tel;
			
			$acc_no = Str::of($data['acc_no'])->replaceMatches('/[^0-9]++/', '')->trim()->__toString();
			$data['acc_no'] = $acc_no;
			$bank_code = $data['bank'];
			$data['wallet_id'] = strip_tags($data['tel']);
			
			$lineid = trim(strip_tags($data['lineid']));
			
			$wallet_id = trim($data['wallet_id']);
			
			if ($config->freecredit_all === 'Y') {
				$freecredit = 'Y';
			} else {
				$freecredit = 'N';
			}
			
			if ($config->verify_open === 'Y') {
				$verify = 'N';
				if ($config->verify_sms === 'Y') {
					$otp = rand(100001, 999999);
				}
				
			} else {
				$verify = 'Y';
			}
			
			//		dd($data);
			
			$validator = Validator::make($data, [
				'acc_no' => [
					'required',
					'digits_between:1,20',
					Rule::unique('members', 'acc_no')->where(function ($query) use ($bank_code) {
						return $query->where('bank_code', $bank_code);
					}),
				],
				//            'wallet_id' => [
				//                'required',
				//                Rule::unique('members', 'wallet_id')->where(function ($query) use ($wallet_id) {
				//                    return $query->where('wallet_id', $wallet_id);
				//                })
				//            ],
				'firstname' => 'required|alpha',
				'lastname' => ['required', 'regex:/^[\pL\s\-]+$/u'],
				'password' => 'required|min:6',
				//            'password_confirm' => 'min:6|same:password',
				'user_name' => 'required|alpha_num|different:tel|unique:members,user_name|max:10|regex:/^[a-z][a-z0-9]*$/',
				'wallet_id' => 'required|numeric|unique:members,wallet_id',
				'tel' => 'required|numeric|unique:members,tel',
				'bank' => 'required|numeric',
				'refer' => 'required|numeric',
				//            'g-recaptcha-response' => 'required'
			]);
			
			//        dd($validator);
			if ($validator->fails()) {
				
				if ($request->expectsJson() || $request->ajax()) {
					return $this->sendError(Lang::get('app.register.fail'), 200);
				}
				
				session()->flash('error', Lang::get('app.register.fail'));
				
				return redirect()->back()->withErrors($validator)->withInput();
				
			}
			
			Event::dispatch('customer.register.before', $data);
			
			$upline = '0';
			$team_id = null;
			$campaign_id = null;
			if (isset($data['marketing'])) {
				$marketing = $this->registerLinkRepository->findOneWhere(['code' => $data['marketing']]);
				if ($marketing) {
					$team_id = $marketing->team_id;
					$campaign_id = $marketing->campaign_id;
				}
				unset($data['marketing']);
			}
			
			$refer = $data['refer'];
			unset($data['refer']);
			
			$pass = $data['password'];
			//        $pass_confirm = $data['password_confirm'];
			//        unset($data['password_confirm']);
			unset($data['password']);
			$data['firstname'] = strip_tags($data['firstname']);
			$data['lastname'] = strip_tags($data['lastname']);
			$name = $data['firstname'] . ' ' . $data['lastname'];
			if (isset($data['promotion'])) {
				$pro = $data['promotion'];
			} else {
				$pro = 'N';
			}
			
			unset($data['g-recaptcha-response']);
			
			unset($data['bank']);
			if ($bank_code == 4) {
				$acc_check = substr($acc_no, -4);
			} else {
				$acc_check = substr($acc_no, -6);
			}
			$acc_bay = substr($acc_no, -7);
			
			$data = array_merge($data, [
				'password' => Hash::make($pass),
				'refer_code' => $refer,
				'upline_code' => $upline,
				'user_name' => $username,
				'user_pass' => $pass,
				'wallet_id' => $wallet_id,
				'tel' => $tel,
				'lineid' => $lineid,
				'acc_no' => $acc_no,
				'acc_check' => $acc_check,
				'acc_bay' => $acc_bay,
				'acc_kbank' => '',
				'bank_code' => $bank_code,
				'confirm' => $verify,
				'freecredit' => $freecredit,
				'check_status' => 'N',
				'promotion' => $pro,
				'name' => $name,
				'user_create' => $name,
				'user_update' => $name,
				'lastlogin' => $datenow,
				'date_regis' => $today,
				'birth_day' => $today,
				'session_limit' => null,
				'payment_limit' => null,
				'payment_delay' => null,
				'remark' => '',
				'gender' => 'M',
				'team_id' => $team_id,
				'campaign_id' => $campaign_id,
				'otp' => $otp,
				'ip' => $ip,
			]);
			
			$response = app('Gametech\Marketing\Repositories\MarketingMemberRepository')->create($data);
			
			if (!$response->code) {
				
				if ($request->expectsJson() || $request->ajax()) {
					return $this->sendError(Lang::get('app.register.fail2'), 200);
				}
				session()->flash('error', Lang::get('app.register.fail2'));
				
				return redirect()->back();
			}
			
			if ($config->verify_open == 'N') {
				
				if ($config->seamless == 'Y') {
					
					$game = app('Gametech\Game\Repositories\GameRepository')->findOneWhere(['enable' => 'Y', 'status_open' => 'Y', 'id' => 'seamless']);
					$member = app('Gametech\Member\Repositories\MemberRepository')->find($response->code);
					$res = app('Gametech\Game\Repositories\GameUserRepository')->addGameUser($game->code, $member->code, ['username' => $username, 'password' => $pass, 'name' => $name, 'user_create' => $name]);
					if ($res['success'] === true) {
						session()->flash('success', Lang::get('app.register.success'));
						if ($this->attemptLogin($request)) {
							return $this->sendLoginResponse($request);
						}
						
						return $this->sendFailedLoginResponse($request);
						
					} else {
						app('Gametech\Member\Repositories\MemberRepository')->delete($response->code);
						
						if ($request->expectsJson() || $request->ajax()) {
							return $this->sendError($res['msg'], 200);
						}
						
						session()->flash('error', $res['msg']);
						
						return redirect()->back();
					}
					
				} else {
					
					if ($config->multigame_open === 'N') {
						$game = app('Gametech\Game\Repositories\GameRepository')->findOneWhere(['enable' => 'Y', 'status_open' => 'Y']);
						$member = app('Gametech\Member\Repositories\MemberRepository')->find($response->code);
						$res = app('Gametech\Game\Repositories\GameUserRepository')->addGameUser($game->code, $member->code, $member);
						
						if ($res['success'] === true) {
							session()->flash('success', 'สมัครสมาชิกสำเร็จแล้ว ยินดีต้อนรับเข้าสู่ระบบ');
							Auth::guard('customer')->login($response);
							
							return redirect()->intended(route($this->_config['redirect']));
						} else {
							app('Gametech\Member\Repositories\MemberRepository')->delete($response->code);
							session()->flash('error', $res['msg']);
							
							return redirect()->back();
						}
					} else {
						session()->flash('success', 'สมัครสมาชิกสำเร็จแล้ว ยินดีต้อนรับเข้าสู่ระบบ');
						Auth::guard('customer')->login($response);
						
						return redirect()->intended(route($this->_config['redirect']));
						
					}
				}
			} else {
				
				session()->flash('success', 'ขณะนี้ข้อมูลการสมัครของท่าน อยู่ในกระบวนการตรวจสอบโดยทีมงาน เมื่อทีมงานดำเนินการเสร็จ ท่านสมาชิกจะสามารถเข้าสู่ระบบของเวบไซต์ได้');
				if ($config->verify_sms === 'Y') {
					return redirect()->route($this->_config['redirect'])->withInput(['user_name' => $username, 'password' => $pass]);
				} else {
					return redirect()->back();
				}
				
			}
			
		}
		
		public function checkUser(Request $request)
		{
			$user = $request->input('username');
			
			$data['user_name'] = $user;
			
			$validator = Validator::make($data, [
				'user_name' => 'required|alpha_num|unique:members,user_name',
			]);
			
			if ($validator->fails()) {
				return response()->json(['exists' => true, 'message' => __('app.register.cannot_use')]);
			}
			
			return response()->json(['exists' => false, 'message' => __('app.register.can_use')]);
		}
		
		public function checkPhone(Request $request)
		{
			if ($request->has('username') === false) {
				$phone = $request->input('tel');
			} else {
				$phone = $request->input('username');
			}
			
			$data['user_name'] = $phone;
			$data['tel'] = $phone;
			
			$validator = Validator::make($data, [
				'user_name' => 'required|numeric|unique:members,user_name',
				'tel' => 'required|numeric|unique:members,tel',
			]);
			
			if ($validator->fails()) {
				return response()->json(['exists' => true, 'message' => __('app.register.cannot_use')]);
			}
			
			return response()->json(['exists' => false, 'message' => __('app.register.can_use')]);
		}
		
		public function checkBank(Request $request)
		{
			$firstname = '';
			$lastname = '';
			$bank = $request->input('bank');
			$acc = $request->input('acc_no');
			//        $exists = MemberProxy::where('bank_code', $bank)->where('acc_no', $acc)->exists();
			
			//        return response()->json(['valid' => $exists]);
			
			$data['acc_no'] = $acc;
			$validator = Validator::make($data, [
				'acc_no' => [
					'required',
					'digits_between:1,14',
					Rule::unique('members', 'acc_no')->where(function ($query) use ($bank) {
						return $query->where('bank_code', $bank);
					}),
				],
			]);
			
			if ($validator->fails()) {
				return response()->json(['valid' => true, 'message' => __('app.register.cannot_use')]);
			}
			
			$postData = [
				"toBankAccNumber" => $acc,
				"toBankAccNameCode" => $this->Banks($bank)
			];
			
			$response = Http::withHeaders([
				'access-key' => 'c4604a14-6d12-4a85-8a8e-6243527f34-c5',
			])->post('https://me2me.biz/getname.php', $postData);
			
			if ($response->successful()) {
				
				$return = $response->json();
				if ($return['status']) {
					$fullname = $this->splitNameUniversal($return['data']['accountName']);
					$firstname = $fullname['firstname'];
					$lastname = $fullname['lastname'];
				} else {
					return response()->json(['valid' => true, 'message' => __('app.register.wrong')]);
				}
				
			}
			
			return response()->json(['valid' => false, 'firstname' => $firstname, 'lastname' => $lastname, 'message' => __('app.register.can_use')]);
		}
		
		public function Banks($bankcode)
		{
			
			switch ($bankcode) {
				case '1':
					$result = 'BBL';
					break;
				case '2':
					$result = 'KBANK';
					break;
				case '3':
					$result = 'KTB';
					break;
				case '4':
					$result = 'SCB';
					break;
				case '5':
					$result = 'GHB';
					break;
				case '6':
					$result = 'KK';
					break;
				case '7':
					$result = 'CIMB';
					break;
				case '19':
				case '15':
				case '10':
					$result = 'TTB';
					break;
				case '11':
					$result = 'BAY';
					break;
				case '12':
					$result = 'UOBT';
					break;
				case '13':
					$result = 'LHBANK';
					break;
				case '14':
					$result = 'GOV';
					break;
				case '17':
					$result = 'BAAC';
					break;
				default:
					$result = '500';
					break;
			}
			return $result;
			
		}
		
		public function splitNameUniversal($fullName)
		{
			// คำนำหน้าภาษาไทยและอังกฤษที่พบบ่อย
			$prefixes = [
				// ไทย
				'นาย', 'นาง', 'นางสาว', 'ดร.', 'น.ส.', 'น.',
				// อังกฤษ
				'Mr.', 'Mrs.', 'Ms.', 'Miss', 'Dr.', 'Prof.', 'Sir', 'Madam'
			];
			
			// ตรวจสอบและตัดคำนำหน้า (prefix) ออก
			foreach ($prefixes as $prefix) {
				// ใช้ mb_stripos แบบไม่ case sensitive และรองรับ multibyte
				if (mb_stripos($fullName, $prefix) === 0) {
					$fullName = trim(mb_substr($fullName, mb_strlen($prefix)));
					break;
				}
			}
			
			// แยกคำด้วย space (รองรับหลายช่องว่าง)
			$parts = preg_split('/\s+/', $fullName);
			
			$firstname = $parts[0] ?? '';
			$lastname = count($parts) > 1 ? $parts[count($parts) - 1] : '';
			
			return [
				'firstname' => $firstname,
				'lastname' => $lastname,
			];
		}
		
		public function username(): string
		{
			return 'user_name';
		}
		
		protected function authenticated(Request $request, $user)
		{
			$config = core()->getConfigData();
			
			Auth::guard('customer')->logoutOtherDevices(request('password'));
			
			Event::dispatch('customer.login.after', $user);
			
			if ($config->verify_open === 'Y') {
				
				if ($config->verify_sms === 'Y') {
					return redirect()->route('customer.verify.index');
				} else {
					session()->flash('success', 'ขณะนี้ข้อมูลการสมัครของท่าน อยู่ในกระบวนการตรวจสอบโดยทีมงาน เมื่อทีมงานดพเนินการเสร็จ ท่านสมาชิกจะสามารถเข้าสู่ระบบของเวบไซต์ได้');
					
					$this->logout($request);
				}
				
			} else {
				
				app('Gametech\Member\Repositories\MemberRepository')->update(['session_id' => request()->session()->getId()], $user->code);
				
				return redirect()->intended('/member');
			}
			
		}
		
		public function logout(Request $request)
		{
			$user = Auth::guard('customer')->user();
			
			$this->guard()->logout();
			
			$request->session()->invalidate();
			
			$request->session()->regenerate();
			
			app('Gametech\Member\Repositories\MemberRepository')->update(['session_id' => ''], $user->code);
			
			Event::dispatch('customer.logout.after', $user);
			
			if ($response = $this->loggedOut($request)) {
				return $response;
			}
			
			return $request->wantsJson()
				? new JsonResponse([], 204)
				: redirect('/');
		}
		
		protected function loggedOut(Request $request): RedirectResponse
		{
			
			return redirect()->route($this->_config['redirect']);
			
		}
	}
