
(function ($)
{
    // networkTrigger: 'testTrigger',
    // eventTrigger: 'testEventTrigger',
    // createTrigger: 'createTriggerTest',

    $(document).ready(function($)
    {
        $('.el-not-ready').removeClass('el-not-ready');

        new Pegj({
            'a.wp_plugin_safety_validator-scan_button:not(.wp-psv-scanning):not(.wp-psv-scan_complete)': {
                data: (el) => ({
                    slug: el.getAttribute('data-slug'),
                    plugin_file: el.getAttribute('data-plugin_file'),
                    version: el.getAttribute('data-version'),
                }),
                trigger: 'click',
                handle: 'scan_plugin',
                beforeSend: function(data, { element, selector, event }) {
                    element.classList.add('wp-psv-scanning');
                },
                success: function(response, { element, selector, event }) {
                    element.classList.remove('wp-psv-scanning');
                    element.classList.add('wp-psv-scan_complete');

                    if (response.success) {
                        if (response.data.results.length > 0) {
                            element.classList.add('wp-psv-scan_complete_with_issues');

                            const pluginEl = $('tr[data-slug="'+ element.getAttribute('data-slug') +'"][data-plugin="'+ element.getAttribute('data-plugin_file') +'"]:not([id])');
                            const targetEl = pluginEl[0];
                            if (!targetEl) return;

                            targetEl.insertAdjacentHTML('afterend', response.data.html.trim());

                        } else {
                            element.classList.add('wp-psv-scan_complete_no_issues');
                        }
                    } else {
                        element.classList.add('wp-psv-scan_error');
                    }
                },
                error: function(err) {
                    console.log(err);
                }
            }
        });
    });
})(jQuery);