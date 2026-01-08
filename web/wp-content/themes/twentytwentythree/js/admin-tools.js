jQuery(document).ready(function ($) {
    // Target trash links in the page/post list
    $('a.np-btn-trash').on('click', function (e) {
        if (!confirm('Are you sure you want to move this item to the trash?')) {
            e.preventDefault(); // Stop the link from being followed
        }
    });
});
