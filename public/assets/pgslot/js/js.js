const baseUrl = document.getElementById("mainscript").getAttribute('baseUrl');

$('#btnstep01').on('click', function () {
    let url = baseUrl + '/check/step01';
    const input = document.querySelector('input[name=user_name]');
    console.log(input.value);
    if (input.value === "") {
        return false;
    }
    const getResponse = async (input) => {
        try {
            const response = await axios.post(url, { user_name : input.value });
            console.log(response.data);
            if (response.data.success) {
                $('.re01').hide();
                $('.re02').show();
                $('.stepregis.step01').removeClass("active");
                $('.stepregis.step02').addClass("active");
            }else{
                Toast.fire({
                    icon: 'error',
                    title: response.data.message
                });

            }

        } catch (err) {
            console.log('err')
        }
    }
    getResponse(input);

});
$('#btnstep02').on('click', function () {

    const password = document.querySelector('input[name=password]');
    const confirm = document.querySelector('input[name=password_confirmation]');
    if (confirm.value !== password.value) {
        Toast.fire({
            icon: 'error',
            title: 'รหัสผ่าน ไม่ตรงกัน'
        });
        return false;
    }

    if (password.value === "") {
        return false;
    }
    const getResponse = async (password,confirm) => {
        try {
            let url = baseUrl + '/check/step02';
            const response = await axios.post(url, { password: password.value, password_confirmation: confirm.value });
            if (response.data.success) {
                $('.re02').hide();
                $('.re03').show();
                $('.stepregis.step02').removeClass("active");
                $('.stepregis.step03').addClass("active");
            }else{
                Toast.fire({
                    icon: 'error',
                    title: response.data.message
                });
                // input.setCustomValidity(response.data.message);
            }

        } catch (err) {
            console.log('err')
        }
    }
    getResponse(password,confirm);

});
$('#btnstep03').on('click', function () {

    const getResponse = async () => {
        try {
            let url = baseUrl + '/register/api';
            var formEl = document.forms.frmregister;
            var formData = new FormData(formEl);
            const response = await axios.post(url, formData);
            if (response.data.success) {
                window.location.href = baseUrl+'/member';
            }else{
                Swal.fire(
                    'ผลการสมัคร',
                    response.data.message,
                    'error'
                );
                $('.re01').show();
                $('.re02').hide();
                $('.re03').hide();
                $('.stepregis.step01').addClass("active");
                $('.stepregis.step02').removeClass("active");
                $('.stepregis.step03').removeClass("active");
                $('#frmregister').trigger("reset");
            }

        } catch (err) {
            console.log('err')
        }
    }
    $('.re01').hide();
    $('.re02').hide();
    $('.re03').hide();
    $('.re04').show();
    $('.stepregis.step03').removeClass("active");
    $('.stepregis.step04').addClass("active");

    setTimeout(function () {
        getResponse();
    }, 2000);
});

if ($(".header-menu")[0]) {
    $('.linebtn').addClass("logined");
} else {

}


// Copy---------------------------------------------------------
$(document).ready(function () {
    $(".copybtn").click(function (event) {
        var $tempElement = $("<input>");
        $("body").append($tempElement);
        $tempElement.val($(this).closest(".copybtn").find("span").text()).select();
        document.execCommand("Copy");
        $tempElement.remove();

    });
});

function copylink() {
    $(".alertcopy").show();
    setTimeout(function () {
        $(".alertcopy").hide();
    }, 2000);
}


$(".copylink").click(function (event) {
    var copyText = document.getElementById("friendlink");
    copyText.select();
    copyText.setSelectionRange(0, 99999)
    document.execCommand("copy");

});


// Copy---------------------------------------------------------


