<?php
/**
 * Plugin Name: Multi-Domain Favicon Manager
 * Plugin URI: https://github.com/jediconcepts/multi-domain-favicon-manager
 * Description: Adds unique favicon support for each domain mapping in the Multiple Domain Mapping plugin. Automatically suppresses WordPress default site icons when custom favicons are defined.
 * Version: 1.0.7
 * Author: JediConcepts
 * Author URI: https://jediconcepts.com
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: multi-domain-favicon-manager
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * Support: dev@jediconcepts.com
 * Requires Plugins: multiple-domain-mapping-on-single-site
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MULTIFAMA_VERSION', '1.0.7');
define('MULTIFAMA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MULTIFAMA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MULTIFAMA_PLUGIN_FILE', __FILE__);

/**
 * Main plugin class
 */
class MultiFama_Favicon_Manager {

    private static $instance = null;
    private $multifama_plugin_active = false;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    public function init() {
        // Check if Multiple Domain Mapping plugin is active
        if (!$this->check_mdm_plugin()) {
            add_action('admin_notices', array($this, 'mdm_plugin_missing_notice'));
            if (get_option('multifama_show_mdm_notice')) {
                delete_option('multifama_show_mdm_notice');
            }
            return;
        }

        $this->multifama_plugin_active = true;

        // Initialize admin interface
        if (is_admin()) {
            add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_assets'));
            add_action('falke_mdma_after_mapping_body', array($this, 'add_favicon_field'), 15, 2);
            add_filter('falke_mdmf_save_mapping', array($this, 'save_favicon_field'));
        }

        // Frontend favicon handling
        add_action('template_redirect', array($this, 'maybe_remove_default_site_icon'));
        add_action('wp_head', array($this, 'output_custom_favicon'), 2);

        // Add settings link to plugins page
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
    }

    private function check_mdm_plugin() {
        return class_exists('FALKE_MultipleDomainMapping');
    }

    public function activate() {
        update_option('multifama_favicon_activated', true);

        if (!$this->check_mdm_plugin()) {
            update_option('multifama_show_mdm_notice', true);
        }
    }

    public function deactivate() {
        delete_option('multifama_favicon_activated');
        delete_option('multifama_show_mdm_notice');
    }

    public function mdm_plugin_missing_notice() {
        $class = 'notice notice-error';
        $install_url = '';
        $button_text = '';
        $action_needed = '';

        // Check if the plugin exists but is not activated
        if (file_exists(WP_PLUGIN_DIR . '/multiple-domain-mapping-on-single-site/multiple-domain-mapping-on-single-site.php')) {
            $install_url = wp_nonce_url(
                admin_url('plugins.php?action=activate&plugin=multiple-domain-mapping-on-single-site/multiple-domain-mapping-on-single-site.php'),
                'activate-plugin_multiple-domain-mapping-on-single-site/multiple-domain-mapping-on-single-site.php'
            );
            $button_text = __('Activate Plugin', 'multi-domain-favicon-manager');
            $action_needed = __('The plugin is installed but not activated.', 'multi-domain-favicon-manager');
        } else {
            $install_url = wp_nonce_url(
                admin_url('update.php?action=install-plugin&plugin=multiple-domain-mapping-on-single-site'),
                'install-plugin_multiple-domain-mapping-on-single-site'
            );
            $button_text = __('Install Plugin', 'multi-domain-favicon-manager');
            $action_needed = __('Click the button below to automatically install it.', 'multi-domain-favicon-manager');
        }

        $message = sprintf(
            __('Multi-Domain Favicon Manager requires the multiple-domain-mapping-on-single-site plugin to be installed and activated.', 'multi-domain-favicon-manager'),
            '<strong>Multiple Domain Mapping on single site</strong>'
        );

        echo '<div class="' . esc_attr($class) . '">';
            echo '<p>' . wp_kses_post($message) . '</p>';
            echo '<p>' . esc_html($action_needed) . '</p>';
            echo '<p>';
                echo '<a href="' . esc_url($install_url) . '" class="button button-primary">' . esc_html($button_text) . '</a> ';
                echo '<a href="https://wordpress.org/plugins/multiple-domain-mapping-on-single-site/" target="_blank" class="button button-secondary">' . esc_html(__('View Plugin Info', 'multi-domain-favicon-manager')) . '</a> ';
            echo '</p>';
        echo '</div>';
    }

