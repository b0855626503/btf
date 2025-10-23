function LoadHistory(method) {
    let url = baseUrl + '/member/history';
    axios.post(url, {id: method})
        .then(response => {
            // console.log(response.data.data);
            $('.historydata').html('');
            $.each(response.data.data, function (index, data) {

                let $element = $("#history-theme").html();

                $element = $element.replace("{method}", data.method)
                    .replaceAll("{billid}", data.id)
                    .replaceAll("{status}", data.status_display)
                    .replaceAll("{image}", data.image)
                    .replaceAll("{amount}", data.amount)
                    .replaceAll("{datetime}", data.date_create);

                $('.historydata').append($element);
            });

        })
        .catch(response => {

        });
}

$(document).ready(function () {
    $('input#month').on('change', function (e) {
        let url = baseUrl + '/member/contributor';
        var input = 'contributor_income';
        var date = $(this).val();
        $('.friendmoneyzone').html('');
        const getResponse = async (input, date) => {
            try {

                const response = await axios.post(url, {id: input, date_start: date + '-01', date_stop: date + '-31'});
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
        getResponse(input, date);

    });

    $("form#frmwithdraw").on('click', 'button[type="button"]', function() {

        var input = $("#frmwithdraw :input[name='amount']").val();
        let url = baseUrl + '/member/withdraw/requestapi';

        console.log(input);
        if (isNaN(input)) {
            return false;
        }
        if (input === 0 || input === "") {
            return false;
        }

        $("form#frmwithdraw").find('button').prop("disabled", true);
        axios.post(url, {amount: input})
            .then(response => {
                if (response.data.success) {
                    Toast.fire({
                        icon: 'success',
                        title: response.data.message
                    })


                } else {

                    Toast.fire({
                        icon: 'error',
                        title: response.data.message
                    })

                }
                $('.btnbalance').trigger('click');
                setTimeout(function () {
                    $("form#frmwithdraw").find('input').val('');
                    $("form#frmwithdraw").find('button').prop("disabled", false);
                }, 2000);


            })
            .catch(response => {

                setTimeout(function () {
                    $("form#frmwithdraw").find('input').val('');
                    $("form#frmwithdraw").find('button').prop("disabled", false);
                }, 2000);

            });


    });

});

$('.-btn-balance-normal').click(function (e) {
    $('.-btn-balance .fa-sync-alt').addClass("fa-spin");
    $('.-btn-balances .fa-sync-alt').addClass("fa-spin");
    $('.-btn-balances .fa-sync-alt').addClass("detox");
    let url = baseUrl + '/member/loadcredit';
    const getResponse = async () => {
        try {
            const response = await axios.get(url);
            $('.wallet_amount').text(response.data.profile.balance);
            $('.point_amount').text(Math.floor(response.data.profile.point_deposit));
            $('.diamond_amount').text(Math.floor(response.data.profile.diamond));
            $('.cashback_amount').text(Math.floor(response.data.profile.cashback));
            $('.bonus_amount').text(Math.floor(response.data.profile.bonus));
            $('.ic_amount').text(Math.floor(response.data.profile.ic));
            $('.faststart_amount').text(Math.floor(response.data.profile.faststart));
            setTimeout(function () {
                $('.-btn-balance .fa-sync-alt').removeClass("fa-spin");
                $('.-btn-balances .fa-sync-alt').removeClass("fa-spin");
                $('.-btn-balances .fa-sync-alt').removeClass("detox");
            }, 2000);

        } catch (err) {
            console.log('err')
        }
    }
    getResponse();

});

$('#tabfriendopen').click(function (e) {

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

});

$('.getpro').click(function (e) {

    let url = baseUrl + '/member/promotion/api';
    var input = $(this).attr("data-id");
    console.log(input);
    const getResponse = async (input) => {
        try {

            const response = await axios.post(url, {promotion: input});
            if (response.data.success) {
                Toast.fire({
                    icon: 'success',
                    title: response.data.message
                })

                setTimeout(function () {
                    window.location.href = window.location;
                }, 3000);

            } else {

                Toast.fire({
                    icon: 'error',
                    title: response.data.message
                })

            }


        } catch (err) {
            console.log('err')
        }
    }
    getResponse(input);

});

$("form#frmchangepass").submit(function (event) {

    const password = document.querySelector('input[name=password]');
    const confirm = document.querySelector('input[name=password_confirmation]');
    if (confirm.value !== password.value) {
        confirm.setCustomValidity('รหัสผ่าน ไม่ตรงกัน');
    }

    let url = baseUrl + '/member/profile/changepass/api';


    if (password.value === "") {
        return false;
    }

    $("form#frmchangepass").find('button').prop("disabled", true);
    axios.post(url, {password: password.value, password_confirmation: confirm.value})
        .then(response => {
            if (response.data.success) {
                Toast.fire({
                    icon: 'success',
                    title: response.data.message
                })


            } else {

                Toast.fire({
                    icon: 'error',
                    title: response.data.message
                })

            }

            setTimeout(function () {
                $("form#frmchangepass").find('input').val('');
                $("form#frmchangepass").find('button').prop("disabled", false);
            }, 2000);


        })
        .catch(response => {

            setTimeout(function () {
                $("form#frmchangepass").find('input').val('');
                $("form#frmchangepass").find('button').prop("disabled", false);
            }, 2000);

        });


});


function trans(key, replace = {}) {
    var translation = key.split('.').reduce((t, i) => t[i] || null, window.i18n);

    for (var placeholder in replace) {
        translation = translation.replace(`:${placeholder}`, replace[placeholder]);
    }
    return translation;
}

function openPopup(id, msg) {
    let url = baseUrl + '/member/transfer/bonus/confirm';

    Swal.fire({
        title: trans('app.bonus.word') + msg + trans('app.bonus.word2'),
        html: trans('app.bonus.confirm'),
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: trans('app.bonus.yes'),
        cancelButtonText: trans('app.bonus.no'),
        allowOutsideClick: false,
        allowEscapeKey: false,
        allowEnterKey: false
    }).then((result) => {
        if (result.isConfirmed) {
            axios.post(url, {
                id: id
            }).then(response => {
                if (response.data.success) {
                    Swal.fire(
                        trans('app.bonus.success'),
                        response.data.message,
                        'success'
                    );
                    setTimeout(() => {
                        window.location.href = window.location;
                    }, 2000);
                } else {
                    Swal.fire(
                        trans('app.bonus.fail'),
                        response.data.message,
                        'error'
                    );
                }

            }).catch(err => [err]);
        }
        setTimeout(function () {
            $('button.js-promotion-apply').prop("disabled", false);
        }, 2000);
    })
}