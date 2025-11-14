jQuery(document).ready(function ($) {
    // Target trash links in the page/post list
    $('a.submitdelete').on('click', function (e) {
        if (!confirm('Are you sure you want to move this item to the trash?')) {
            e.preventDefault(); // Stop the link from being followed
        }
    });

    // Target "Move to trash" link in the block editor
    // This selector is more complex and can change with WP versions
    $(document).on(
        'click',
        '.editor-post-trash button, .components-button.editor-post-trash',
        function (e) {
            if (!confirm('Are you sure you want to move this item to the trash?')) {
                e.preventDefault(); // Stop the action
                return false;
            }
        }
    );
});