    public function admin_enqueue_assets($hook) {
        if (is_admin() && current_user_can('manage_options')) {
            $is_mdm_page = (
                strpos($hook, 'multidomainmapping') !== false ||
                strpos($hook, 'multiple-domain-mapping') !== false
            );
            if (!$is_mdm_page) {
                return;
            }
        } else {
            return;
        }

        wp_enqueue_media();

        // Enqueue plugin style
        wp_enqueue_style(
            'multifama-admin-favicon-css',
            MULTIFAMA_PLUGIN_URL . 'assets/css/admin-favicon.css',
            array(),
            MULTIFAMA_VERSION
        );

        // Enqueue plugin JS
        wp_enqueue_script(
            'multifama-admin-favicon-js',
            MULTIFAMA_PLUGIN_URL . 'assets/js/admin-favicon.js',
            array('jquery'),
            MULTIFAMA_VERSION,
            true
        );

        // Pass domain mapping info to JS
        $http_host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
        wp_localize_script('multifama-admin-favicon-js', 'multiDomainFaviconManagerData', array(
            'baseUrl' => home_url(),
            'currentUrl' => (is_ssl() ? 'https://' : 'http://') . $http_host,
            'uploadsUrl' => wp_upload_dir()['baseurl'],
            'currentMapping' => $this->get_current_mapping_context()
        ));
    }

    private function get_current_mapping_context() {
        global $FALKE_MultipleDomainMapping;
        if (isset($FALKE_MultipleDomainMapping)) {
            $mappings = $FALKE_MultipleDomainMapping->getMappings();
            if (!empty($mappings['mappings'])) {
                return $mappings['mappings'];
            }
        }
        return array();
    }

    public function add_favicon_field($cnt, $mapping) {
        if ($cnt === 'new') return;
        $favicon_url = isset($mapping['favicon']) ? $mapping['favicon'] : '';
        echo '<div class="multifama-favicon-field falke_mdm_mapping_additional_input">';
            echo '<p class="falke_mdm_mapping_additional_input_header">';
                echo '<strong>' . esc_html(__('Favicon for this domain', 'multi-domain-favicon-manager')) . '</strong>';
            echo '</p>';
            echo '<div class="multifama-favicon-wrapper">';
                echo '<input type="url" ';
                echo 'name="falke_mdm_mappings[cnt_' . esc_attr($cnt) . '][favicon]" ';
                echo 'value="' . esc_url($favicon_url) . '" ';
                echo 'placeholder="https://example.com/favicon.ico" ';
                echo 'class="multifama-favicon-url regular-text" ';
                echo 'data-target="cnt_' . esc_attr($cnt) . '" />';
                echo '<div class="multifama-favicon-buttons">';
                    echo '<button type="button" class="button multifama-favicon-upload" data-target="cnt_' . esc_attr($cnt) . '">';
                        echo esc_html(__('Upload New', 'multi-domain-favicon-manager'));
                    echo '</button>';
                    echo '<button type="button" class="button multifama-favicon-browse" data-target="cnt_' . esc_attr($cnt) . '">';
                        echo esc_html(__('Browse Media', 'multi-domain-favicon-manager'));
                    echo '</button>';
                    echo '<button type="button" class="button multifama-favicon-search" data-target="cnt_' . esc_attr($cnt) . '">';
                        echo esc_html(__('Search by Name', 'multi-domain-favicon-manager'));
                    echo '</button>';
                    echo '<button type="button" class="button multifama-favicon-convert" data-target="cnt_' . esc_attr($cnt) . '">';
                        echo esc_html(__('Convert URL', 'multi-domain-favicon-manager'));
                    echo '</button>';
                    if ($favicon_url) {
                        echo '<button type="button" class="button multifama-favicon-remove" data-target="cnt_' . esc_attr($cnt) . '">';
                            echo esc_html(__('Remove', 'multi-domain-favicon-manager'));
                        echo '</button>';
                    }
                echo '</div>';
            echo '</div>';
            if ($favicon_url) {
                echo '<div class="multifama-favicon-preview">';
                    echo '<img src="' . esc_url($favicon_url) . '" alt="Favicon preview" />';
                echo '</div>';
            }
            echo '<p class="description">';
                echo esc_html(__('Upload new, browse media library, search by filename, or convert domain URLs.', 'multi-domain-favicon-manager'));
                echo '<br>';
                echo esc_html(__('Convert URL: Converts base domain URLs to mapped domain URLs for this specific mapping.', 'multi-domain-favicon-manager'));
                echo '<br>';
                echo esc_html(__('Supported formats: .ico, .png, .svg (16x16 or 32x32 pixels recommended).', 'multi-domain-favicon-manager'));
                echo '<br>';
                echo esc_html(__('This will override the default WordPress site icon when visitors access this domain.', 'multi-domain-favicon-manager'));
            echo '</p>';
        echo '</div>';
    }

