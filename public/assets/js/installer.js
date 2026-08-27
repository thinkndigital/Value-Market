$.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
    }
});
$(document).on('submit', '.form-submit-event', function (e) {
    e.preventDefault();
    let formData = new FormData(this);
    $.ajax({
        type: "POST",
        url: $(this).attr('action'),
        data: formData,
        cache: false,
        contentType: false,
        processData: false,
        // dataType: 'json',
        success: function (response) {
            if (response.error == true) {

                if (response.message instanceof Array) {
                    $.each(response.message, function (key, value) {
                        iziToast.error({
                            message: value,
                            position: "topRight",
                        });
                        return false;
                    });
                } else {

                    iziToast.error({
                        message: response.message,
                        position: "topRight",
                    });
                }
                return false;
            }
            $("#complete_installation_btn").click();
            iziToast.success({
                message: response.message,
                position: "topRight",
            });
            setTimeout(() => {
                if (response.install != undefined) {
                    if (response.install == true) {
                        location.reload();
                    }
                }
            }, 2000);
        }
    });
});