// Main Tab
function openTab(evt, cityName) {
    var i, tabcontent, tablinks;
    tabcontent = document.getElementsByClassName("tabcontent");
    for (i = 0; i < tabcontent.length; i++) {
        tabcontent[i].style.display = "none";
    }
    tablinks = document.getElementsByClassName("navmenu");
    for (i = 0; i < tablinks.length; i++) {
        tablinks[i].className = tablinks[i].className.replace(" active", "");
    }
    document.getElementById(cityName).style.display = "block";
    evt.currentTarget.className += " active";
    if ($("#homepage").css("display") == "block") {
        $(".navmenu.play").addClass('active');
    }
    if ($("#dps").css("display") == "block") {
        $(".navmenu.deposit").addClass('active');
    }
    if ($("#wd").css("display") == "block") {
        $(".navmenu.withdraw").addClass('active');
    }
    if ($("#history").css("display") == "block") {
        $(".navmenu.history").addClass('active');
    }
    if ($("#friend").css("display") == "block") {
        $(".navmenu.friend").addClass('active');
    }

    // if ($("#fortune").css("display") == "block") {
    //     $(".navmenu.play").addClass('active');
    // }
    $('.sidebarCollapse').removeClass('open');
    $('.menuslidebox').removeClass('open');


}
if(document.getElementById("defaultOpen")) {
    document.getElementById("defaultOpen").click();
}
// Main Tab


// SIDEBAR TAB


$('.sporttab').on('click', function () {
    $(".customgametab .nav-link").removeClass('active');
    $("#pills-sport-tab").addClass('active');
    $("#pills-tabContent .tab-pane").removeClass('show active');
    $("#pills-tabContent #pills-sport").addClass('fade show active');
});
$('.casinotab').on('click', function () {
    $(".customgametab .nav-link").removeClass('active');
    $("#pills-Casino-tab").addClass('active');
    $("#pills-tabContent .tab-pane").removeClass('show active');
    $("#pills-tabContent #pills-Casino").addClass('fade show active');
});
$('.slottab').on('click', function () {
    $(".customgametab .nav-link").removeClass('active');
    $("#pills-slot-tab").addClass('active');
    $("#pills-tabContent .tab-pane").removeClass('show active');
    $("#pills-tabContent #pills-slot").addClass('fade show active');
});
$('.lottotab').on('click', function () {
    $(".customgametab .nav-link").removeClass('active');
    $("#pills-lotto-tab").addClass('active');
    $("#pills-tabContent .tab-pane").removeClass('show active');
    $("#pills-tabContent #pills-lotto").addClass('fade show active');
});


// SIDEBAR TAB


// cryptotab


function cryptotab() {
    $(".boxcrypto.dps01").hide();
    $('.boxcrypto.dps02').css("display", "flex");
}

function cryptotabclose() {
    $(".boxcrypto.dps02").hide();
    $('.boxcrypto.dps01').css("display", "flex");
}

$('.wdtablink.bank').on('click', function () {
    $('.wdtablink').removeClass('active');
    $(this).addClass('active');
    $('.boxwd').hide();
    $('.boxwd.bank').show();

});
$('.wdtablink.crypto').on('click', function () {
    $('.wdtablink').removeClass('active');
    $(this).addClass('active');
    $('.boxwd').hide();
    $('.boxwd.crypto').css("display", "flex");
});


$('.coin-item.busd').on('click', function () {
    $('.box_input').hide();
    $('.box_input.busd').css("display", "block");
});
$('.coin-item.usdt').on('click', function () {
    $('.box_input').hide();
    $('.box_input.usdt').css("display", "block");
});

// cryptotab


// Change Password

function changepassword() {
    $('.containcpass').show();
    $('.accountdetail').hide();
}

$('.backaccount').on('click', function () {
    $('.containcpass').hide();
    $('.accountdetail').show();
});
// Change Password


