$(document).on('ready', function () {
    $('.js-flatpickr').each(function () {
        $.HSCore.components.HSFlatpickr.init($(this));
    });
});

$('#start_date,#expire_date').change(function () {
    let fr = $('#start_date').val();
    let to = $('#expire_date').val();
    if (fr != '' && to != '') {
        if (fr > to) {
            $('#start_date').val('');
            $('#expire_date').val('');
            toastr.error('Invalid date range!', Error, {
                CloseButton: true,
                ProgressBar: true
            });
        }
    }
});

$('.change-discount-type').on('change', function (){
    let type = $(this).val()
    if (type === 'amount') {
        $("#max_amount_div").hide();
    } else {
        $("#max_amount_div").show();
    }
})

$(document).ready(function() {
    $('form').on('reset', function(e) {
        $("#max_amount_div").show();
    });
});
