$(document).ready(function() {
    $(".module-permission").on('change', function (){
        if ($(".module-permission:checked").length == $(".module-permission").length) {
            $("#select_all").prop("checked", true);
        } else {
            $("#select_all").prop("checked", false);
        }
    });

    $("#select_all").on('change', function (){
        if ($("#select_all").is(":checked")) {
            $(".module-permission").prop("checked", true);
        } else {
            $(".module-permission").prop("checked", false);
        }
    });

    if ($(".module-permission:checked").length == $(".module-permission").length) {
        $("#select_all").prop("checked", true);
    }
});
