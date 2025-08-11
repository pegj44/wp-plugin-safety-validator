
(function ($)
{
    $(document).ready(function($)
    {
        $('.el-not-ready').removeClass('el-not-ready');

        $(document).on('click', 'a.wp-plugin-safety-validator-scan_button:not(.wp-psv-scanning):not(.wp-psv-scan_complete)', function(e) {
            e.preventDefault();
            const btn = $(this);
            btn.addClass('wp-psv-scanning');
            $.ajax({
                url: wp_plugin_safety_validator_scripts.ajax_url,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: wp_plugin_safety_validator_scripts.scan_action,
                    nonce: wp_plugin_safety_validator_scripts.scan_nonce,
                    plugin_file: $(this).data('plugin_file'),
                    slug: $(this).data('slug'),
                    version: $(this).data('version'),
                },
                success: function(response) {
                    btn.removeClass('wp-psv-scanning');
                    btn.addClass('wp-psv-scan_complete');
                    console.log(response);

                    if (response.success) {
                        if (response.data.results.length > 0) {
                            btn.addClass('wp-psv-scan_complete_with_issues');
                        } else {
                            btn.addClass('wp-psv-scan_complete_no_issues');
                        }
                    } else {
                        btn.addClass('wp-psv-scan_error');
                    }
                },
                error: function(response) {}
            });
        });

    });
})(jQuery);