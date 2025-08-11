<a href="#" data-slug="<?php echo $slug ?>" data-version="<?php echo $version ?>" data-plugin_file="<?php echo $plugin_file ?>" class="<?php echo WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN . '-scan_button' ?> el-not-ready">
    <span class="wp-psv-scan_btn"><?php _e('Scan this plugin', WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN) ?></span>
    <span class="wp-psv-scanning_btn"><?php _e('Scanning in background process...', WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN) ?></span>
    <span class="wp-psv-scan_complete_no_issues_txt"><?php _e('Scan complete. No issues found.', WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN) ?></span>
    <span class="wp-psv-scan_complete_with_issues_txt"><?php _e('Scan complete. Some issues found.', WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN) ?></span>
</a>