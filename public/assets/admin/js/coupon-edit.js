var initialSelect2Value = $('.js-select2-custom').val();
$('form').on('reset', function() {
    this.reset();
    $('.js-select2-custom').val(initialSelect2Value).trigger('change');
});

$("#discount_type").change(function(){
    if(this.value === 'amount') {
        $("#max_discount_div").hide();
    }
    else if(this.value === 'percent') {
        $("#max_discount_div").show();
    }
});

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

$('.coupon-type').change(function() {
    let order_type = (this.value);
    if(order_type==='first_order'){
        $('#user-limit').removeAttr('required');
        $('#limit-for-user').hide();

        $('#customer_id').removeAttr('required',true);
        $('#customer_div').addClass('d-none');

        $('#discount_type_div').show();

        $('#discount_amount_div').show();

        // $('#max_discount_div').show();
    }
    else if(order_type==='customer_wise'){
        $('#user-limit').prop('required');
        $('#limit-for-user').show();

        $('#customer_id').prop('required', true);
        $('#customer_div').removeClass('d-none');

        $('#discount_type_div').show();

        $('#discount_amount_div').show();

        //$('#max_discount_div').show();
    }
    else if(order_type==='free_delivery'){
        $('#user-limit').prop('required');
        $('#limit-for-user').show();

        $('#customer_id').removeAttr('required',true);
        $('#customer_div').addClass('d-none');

        $('#discount_type_div').hide();

        $('#discount_amount_div').hide();

        //$('#max_discount_div').hide();
    }
    else{
        $('#user-limit').prop('required',true);
        $('#limit-for-user').show();

        $('#customer_id').removeAttr('required',true);
        $('#customer_div').addClass('d-none');

        $('#discount_type_div').show();

        $('#discount_amount_div').show();

        //$('#max_discount_div').show();
    }
})
