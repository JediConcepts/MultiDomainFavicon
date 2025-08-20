<?php
/**
 * Plugin Name: Multi-Domain Favicon Manager
 * Plugin URI: https://github.com/JediConcepts/MultiDomainFavicon
 * Description: Adds unique favicon support for each domain mapping in the Multiple Domain Mapping plugin. Automatically suppresses WordPress default site icons when custom favicons are defined.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://jediconcepts.com
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: MultiDomainFavicon
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.3
 * Requires PHP: 7.4
 * Network: false
 * 
 * Multi-Domain Favicon Manager is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 * 
 * Multi-Domain Favicon Manager is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MDM_FAVICON_VERSION', '1.0.0');
define('MDM_FAVICON_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MDM_FAVICON_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MDM_FAVICON_PLUGIN_FILE', __FILE__);

/**
 * Main plugin class
 */
class MDM_Favicon_Manager {
    
    /**
     * Single instance of the plugin
     */
    private static $instance = null;
    
    /**
     * Plugin activation flag
     */
    private $mdm_plugin_active = false;
    
    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Check if Multiple Domain Mapping plugin is active
        if (!$this->check_mdm_plugin()) {
            add_action('admin_notices', array($this, 'mdm_plugin_missing_notice'));
            return;
        }
        
        $this->mdm_plugin_active = true;
        
        // Load text domain
        load_plugin_textdomain('mdm-favicon', false, dirname(plugin_basename(__FILE__)) . '/languages/');
        
        // Initialize admin interface
        if (is_admin()) {
            add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
            add_action('falke_mdma_after_mapping_body', array($this, 'add_favicon_field'), 15, 2);
            add_filter('falke_mdmf_save_mapping', array($this, 'save_favicon_field'));
        }
        
        // Frontend favicon handling
        add_action('template_redirect', array($this, 'maybe_remove_default_site_icon'));
        add_action('wp_head', array($this, 'output_custom_favicon'), 2);
        
