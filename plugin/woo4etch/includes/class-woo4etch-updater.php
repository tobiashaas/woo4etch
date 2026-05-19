<?php
/**
 * GitHub release updates for Woo4Etch (regular plugin install under wp-content/plugins/).
 *
 * @package Woo4Etch
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Checks github.com/tobiashaas/woo4etch releases and registers updates in WordPress.
 */
final class Woo4Etch_Updater {

    private const GITHUB_REPO = 'tobiashaas/woo4etch';
    private const CACHE_KEY   = 'woo4etch_github_release';
    private const CACHE_TTL   = 43200; // 12 hours

    /** @var string */
    private static $plugin_file;

    /** @var string */
    private static $plugin_basename;

    /**
     * @param string $plugin_file Absolute path to woo4etch.php.
     */
    public static function init($plugin_file) {
        if (!apply_filters('woo4etch/enable_github_updates', true)) {
            return;
        }

        // MU-plugin installs use a different update path; dashboard updates target plugins/.
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        self::$plugin_file     = $plugin_file;
        self::$plugin_basename = plugin_basename($plugin_file);

        add_filter('pre_set_site_transient_update_plugins', [__CLASS__, 'inject_update']);
        add_filter('plugins_api', [__CLASS__, 'plugins_api'], 20, 3);
    }

    /**
     * @param object $transient update_plugins transient.
     * @return object
     */
    public static function inject_update($transient) {
        if (!is_object($transient)) {
            $transient = (object) ['checked' => [], 'response' => [], 'no_update' => []];
        }

        $release = self::get_release();
        if ($release === null) {
            return $transient;
        }

        $current = self::get_plugin_version();
        if ($current === '' || version_compare($release['version'], $current, '<=')) {
            if (!isset($transient->no_update)) {
                $transient->no_update = [];
            }
            $transient->no_update[self::$plugin_basename] = self::build_update_object($release);
            return $transient;
        }

        if (!isset($transient->response)) {
            $transient->response = [];
        }

        $transient->response[self::$plugin_basename] = self::build_update_object($release);

        return $transient;
    }

    /**
     * Plugin details modal in Plugins → View details.
     *
     * @param false|object|array $result
     * @param string             $action
     * @param object             $args
     * @return false|object|array
     */
    public static function plugins_api($result, $action, $args) {
        if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== 'woo4etch') {
            return $result;
        }

        $release = self::get_release();
        if ($release === null) {
            return $result;
        }

        $plugin_data = get_plugin_data(self::$plugin_file, false, false);

        return (object) [
            'name'          => $plugin_data['Name'] ?: 'Woo4Etch',
            'slug'          => 'woo4etch',
            'version'       => $release['version'],
            'author'        => $plugin_data['Author'] ?: 'Tobias Haas',
            'homepage'      => $plugin_data['PluginURI'] ?: 'https://github.com/' . self::GITHUB_REPO,
            'download_link' => $release['package'],
            'requires'      => $plugin_data['RequiresWP'] ?: '6.0',
            'requires_php'  => $plugin_data['RequiresPHP'] ?: '7.4',
            'sections'      => [
                'description' => $plugin_data['Description'] ?: '',
                'changelog'   => $release['notes'] !== '' ? $release['notes'] : __('See GitHub Releases for changes.', 'woo4etch'),
            ],
            'banners'       => [],
            'icons'         => [],
        ];
    }

    /**
     * @return array{version: string, package: string, url: string, notes: string}|null
     */
    private static function get_release() {
        $cached = get_transient(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $response = wp_remote_get(
            'https://api.github.com/repos/' . self::GITHUB_REPO . '/releases/latest',
            [
                'timeout' => 15,
                'headers' => [
                    'Accept'     => 'application/vnd.github+json',
                    'User-Agent' => 'Woo4Etch-Updater/' . self::get_plugin_version(),
                ],
            ]
        );

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($body) || empty($body['tag_name'])) {
            return null;
        }

        $package = self::resolve_zip_url($body);
        if ($package === '') {
            return null;
        }

        $release = [
            'version' => self::normalize_version((string) $body['tag_name']),
            'package' => $package,
            'url'     => isset($body['html_url']) ? (string) $body['html_url'] : '',
            'notes'   => isset($body['body']) ? (string) $body['body'] : '',
        ];

        set_transient(self::CACHE_KEY, $release, self::CACHE_TTL);

        return $release;
    }

    /**
     * Prefer release asset woo4etch.zip; fall back to first .zip asset.
     *
     * @param array<string, mixed> $body GitHub API release payload.
     */
    private static function resolve_zip_url(array $body) {
        if (empty($body['assets']) || !is_array($body['assets'])) {
            return '';
        }

        $fallback = '';
        foreach ($body['assets'] as $asset) {
            if (!is_array($asset) || empty($asset['browser_download_url'])) {
                continue;
            }
            $name = isset($asset['name']) ? (string) $asset['name'] : '';
            $url  = (string) $asset['browser_download_url'];

            if ($name === 'woo4etch.zip') {
                return $url;
            }

            if ($fallback === '' && substr(strtolower($name), -4) === '.zip') {
                $fallback = $url;
            }
        }

        return $fallback;
    }

    /**
     * @param array{version: string, package: string, url: string, notes: string} $release
     */
    private static function build_update_object(array $release) {
        return (object) [
            'slug'        => 'woo4etch',
            'plugin'      => self::$plugin_basename,
            'new_version' => $release['version'],
            'url'         => $release['url'],
            'package'     => $release['package'],
            'icons'       => [],
            'banners'     => [],
            'tested'      => '',
            'requires'    => '6.0',
            'requires_php'=> '7.4',
        ];
    }

    private static function get_plugin_version() {
        if (!function_exists('get_plugin_data')) {
            return '';
        }
        $data = get_plugin_data(self::$plugin_file, false, false);
        return isset($data['Version']) ? (string) $data['Version'] : '';
    }

    private static function normalize_version($tag) {
        return ltrim($tag, 'vV');
    }
}