    public function save_favicon_field($mapping) {
        if (isset($mapping['favicon']) && !empty($mapping['favicon'])) {
            $mapping['favicon'] = esc_url_raw($mapping['favicon']);
        }
        return $mapping;
    }

    public function maybe_remove_default_site_icon() {
        if (!$this->multifama_plugin_active) return;
        global $FALKE_MultipleDomainMapping;
        if (!isset($FALKE_MultipleDomainMapping)) return;
        $currentMapping = $FALKE_MultipleDomainMapping->getCurrentMapping();
        if (!empty($currentMapping['match'])) {
            $has_custom_favicon = false;
            if (!empty($currentMapping['match']['favicon'])) {
                $has_custom_favicon = true;
            }
            if (!empty($currentMapping['match']['customheadcode'])) {
                $customHeadCode = html_entity_decode($currentMapping['match']['customheadcode']);
                if ($this->contains_favicon_tags($customHeadCode)) {
                    $has_custom_favicon = true;
                }
            }
            if ($has_custom_favicon) {
                remove_action('wp_head', 'wp_site_icon', 99);
                remove_action('admin_head', 'wp_site_icon');
                add_filter('site_icon_image_sizes', '__return_empty_array');
                add_filter('site_icon_meta_tags', '__return_empty_array');
            }
        }
    }

    public function output_custom_favicon() {
        if (!$this->multifama_plugin_active) return;
        global $FALKE_MultipleDomainMapping;
        if (!isset($FALKE_MultipleDomainMapping)) return;
        $currentMapping = $FALKE_MultipleDomainMapping->getCurrentMapping();
        if (!empty($currentMapping['match']) && !empty($currentMapping['match']['favicon'])) {
            $favicon_url = $currentMapping['match']['favicon'];
            $this->render_favicon_tags($favicon_url);
        }
    }

    private function render_favicon_tags($favicon_url) {
        $file_extension = strtolower(pathinfo(wp_parse_url($favicon_url, PHP_URL_PATH), PATHINFO_EXTENSION));
        echo "<!-- Custom favicon for mapped domain -->\n";
        switch ($file_extension) {
            case 'svg':
                echo '<link rel="icon" type="image/svg+xml" href="' . esc_url($favicon_url) . '" />' . "\n";
                break;
            case 'png':
                echo '<link rel="icon" type="image/png" href="' . esc_url($favicon_url) . '" />' . "\n";
                echo '<link rel="apple-touch-icon" href="' . esc_url($favicon_url) . '" />' . "\n";
                break;
            case 'ico':
            default:
                echo '<link rel="shortcut icon" href="' . esc_url($favicon_url) . '" />' . "\n";
                echo '<link rel="icon" type="image/x-icon" href="' . esc_url($favicon_url) . '" />' . "\n";
                break;
        }
    }

    private function contains_favicon_tags($html) {
        $favicon_patterns = array(
            '/rel=["\']icon["\']/',
            '/rel=["\']shortcut icon["\']/',
            '/rel=["\']apple-touch-icon["\']/',
            '/name=["\']msapplication-TileImage["\']/'
        );
        foreach ($favicon_patterns as $pattern) {
            if (preg_match($pattern, $html)) {
                return true;
            }
        }
        return false;
    }

    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('tools.php?page=multiple-domain-mapping-on-single-site%2Fmultidomainmapping.php') . '">' . esc_html(__('Settings', 'multi-domain-favicon-manager')) . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}

// Initialize the plugin
MultiFama_Favicon_Manager::get_instance();

// Create assets directory structure on activation
register_activation_hook(__FILE__, function() {
    $upload_dir = wp_upload_dir();
    $favicon_dir = $upload_dir['basedir'] . '/multifama-favicons';
    if (!file_exists($favicon_dir)) {
        wp_mkdir_p($favicon_dir);
    }
});
?>
