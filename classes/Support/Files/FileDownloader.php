<?php

namespace Pegj\Support\Files;

if (defined('PEGJ_FILE_DOWNLOADER_DIR')) {
    return;
}

define( 'PEGJ_FILE_DOWNLOADER_DIR', __DIR__ );

/**
 * This class handles the downloading of files from any URL into wp-content/uploads/subdir.
 */
class FileDownloader
{
    public $file_source_url;
    public $file_name;
    private $refresh_interval = HOUR_IN_SECONDS;

    /**
     * Downloads a file from any URL into wp-content/uploads/subdir.
     *
     * @return true|\WP_Error|string Absolute path to a saved file or WP_Error.
     */
    public function download(): true|\WP_Error|string
    {
        if ( empty( $this->get_file_source_url() ) || empty( $this->get_file_name() ) ) {
            return new \WP_Error( WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN .'_invalid_params', 'Source URL and filename are required.' );
        }

        $upload = wp_upload_dir();
        if ( ! empty( $upload['error'] ) ) {
            return new \WP_Error( WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN .'_upload_dir_error', $upload['error'] );
        }

        $target_dir  = wp_normalize_path( trailingslashit( $upload['basedir'] ) . WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN );
        $target_file = wp_normalize_path( $target_dir . '/' . sanitize_file_name( $this->get_file_name() ) );

        $perm_check = $this->check_permissions( $upload['basedir'], $target_dir, $target_file );
        if ( is_wp_error( $perm_check ) ) {
            return $perm_check;
        }

        // If the file exists and is fresh, skip re-download.
        if ( file_exists( $target_file ) && $this->get_refresh_interval() > 0 ) {
            $age = time() - (int) @filemtime( $target_file );
            if ( $age >= 0 && $age < $this->get_refresh_interval() ) {
                return $target_file;
            }
        }

        $result_dir = $this->ensure_directory( $target_dir );
        if ( is_wp_error( $result_dir ) ) {
            return $result_dir;
        }

        $temp = download_url( esc_url_raw( $this->get_file_source_url() ), 30 );
        if ( is_wp_error( $temp ) ) {
            return new \WP_Error( WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN .'_download_failed', 'Failed to download file.', $temp );
        }

        // Overwrite (replace if exists).
        $move_ok = @rename( $temp, $target_file );
        if ( ! $move_ok ) {
            $copy_ok = @copy( $temp, $target_file );
            @unlink( $temp );
            if ( ! $copy_ok ) {
                return new \WP_Error( WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN .'_write_failed', 'Unable to write file to target location.' );
            }
        }

        @chmod( $target_file, 0644 );

        return $target_file;
    }

    /**
     * Check and create the target directory if it doesn't exist.
     *
     * @param $target_dir
     * @return true|\WP_Error
     */
    private function ensure_directory( $target_dir ): true|\WP_Error
    {
        if ( ! is_dir( $target_dir ) ) {
            if ( ! wp_mkdir_p( $target_dir ) ) {
                return new \WP_Error( WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN .'_mkdir_failed', 'Failed to create destination directory.' );
            }
            @chmod( $target_dir, 0755 );
        }

        if ( ! $this->is_writable( $target_dir ) ) {
            return new \WP_Error( WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN .'_dir_not_writable', 'Destination directory is not writable.' );
        }

        return true;
    }

    /**
     * Set the minimum time between downloads for the same target file.
     *
     * @param int $seconds Non-negative integer seconds. 0 = always download.
     * @return void
     */
    public function set_refresh_interval(int $seconds ): void
    {
        if ( $seconds < 0 ) {
            $seconds = HOUR_IN_SECONDS;
        }

        $this->refresh_interval = $seconds;
    }

    public function get_refresh_interval(): float|int
    {
        return $this->refresh_interval;
    }

    public function set_file_source_url($file_source_url): void
    {
        $this->file_source_url = $file_source_url;
    }

    public function get_file_source_url()
    {
        return $this->file_source_url;
    }

    public function set_file_name($file_name)
    {
        $this->file_name = $file_name;
    }

    public function get_file_name()
    {
        return $this->file_name;
    }

    public function check_permissions( $uploads_base, $target_dir, $target_file ): true|\WP_Error
    {
        if ( ! $this->is_writable( $uploads_base ) ) {
            return new \WP_Error( WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN .'_uploads_not_writable', 'Uploads directory is not writable.' );
        }

        if ( is_dir( $target_dir ) && ! $this->is_writable( $target_dir ) ) {
            return new \WP_Error( WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN .'_target_dir_not_writable', 'Target directory is not writable.' );
        }

        if ( file_exists( $target_file ) && ! $this->is_writable( $target_file ) ) {
            return new \WP_Error( WP_PLUGIN_SAFETY_VALIDATOR_DOMAIN .'_target_file_not_writable', 'Target file exists but is not writable.' );
        }

        return true;
    }

    private function is_writable( $path ): bool
    {
        if ( function_exists( 'wp_is_writable' ) ) {
            return wp_is_writable( $path );
        }
        return is_writable( $path );
    }
}