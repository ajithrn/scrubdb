<?php
/**
 * GitHub Updater — checks GitHub releases for plugin updates
 * and integrates with the WordPress plugin update system.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ScrubDB_GitHub_Updater {

    private $slug;
    private $plugin_file;
    private $github_repo;
    private $transient_key;

    /**
     * @param string $plugin_file Full path to the main plugin file.
     * @param string $github_repo GitHub repo in "owner/repo" format.
     */
    public function __construct( $plugin_file, $github_repo ) {
        $this->plugin_file   = $plugin_file;
        $this->github_repo   = $github_repo;
        $this->slug          = dirname( plugin_basename( $plugin_file ) );
        $this->transient_key = 'scrubdb_github_update_' . md5( $this->slug );

        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update' ] );
        add_filter( 'plugins_api', [ $this, 'plugin_info' ], 10, 3 );
    }

    /**
     * Hook into the update check transient to inject our update data.
     */
    public function check_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $remote = $this->get_remote_version();

        if ( $remote && version_compare( SCRUBDB_VERSION, $remote->new_version, '<' ) ) {
            $res              = new stdClass();
            $res->slug        = $this->slug;
            $res->plugin      = plugin_basename( $this->plugin_file );
            $res->new_version = $remote->new_version;
            $res->tested      = $remote->tested;
            $res->package     = $remote->package;
            $res->url         = $remote->url;

            $transient->response[ $res->plugin ] = $res;
        }

        return $transient;
    }

    /**
     * Provide plugin information for the "View Details" modal.
     */
    public function plugin_info( $res, $action, $args ) {
        if ( 'plugin_information' !== $action ) {
            return $res;
        }

        if ( $this->slug !== $args->slug ) {
            return $res;
        }

        $remote = $this->get_remote_version();

        if ( ! $remote ) {
            return $res;
        }

        $res                 = new stdClass();
        $res->name           = 'ScrubDB';
        $res->slug           = $this->slug;
        $res->version        = $remote->new_version;
        $res->tested         = $remote->tested;
        $res->requires       = '5.0';
        $res->requires_php   = '7.4';
        $res->author         = '<a href="https://ajithrn.com">Ajith R N</a>';
        $res->author_profile = 'https://github.com/ajithrn';
        $res->download_link  = $remote->package;
        $res->trunk          = $remote->package;
        $res->last_updated   = $remote->last_updated;
        $res->sections       = [
            'description' => 'WordPress database diagnostic and cleanup tool — inspect bloat, find orphaned data, debug problematic options, and clean up when ready.',
            'changelog'   => $remote->changelog,
        ];

        return $res;
    }

    /**
     * Get the remote version info, cached for 12 hours.
     */
    private function get_remote_version() {
        $remote = get_site_transient( $this->transient_key );

        if ( false === $remote ) {
            $remote = $this->fetch_github_release();
            set_site_transient( $this->transient_key, $remote, 12 * HOUR_IN_SECONDS );
        }

        return $remote;
    }

    /**
     * Fetch the latest release from GitHub API.
     */
    public function fetch_github_release() {
        $url      = "https://api.github.com/repos/{$this->github_repo}/releases/latest";
        $response = wp_remote_get( $url, [
            'timeout' => 10,
            'headers' => [ 'Accept' => 'application/vnd.github.v3+json' ],
        ] );

        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ) );

        if ( ! isset( $body->tag_name ) ) {
            return false;
        }

        $version = ltrim( $body->tag_name, 'v' );

        // Find the zip asset, fall back to zipball.
        $package = $body->zipball_url;
        if ( ! empty( $body->assets ) ) {
            foreach ( $body->assets as $asset ) {
                if ( $asset->name === 'scrubdb.zip' ) {
                    $package = $asset->browser_download_url;
                    break;
                }
            }
        }

        $obj               = new stdClass();
        $obj->new_version  = $version;
        $obj->url          = $body->html_url;
        $obj->package      = $package;
        $obj->changelog    = $this->parse_markdown( $body->body ?? '' );
        $obj->last_updated = $body->published_at ?? '';
        $obj->tested       = '6.7';

        return $obj;
    }

    /**
     * Simple markdown to HTML for release notes.
     */
    private function parse_markdown( $text ) {
        if ( empty( $text ) ) {
            return '';
        }

        $text = preg_replace( '/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text );
        $text = preg_replace_callback(
            '/\[(.*?)\]\((.*?)\)/',
            function ( $m ) {
                return '<a href="' . esc_url( $m[2] ) . '">' . esc_html( $m[1] ) . '</a>';
            },
            $text
        );
        $text = preg_replace( '/^\s*-\s+(.*)/m', '<li>$1</li>', $text );
        $text = preg_replace( '/((<li>.*<\/li>\s*)+)/s', '<ul>$1</ul>', $text );

        return nl2br( $text );
    }
}
