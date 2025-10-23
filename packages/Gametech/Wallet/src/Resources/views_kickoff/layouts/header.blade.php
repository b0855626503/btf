<nav id="main-nav" class="navbar navbar-expand-sm navbar-light">
    <div class="container" style="max-height: 100%;">
        <div class="d-inline-flex align-items-center ham-menu">
            <button class="navbar-toggler p-0 " type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation" style="height: 35px; width: 35px;">
                <span class="bi bi-list bi-2x text-light"></span>
            </button>
        </div>
        <a href="{{ route('customer.session.index') }}" class="navbar-brand m-0 d-flex align-items-center not-login-stay-left">
            <img id="main-logo" src="{{ url(core()->imgurl($webconfig->logo,'img')) }}">
        </a>
        <div id="auth-wrapper" class="group-button-user p-1 rounded-pill login-b">
            <div class="d-inline-flex">
                <a href="{{ route('customer.session.store') }}" class="nav-link register-btn btn btn-custom-secondary rounded-pill d-flex align-items-center pt-1 pb-1 text-white justify-content-center homeregis" aria-label="register">
                      <span class="fw-bold text-highlight d-flex align-items-center">
                        <i class="bi bi-person-plus-fill me-1 text-white"></i> {{ __('app.login.register') }} </span>
                </a>
                <a href="{{ route('customer.session.index') }}" class="nav-link login-btn btn btn-custom-primary rounded-pill gradient d-flex align-items-center pt-1 pb-1 ms-2 justify-content-center homelogin" aria-label="login">
                      <span class="fw-bold text-highlight d-flex align-items-center">
                        <i class="bi bi-box-arrow-in-right me-1"></i> {{ __('app.login.login') }} </span>
                </a>
            </div>
        </div>
        <div class="collapse navbar-collapse navbar-content-index" id="navbarSupportedContent">
            <div class="navbar-nav ms-auto align-items-center">
                <li class="nav-item header-group-menu pt-3">
                    <span>Pages</span>
                </li>
                <li class="nav-item bg-box-1 nc-home btn-home ">
                    <a href="{{ route('customer.session.index') }}" class="nav-link btn btn-box-1 d-flex align-items-center btn-lg  position-relative">
                        <span class="text-highlight">{{ __('app.login.home') }}</span>
                    </a>
                </li>
                <li class="nav-item bg-box-1 line__ti_p_390ypsoj btn-contact ">
                    <a href="{{ $webconfig->linelink }}" class="nav-link btn btn-box-1 d-flex align-items-center btn-lg  position-relative" target="_blank">
                        <span class="text-highlight">{{ __('app.login.contact') }}</span>
                    </a>
                </li>

                <li class="nav-item bg-box-1 btn-language dropdown d-none d-md-block">
                    <a class="nav-link btn btn-box-1 d-flex align-items-center btn-lg position-relative dropdown-toggle"
                       href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="text-highlight">{{ __('app.login.language') }}</span>
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                        <li><a class="dropdown-item" href="{{ route('customer.home.lang', ['lang' => 'en']) }}"><img
                                        src="/images/flag/en.png" width="32" height="32"
                                        class="img img-fluid img-sm"> English</a>
                        </li>
                        <li><a class="dropdown-item" href="{{ route('customer.home.lang', ['lang' => 'th']) }}"><img
                                        src="/images/flag/th.png" width="32" height="32"
                                        class="img img-fluid img-sm"> ภาษาไทย</a></li>
                        <li><a class="dropdown-item" href="{{ route('customer.home.lang', ['lang' => 'kh']) }}"><img
                                        src="/images/flag/kh.png" width="32" height="32"
                                        class="img img-fluid img-sm"> ភាសាខ្មែរ</a></li>
                        <li><a class="dropdown-item" href="{{ route('customer.home.lang', ['lang' => 'la']) }}"><img
                                        src="/images/flag/la.png" width="32" height="32"
                                        class="img img-fluid img-sm"> ພາສາລາວ</a>
                        <li><a class="dropdown-item" href="{{ route('customer.home.lang', ['lang' => 'cn']) }}"><img
                                        src="/images/flag/cn.png" width="32" height="32"
                                        class="img img-fluid img-sm"> 中國人</a></li>
                        <li><a class="dropdown-item" href="{{ route('customer.home.lang', ['lang' => 'kr']) }}"><img
                                        src="/images/flag/kr.png" width="32" height="32"
                                        class="img img-fluid img-sm"> 한국어</a></li>
                    </ul>
                </li>


                <li class="nav-item header-group-menu pt-3">
                    <span>{{ __('app.login.language') }}</span>
                </li>
                <li class="nav-item bg-box-1 nc-home btn-language d-block d-md-none">
                    <a href="{{ route('customer.home.lang', ['lang' => 'en']) }}"
                       class="nav-link btn btn-box-1 d-flex align-items-center btn-lg position-relative">
                            <span class="text-highlight"><img
                                        src="/images/flag/en.png" width="32" height="32" class="img img-fluid img-sm"> English</span>
                    </a>
                </li>
                <li class="nav-item bg-box-1 nc-home btn-language d-block d-md-none">
                    <a href="{{ route('customer.home.lang', ['lang' => 'th']) }}"
                       class="nav-link btn btn-box-1 d-flex align-items-center btn-lg position-relative">
                            <span class="text-highlight"><img
                                        src="/images/flag/th.png" width="32" height="32" class="img img-fluid img-sm"> ภาษาไทย</span>
                    </a>
                </li>
                <li class="nav-item bg-box-1 nc-home btn-language d-block d-md-none">
                    <a href="{{ route('customer.home.lang', ['lang' => 'kh']) }}"
                       class="nav-link btn btn-box-1 d-flex align-items-center btn-lg position-relative">
                            <span class="text-highlight"><img
                                        src="/images/flag/kh.png" width="32" height="32" class="img img-fluid img-sm"> ភាសាខ្មែរ</span>
                    </a>
                </li>
                <li class="nav-item bg-box-1 nc-home btn-language d-block d-md-none">
                    <a href="{{ route('customer.home.lang', ['lang' => 'la']) }}"
                       class="nav-link btn btn-box-1 d-flex align-items-center btn-lg position-relative">
                            <span class="text-highlight"><img
                                        src="/images/flag/la.png" width="32" height="32" class="img img-fluid img-sm"> ພາສາລາວ</span>
                    </a>
                </li>
                <li class="nav-item bg-box-1 nc-home btn-language d-block d-md-none">
                    <a href="{{ route('customer.home.lang', ['lang' => 'cn']) }}"
                       class="nav-link btn btn-box-1 d-flex align-items-center btn-lg position-relative">
                            <span class="text-highlight"><img
                                        src="/images/flag/cn.png" width="32" height="32" class="img img-fluid img-sm"> 中國人</span>
                    </a>
                </li>
                <li class="nav-item bg-box-1 nc-home btn-language d-block d-md-none">
                    <a href="{{ route('customer.home.lang', ['lang' => 'kr']) }}"
                       class="nav-link btn btn-box-1 d-flex align-items-center btn-lg position-relative">
                            <span class="text-highlight"><img
                                        src="/images/flag/kr.png" width="32" height="32" class="img img-fluid img-sm"> 한국어</span>
                    </a>
                </li>

            </div>
        </div>
    </div>
</nav>