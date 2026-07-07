function readURL(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();

        reader.onload = function (e) {
            $('#viewer').attr('src', e.target.result);
        }

        reader.readAsDataURL(input.files[0]);
    }
}

$("#customFileEg1").change(function () {
    readURL(this);
});

$('.show-item').on('change', function(){
    let type = $(this).val();
    console.log(type);
    show_item(type);
})

function show_item(type) {
    if (type === 'product') {
        $("#type-product").show();
        $("#type-category").hide();
    } else {
        $("#type-product").hide();
        $("#type-category").show();
    }
}

$(document).ready(function() {
    $('form').on('reset', function(e) {
        $("#type-product").show();
        $("#type-category").hide();
    });
});
