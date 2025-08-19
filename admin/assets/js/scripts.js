
(function ($)
{
    //pegj_

    /**
     * @todo JS library Concept for ajax request implementation
     */
    // const pegjConfig = new Pegj({
    //    '.button': {
    //        'data': {},
    //        'trigger': 'click',
    //        'networkTrigger': 'testTrigger',
    //        'eventTrigger': 'testEventTrigger',
    //        'createTrigger': 'createTriggerTest',
    //        'ajaxHandle': 'show_hello_world',
    //        'output': '.show-output',
    //        'outputHandler': 'show_output',
    //    }
    // });
    //
    // function show_output(data, html)
    // {
    //     return html;
    // }


    $(document).ready(function($)
    {
        console.log(wp_plugin_safety_validator_ajax);

        $('.el-not-ready').removeClass('el-not-ready');

        $(document).on('click', 'a.wp_plugin_safety_validator-scan_button:not(.wp-psv-scanning):not(.wp-psv-scan_complete)', function(e) {
            e.preventDefault();
            const btn = $(this);
            btn.addClass('wp-psv-scanning');
            $.ajax({
                url: wp_plugin_safety_validator_ajax.ajax_url,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: 'wp_plugin_safety_validator_scan_plugin',
                    nonce: wp_plugin_safety_validator_ajax.nonce.scan_plugin,
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

                            const pluginEl = $('tr[data-slug="'+ btn.attr('data-slug') +'"][data-plugin="'+ btn.attr('data-plugin_file') +'"]:not([id])');
                            const targetEl = pluginEl[0];
                            if (!targetEl) return;

                            targetEl.insertAdjacentHTML('afterend', response.data.html.trim());

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