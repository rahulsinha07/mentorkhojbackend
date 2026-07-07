$('.js-select2-custom').each(function () {
    var select2 = $.HSCore.components.HSSelect2.init($(this));
});

$('.print-button').on('click', function() {
    printDiv('printableArea');
});

function printDiv(divName) {
    var printContents = document.getElementById(divName).innerHTML;
    document.body.innerHTML = printContents;
    window.print();
    location.reload();
}