// Main Friend
// tabs friend---------------------------------------------------------
function openfriendtab(evt, cityName) {
    var i, tabcontent, tablinks;
    tabcontent = document.getElementsByClassName("containfriendwd");
    for (i = 0; i < tabcontent.length; i++) {
        tabcontent[i].style.display = "none";

    }
    tablinks = document.getElementsByClassName("ininwrapgrid001");
    for (i = 0; i < tablinks.length; i++) {
        tablinks[i].className = tablinks[i].className.replace(" active", "");

    }
    document.getElementById(cityName).style.display = "block";
    evt.currentTarget.className += " active";
    if (cityName === 'allfriend') {
        let url = baseUrl + '/member/contributor/api';
        var amount = 0;
        const getResponse = async () => {
            try {
                const response = await axios.get(url);
                $('.friend_sum').text(response.data.data.downs_count);
                $('.friend_sum_deposit').text(response.data.data.payments_promotion_count);
                if (response.data.data.payments_promotion_credit_bonus_sum) {
                    amount = response.data.data.payments_promotion_credit_bonus_sum;
                }
                $('.friend_sum_faststart').text(amount);
                $('.friend_percent').text(response.data.data.percent);

            } catch (err) {
                console.log('err')
            }
        }
        getResponse();
    }

    if (cityName === 'friendtabs') {
        let url = baseUrl + '/member/contributor';
        var input = 'contributor';
        $('.friendzone').html('');
        const getResponse = async (input) => {
            try {

                const response = await axios.post(url, {id: input});
                if (response.data.success) {
                    $.each(response.data.data, function (index, data) {

                        let $element = $("#friend-theme").html();

                        $element = $element.replace("{id}", data.id)
                            .replaceAll("{image}", data.image)
                            .replaceAll("{amount}", data.amount)
                            .replaceAll("{datetime}", data.date_create);

                        $('.friendzone').append($element);
                    });

                }

            } catch (err) {
                console.log('err')
            }
        }
        getResponse(input);
    }


    if (cityName === 'moneyfriendtabs') {
        let url = baseUrl + '/member/contributor';
        var input = 'contributor_income';
        $('.friendmoneyzone').html('');
        const getResponse = async (input) => {
            try {

                const response = await axios.post(url, {id: input});
                if (response.data.success) {
                    $.each(response.data.data, function (index, data) {

                        let $element = $("#friendmoney-theme").html();

                        $element = $element.replace("{id}", data.id)
                            .replaceAll("{image}", data.image)
                            .replaceAll("{amount}", data.amount)
                            .replaceAll("{datetime}", data.date_create);

                        $('.friendmoneyzone').append($element);
                    });

                }

            } catch (err) {
                console.log('err')
            }
        }
        getResponse(input);

    }


}
if(document.getElementById("tabfriendopen")) {
    document.getElementById("tabfriendopen").click();
}


// Endtabs friend---------------------------------------------------------

// Main Friend


var swiper = new Swiper("#lastSlide", {
    slidesPerView: 'auto',
    spaceBetween: 15,
    freeMode: true,
});


$(".sidebarCollapse").click(function (event) {
    $('.sidebarCollapse').toggleClass('open');
    $('.menuslidebox').toggleClass('open');

});


//  Promotion Slide
var swiper = new Swiper(".prosw", {
    slidesPerView: "auto",
    centeredSlides: true,
    spaceBetween: 30,
    effect: "coverflow",
    grabCursor: true,
    initialSlide: 1,
    coverflowEffect: {
        rotate: 0,
        stretch: 0,
        depth: 500,
        modifier: 1,
        slideShadows: true,
    },
    navigation: {
        nextEl: ".btnrightslide",
        prevEl: ".btnleftslide",
    },
});
//  Promotion Slide


// $(document).ready(function () {
//     var swiper = new Swiper(".mypromotion", {
//         slidesPerView: "auto",
//         spaceBetween: 30,
//         loop: true,
//         pagination: {
//             el: ".swiper-pagination",
//             clickable: true,
//         },
//         navigation: {
//             nextEl: ".swiper-button-next",
//             prevEl: ".swiper-button-prev",
//         },
//     });
// });