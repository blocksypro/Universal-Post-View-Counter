jQuery(document).ready(function ($) {
    $.ajax({
        url: upvc_ajax_obj.ajax_url,
        type: 'POST',
        data: {
            action: 'upvc_count_post_view',
            post_id: upvc_ajax_obj.post_id
        },
        success: function (response) {
            if (response.success) {
                console.log('View count updated.');
            }
        },
        error: function () {
            console.log('Error updating view count.');
        }
    });
});