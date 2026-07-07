
    "use strict";
    $(document).on('ready', function () {
        $('.js-flatpickr').each(function () {
            $.HSCore.components.HSFlatpickr.init($(this));
        });
    });

    $('#start_date,#end_date').change(function () {
        let fr = $('#start_date').val();
        let to = $('#end_date').val();
        if (fr != '' && to != '') {
            if (fr > to) {
                $('#start_date').val('');
                $('#end_date').val('');
                toastr.error('Invalid date range!', Error, {
                    CloseButton: true,
                    ProgressBar: true
                });
            }
        }
    });

    $("#date_type").change(function() {
        let val = $(this).val();
        $('#start_date_div').toggle(val === 'custom_date');
        $('#end_date_div').toggle(val === 'custom_date');

        if(val === 'custom_date'){
            $('#start_date').attr('required','required');
            $('#end_date').attr('required','required');
        }else{
            $('#start_date').val(null).removeAttr('required')
            $('#end_date').val(null).removeAttr('required')
        }
    }).change();
