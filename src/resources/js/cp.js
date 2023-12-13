$('.putyourlightson\\\\blitz\\\\widgets\\\\cachewidget .action .heading').click(function() {
    $(this).closest('.action').find('.form').toggleClass('hidden');
});

if ($('.blitz-diagnostics #crumbs').length) {
    $('#global-header #crumbs').replaceWith($('.blitz-diagnostics #crumbs').removeClass('hidden'));
}
