<tr class="<?php echo esc_attr( $classes ); ?>" id="<?php echo esc_attr( sanitize_html_class( dirname( $plugin_file ) ) ); ?>-wp_psv_scan-notice" data-plugin="<?php echo esc_attr( $plugin_file ); ?>">
    <td colspan="4" class="plugin-update colspanchange">
        <div class="update-message notice inline notice-alt notice-error">
            <p style="font-weight: 500"><?php echo wp_kses_post( $message ); ?></p>

            <ul class="" style="list-style: inside; padding-left: 30px;">
                <?php
                    $store_vulnerabilities = [];
                    foreach ($vulnerabilities as $vulnerability) :

                        $title = $vulnerability['title'] .' — '. $vulnerability['description'];

                        if (!in_array($title, $store_vulnerabilities)) : // hide duplicate sources
                ?>
                            <li>
                                <?php
                                    echo esc_html($title);

                                    if (!empty($vulnerability['sources'])) {
                                        echo " | Sources: ";
                                        $sources_html = [];
                                        foreach ($vulnerability['sources'] as $domain => $url) {
                                            $sources_html[] = '<a href="'. esc_url($url) .'" target="_blank">'. esc_html($domain) .'</a>';
                                        }

                                        echo implode(', ', $sources_html);
                                    }
                                ?>
                            </li>
                <?php
                            $store_vulnerabilities[] = $title;
                        endif;
                    endforeach;
                ?>
            </ul>
        </div>
    </td>
</tr>