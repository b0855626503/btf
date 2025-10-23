<?php

namespace Gametech\Admin\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PragmaRX\Google2FALaravel\Support\Authenticator;

class LoginController extends AppBaseController
{
    /**
     * เก็บคอนฟิก view จาก routes (ถ้าใช้รูปแบบ _config)
     * @var array|null
     */
    protected $_config;

    /**
     * หลังล็อกอินสำเร็จให้ไปที่ไหน (ใช้กับ intended() เป็นค่า fallback)
     */
    protected string $redirectTo = '/admin';

    public function __construct()
    {
        // หน้า logout ต้องเป็นคนที่ผ่าน admin middleware เท่านั้น
        $this->middleware('auth:admin')->only('logout');

        // เก็บค่า _config จาก route ถ้ามีใช้งาน
        $this->_config = request('_config');
    }

    /**
     * ชื่อช่อง username ที่ใช้ล็อกอิน
     */
    public function username(): string
    {
        return 'user_name';
    }

    /**
     * หน้าแสดงฟอร์มล็อกอิน
     */
    public function show()
    {

        if (Auth::guard('admin')->check()) {

            // ถ้ายังไม่ผ่าน 2FA (หากคุณใช้ตัวนี้) ก็เด้งไปตั้งค่า/ยืนยัน 2FA
            // if (!session('2fa_passed')) {
            //     return redirect()->route('admin.2fa.show');
            // }
            return redirect()->route('admin.2fa.setting');
        }

        $current = 'ขณะนี้ระบบเป็น v 1.0.0';

        $view = $this->_config['view'];
        return view($view, compact('current'));
    }

    /**
     * ทำการล็อกอินด้วย guard:admin แบบ manual (ไม่พึ่ง LoginRequest ของ Fortify)
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            $this->username() => ['required', 'string'],
            'password'        => ['required', 'string'],
        ]);


        // พยายามล็อกอินด้วย guard 'admin'
        if (! Auth::guard('admin')->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                $this->username() => __('ไม่สามารถเข้าสู่ระบบได้ โปรดตรวจสอบชื่อผู้ใช้/รหัสผ่าน'),
            ])->redirectTo(url()->previous());
        }

        // regenerate session เพื่อความปลอดภัย
        $request->session()->regenerate();



        // ถ้าต้องการไล่อุปกรณ์อื่นออก ใช้รหัสผ่านล่าสุด
        try {
            Auth::guard('admin')->logoutOtherDevices($credentials['password']);
        } catch (\Throwable $e) {
            // ข้ามถ้า hashing driver ไม่รองรับ
        }

        $user = Auth::guard('admin')->user();

        // event หลังล็อกอิน
        Event::dispatch('admin.login.after', $user);

//        dd($user);

        // ถ้าใช้ 2FA: ยังไม่ผ่านให้เด้งไปหน้า verify/setup
        // if (!session('2fa_passed')) {
        //     return redirect()->route('admin.2fa.show');
        // }

//        if (!$user->google2fa_secret ||!$user->google2fa_enable) {
//            dd($user);
//            return redirect()->route('admin.2fa.setting');
////            return redirect()->intended('auth');
//        }


//        dd(Auth::guard('admin')->user());
        // สำเร็จ → กลับหน้าเดิมที่ต้องการ หรือ dashboard
        return redirect()->intended('/');
    }

    /**
     * ออกจากระบบ (guard:admin)
     */
    public function logout(Request $request): JsonResponse|RedirectResponse
    {
        // สำคัญ: logout ต้องเรียกที่ guard ไม่ใช่ที่ $user
        Auth::guard('admin')->logout();

        // เคลียร์ session/CSRF
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // ถ้าใช้ pragmarx/google2fa-laravel อยู่ ให้เคลียร์สถานะด้วย
        try {
            (new Authenticator($request))->logout();
        } catch (\Throwable $e) {
            // ignore
        }

        // event หลังออกจากระบบ
        Event::dispatch('admin.logout.after');

        if ($response = $this->loggedOut($request)) {
            return $response;
        }

        return $request->wantsJson()
            ? new JsonResponse([], 204)
            : redirect()->route('admin.session.index');
    }

    protected function loggedOut(Request $request): RedirectResponse
    {
        // กลับหน้า login ของ admin เสมอ
        return redirect()->route('admin.session.index');
    }

    /**
     * ใช้ guard:admin เสมอ
     */
    protected function guard()
    {
        return Auth::guard('admin');
    }
}