        // Add settings link to plugins page
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
    }
    
    /**
     * Check if Multiple Domain Mapping plugin is active
     */
    private function check_mdm_plugin() {
        return class_exists('FALKE_MultipleDomainMapping');
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Set activation flag
        update_option('mdm_favicon_activated', true);
        
        // Check for MDM plugin
        if (!$this->check_mdm_plugin()) {
            // Store notice for display after activation
            update_option('mdm_favicon_show_mdm_notice', true);
        }
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        delete_option('mdm_favicon_activated');
        delete_option('mdm_favicon_show_mdm_notice');
    }
    
    /**
     * Show notice if MDM plugin is missing
     */
    public function mdm_plugin_missing_notice() {
        $class = 'notice notice-error';
        $message = sprintf(
            __('Multi-Domain Favicon Manager requires the %s plugin to be installed and activated.', 'mdm-favicon'),
            '<strong>Multiple Domain Mapping on single site</strong>'
        );
        
        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), $message);
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function admin_scripts($hook) {
        // Only load on MDM settings page
        if (strpos($hook, 'multidomainmapping') === false) {
            return;
        }
        
        wp_enqueue_media();
        wp_enqueue_script(
            'mdm-favicon-admin',
            MDM_FAVICON_PLUGIN_URL . 'assets/admin.js',
            array('jquery', 'media-upload'),
            MDM_FAVICON_VERSION,
            true
        );
        
        wp_localize_script('mdm-favicon-admin', 'mdmFavicon', array(
            'selectFavicon' => __('Select Favicon', 'mdm-favicon'),
            'useFavicon' => __('Use this favicon', 'mdm-favicon'),
            'removeFavicon' => __('Remove', 'mdm-favicon')
        ));
        
        wp_enqueue_style(
            'mdm-favicon-admin',
            MDM_FAVICON_PLUGIN_URL . 'assets/admin.css',
            array(),
            MDM_FAVICON_VERSION
        );
    }
    
    /**
     * Add favicon field to mapping form
     */
    public function add_favicon_field($cnt, $mapping) {
        if ($cnt === 'new') return;
        
        $favicon_url = isset($mapping['favicon']) ? $mapping['favicon'] : '';
        
        echo '<div class="mdm-favicon-field falke_mdm_mapping_additional_input">';
            echo '<p class="falke_mdm_mapping_additional_input_header">';
                echo '<strong>' . __('Favicon for this domain', 'mdm-favicon') . '</strong>';
            echo '</p>';
            
            echo '<div class="mdm-favicon-wrapper">';
                echo '<input type="url" ';
                echo 'name="falke_mdm_mappings[cnt_' . $cnt . '][favicon]" ';
                echo 'value="' . esc_url($favicon_url) . '" ';
                echo 'placeholder="https://example.com/favicon.ico" ';
                echo 'class="mdm-favicon-url regular-text" ';
                echo 'data-target="cnt_' . $cnt . '" />';
                
                echo '<button type="button" class="button mdm-favicon-upload" data-target="cnt_' . $cnt . '">';
                    echo __('Upload', 'mdm-favicon');
                echo '</button>';
                
                if ($favicon_url) {
                    echo '<button type="button" class="button mdm-favicon-remove" data-target="cnt_' . $cnt . '">';
                        echo __('Remove', 'mdm-favicon');
                    echo '</button>';
                }
            echo '</div>';
            
            if ($favicon_url) {
                echo '<div class="mdm-favicon-preview">';
                    echo '<img src="' . esc_url($favicon_url) . '" alt="Favicon preview" />';
                echo '</div>';
            }
            
            echo '<p class="description">';
                echo __('Upload a favicon (16x16 or 32x32 pixels, .ico, .png, or .svg format) for this specific domain.', 'mdm-favicon');
                echo '<br>';
                echo __('This will override the default WordPress site icon when visitors access this domain.', 'mdm-favicon');
            echo '</p>';
        echo '</div>';
    }
    
    /**
     * Save favicon field with mapping data
     */
    public function save_favicon_field($mapping) {
        if (isset($mapping['favicon']) && !empty($mapping['favicon'])) {
            $mapping['favicon'] = esc_url_raw($mapping['favicon']);
        }
        return $mapping;
    }
    
    /**
     * Remove WordPress default site icon when custom favicon exists
     */
    public function maybe_remove_default_site_icon() {
        if (!$this->mdm_plugin_active) return;
        
        global $FALKE_MultipleDomainMapping;
        
        if (!isset($FALKE_MultipleDomainMapping)) return;
        
        $currentMapping = $FALKE_MultipleDomainMapping->getCurrentMapping();
        
        if (!empty($currentMapping['match'])) {
            $has_custom_favicon = false;
            
            // Check for favicon field
            if (!empty($currentMapping['match']['favicon'])) {
                $has_custom_favicon = true;
            }
            
            // Also check custom head code for favicon tags
            if (!empty($currentMapping['match']['customheadcode'])) {
                $customHeadCode = html_entity_decode($currentMapping['match']['customheadcode']);
                if ($this->contains_favicon_tags($customHeadCode)) {
                    $has_custom_favicon = true;
                }
            }
            
            if ($has_custom_favicon) {
                // Remove WordPress default site icon
                remove_action('wp_head', 'wp_site_icon', 99);
                remove_action('admin_head', 'wp_site_icon');
                
                // Filter out default site icon
                add_filter('site_icon_image_sizes', '__return_empty_array');
                add_filter('site_icon_meta_tags', '__return_empty_array');
            }
        }
    }
    
    /**
     * Output custom favicon
     */
    public function output_custom_favicon() {
        if (!$this->mdm_plugin_active) return;
        
        global $FALKE_MultipleDomainMapping;
        
        if (!isset($FALKE_MultipleDomainMapping)) return;
        
        $currentMapping = $FALKE_MultipleDomainMapping->getCurrentMapping();
        
        if (!empty($currentMapping['match']) && !empty($currentMapping['match']['favicon'])) {
            $favicon_url = $currentMapping['match']['favicon'];
            $this->render_favicon_tags($favicon_url);
        }
    }
    
    /**
     * Render favicon HTML tags
     */
    private function render_favicon_tags($favicon_url) {
        $file_extension = strtolower(pathinfo(parse_url($favicon_url, PHP_URL_PATH), PATHINFO_EXTENSION));
        
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
    
    /**
     * Check if custom head code contains favicon tags
     */
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
    
    /**
     * Add settings link to plugins page
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('tools.php?page=multiple-domain-mapping-on-single-site%2Fmultidomainmapping.php') . '">' . __('Settings', 'mdm-favicon') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}

// Initialize the plugin
MDM_Favicon_Manager::get_instance();

// Create assets directory structure on activation
register_activation_hook(__FILE__, function() {
    $upload_dir = wp_upload_dir();
    $favicon_dir = $upload_dir['basedir'] . '/mdm-favicons';
    
    if (!file_exists($favicon_dir)) {
        wp_mkdir_p($favicon_dir);
    }
});

// Add admin CSS inline if file doesn't exist
add_action('admin_head', function() {
    if (isset($_GET['page']) && strpos($_GET['page'], 'multidomainmapping') !== false) {
        ?>
        <style>
        .mdm-favicon-field {
            border-top: 1px solid #ddd;
            padding-top: 15px;
            margin-top: 15px;
        }
        .mdm-favicon-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        .mdm-favicon-url {
            flex: 1;
            min-width: 300px;
        }
        .mdm-favicon-preview {
            margin-top: 10px;
        }
        .mdm-favicon-preview img {
            width: 32px;
            height: 32px;
            border: 1px solid #ddd;
            background: #f9f9f9;
            padding: 4px;
        }
        .mdm-favicon-remove {
            color: #a00;
        }
        .mdm-favicon-remove:hover {
            color: #dc3232;
        }
        </style>
        <?php
    }
});

// Add admin JavaScript inline if file doesn't exist
add_action('admin_footer', function() {
    if (isset($_GET['page']) && strpos($_GET['page'], 'multidomainmapping') !== false) {
        ?>
        <script>
        jQuery(document).ready(function($) {
            var mediaUploader;
            
            // Upload favicon
            $(document).on('click', '.mdm-favicon-upload', function(e) {
                e.preventDefault();
                
                var button = $(this);
                var target = button.data('target');
                var inputField = $('.mdm-favicon-url[data-target="' + target + '"]');
                var wrapper = button.closest('.mdm-favicon-wrapper');
                var preview = wrapper.siblings('.mdm-favicon-preview');
                
                if (mediaUploader) {
                    mediaUploader.open();
                    return;
                }
                
                mediaUploader = wp.media({
                    title: 'Select Favicon',
                    button: {
                        text: 'Use this favicon'
                    },
                    library: {
                        type: ['image/x-icon', 'image/png', 'image/svg+xml', 'image/jpeg', 'image/gif']
                    },
                    multiple: false
                });
                
                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    
                    inputField.val(attachment.url);
                    
                    // Update preview
                    if (preview.length === 0) {
                        preview = $('<div class="mdm-favicon-preview"></div>');
                        wrapper.after(preview);
                    }
                    preview.html('<img src="' + attachment.url + '" alt="Favicon preview" />');
                    
                    // Add remove button if not exists
                    if (wrapper.find('.mdm-favicon-remove').length === 0) {
                        wrapper.append('<button type="button" class="button mdm-favicon-remove" data-target="' + target + '">Remove</button>');
                    }
                });
                
                mediaUploader.open();
            });
            
            // Remove favicon
            $(document).on('click', '.mdm-favicon-remove', function(e) {
                e.preventDefault();
                
                var button = $(this);
                var target = button.data('target');
                var inputField = $('.mdm-favicon-url[data-target="' + target + '"]');
                var wrapper = button.closest('.mdm-favicon-wrapper');
                var preview = wrapper.siblings('.mdm-favicon-preview');
                
                inputField.val('');
                preview.remove();
                button.remove();
            });
        });
        </script>
        <?php
    }
});
?>
