<?php
/**
 * Plugin Name: Multi-Domain Favicon Manager
 * Plugin URI: https://github.com/JediConcepts/MultiDomainFavicon
 * Description: Adds unique favicon support for each domain mapping in the Multiple Domain Mapping plugin. Automatically suppresses WordPress default site icons when custom favicons are defined.
 * Version: 1.0.1
 * Author: JediConcepts
 * Author URI: https://jediconcepts.com
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: multidomainfavicon
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
define('MDM_FAVICON_VERSION', '1.0.1');
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
        load_plugin_textdomain('multidomainfavicon', false, dirname(plugin_basename(__FILE__)) . '/languages/');
        
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
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_action('wp_footer', array($this, 'debug_output'));
        }
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
            __('Multi-Domain Favicon Manager requires the %s plugin to be installed and activated.', 'multidomainfavicon'),
            '<strong>Multiple Domain Mapping on single site</strong>'
        );
        
        printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), $message);
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function admin_scripts($hook) {
        // Debug: Check what hook we're on
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('MDM Favicon: Admin hook: ' . $hook);
        }
        
        // Check for the correct MDM page - try multiple patterns
        $is_mdm_page = (
            strpos($hook, 'multidomainmapping') !== false ||
            strpos($hook, 'multiple-domain-mapping') !== false ||
            (isset($_GET['page']) && strpos($_GET['page'], 'multiple-domain-mapping') !== false)
        );
        
        if (!$is_mdm_page) {
            return;
        }
        
        wp_enqueue_media();
        
        // Use inline scripts since external files might not exist
        add_action('admin_footer', array($this, 'admin_js_inline'));
        add_action('admin_head', array($this, 'admin_css_inline'));
    }
    
    /**
     * Add favicon field to mapping form
     */
    public function add_favicon_field($cnt, $mapping) {
        if ($cnt === 'new') return;
        
        $favicon_url = isset($mapping['favicon']) ? $mapping['favicon'] : '';
        
        echo '<div class="mdm-favicon-field falke_mdm_mapping_additional_input">';
            echo '<p class="falke_mdm_mapping_additional_input_header">';
                echo '<strong>' . __('Favicon for this domain', 'multidomainfavicon') . '</strong>';
            echo '</p>';
            
            echo '<div class="mdm-favicon-wrapper">';
                echo '<input type="url" ';
                echo 'name="falke_mdm_mappings[cnt_' . $cnt . '][favicon]" ';
                echo 'value="' . esc_url($favicon_url) . '" ';
                echo 'placeholder="https://example.com/favicon.ico" ';
                echo 'class="mdm-favicon-url regular-text" ';
                echo 'data-target="cnt_' . $cnt . '" />';
                
                echo '<div class="mdm-favicon-buttons">';
                    echo '<button type="button" class="button mdm-favicon-upload" data-target="cnt_' . $cnt . '">';
                        echo __('Upload New', 'multidomainfavicon');
                    echo '</button>';
                    
                    echo '<button type="button" class="button mdm-favicon-browse" data-target="cnt_' . $cnt . '">';
                        echo __('Browse Media', 'multidomainfavicon');
                    echo '</button>';
                    
                    if ($favicon_url) {
                        echo '<button type="button" class="button mdm-favicon-remove" data-target="cnt_' . $cnt . '">';
                            echo __('Remove', 'multidomainfavicon');
                        echo '</button>';
                    }
                echo '</div>';
            echo '</div>';
            
            if ($favicon_url) {
                echo '<div class="mdm-favicon-preview">';
                    echo '<img src="' . esc_url($favicon_url) . '" alt="Favicon preview" />';
                echo '</div>';
            }
            
            echo '<p class="description">';
                echo __('Upload a new favicon or browse your existing media library for favicon files.', 'multidomainfavicon');
                echo '<br>';
                echo __('Supported formats: .ico, .png, .svg (16x16 or 32x32 pixels recommended).', 'multidomainfavicon');
                echo '<br>';
                echo __('This will override the default WordPress site icon when visitors access this domain.', 'multidomainfavicon');
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
        $settings_link = '<a href="' . admin_url('tools.php?page=multiple-domain-mapping-on-single-site%2Fmultidomainmapping.php') . '">' . __('Settings', 'multidomainfavicon') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Output admin CSS inline
     */
    public function admin_css_inline() {
        ?>
        <style>
        .mdm-favicon-field {
            border-top: 1px solid #ddd;
            padding-top: 15px;
            margin-top: 15px;
            background: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
        }
        .mdm-favicon-wrapper {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }
        .mdm-favicon-url {
            flex: 1;
            min-width: 300px;
        }
        .mdm-favicon-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        .mdm-favicon-preview {
            margin-top: 10px;
        }
        .mdm-favicon-preview img {
            width: 32px;
            height: 32px;
            border: 1px solid #ddd;
            background: #fff;
            padding: 4px;
            border-radius: 3px;
        }
        .mdm-favicon-remove {
            color: #a00 !important;
            border-color: #a00 !important;
        }
        .mdm-favicon-remove:hover {
            color: #fff !important;
            background: #dc3232 !important;
            border-color: #dc3232 !important;
        }
        .mdm-favicon-upload {
            background: #0073aa !important;
            border-color: #0073aa !important;
            color: #fff !important;
        }
        .mdm-favicon-upload:hover {
            background: #005a87 !important;
            border-color: #005a87 !important;
        }
        .mdm-favicon-browse {
            background: #00a32a !important;
            border-color: #00a32a !important;
            color: #fff !important;
        }
        .mdm-favicon-browse:hover {
            background: #008a20 !important;
            border-color: #008a20 !important;
        }
        </style>
        <?php
    }
    
    /**
     * Output admin JavaScript inline
     */
    public function admin_js_inline() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            var uploadMediaUploader, browseMediaUploader;
            
            // Upload new favicon
            $(document).on('click', '.mdm-favicon-upload', function(e) {
                e.preventDefault();
                
                var button = $(this);
                var target = button.data('target');
                var inputField = $('.mdm-favicon-url[data-target="' + target + '"]');
                var wrapper = button.closest('.mdm-favicon-wrapper');
                var preview = wrapper.siblings('.mdm-favicon-preview');
                
                // Create new media uploader instance for uploads
                uploadMediaUploader = wp.media({
                    title: '<?php echo esc_js(__('Upload New Favicon', 'multidomainfavicon')); ?>',
                    button: {
                        text: '<?php echo esc_js(__('Use this favicon', 'multidomainfavicon')); ?>'
                    },
                    library: {
                        type: ['image/x-icon', 'image/png', 'image/svg+xml', 'image/jpeg', 'image/gif']
                    },
                    multiple: false
                });
                
                uploadMediaUploader.on('select', function() {
                    var attachment = uploadMediaUploader.state().get('selection').first().toJSON();
                    updateFaviconField(target, attachment, inputField, wrapper, preview);
                });
                
                uploadMediaUploader.open();
            });
            
            // Browse existing media
            $(document).on('click', '.mdm-favicon-browse', function(e) {
                e.preventDefault();
                
                var button = $(this);
                var target = button.data('target');
                var inputField = $('.mdm-favicon-url[data-target="' + target + '"]');
                var wrapper = button.closest('.mdm-favicon-wrapper');
                var preview = wrapper.siblings('.mdm-favicon-preview');
                
                // Create new media uploader instance for browsing
                browseMediaUploader = wp.media({
                    title: '<?php echo esc_js(__('Select Favicon from Media Library', 'multidomainfavicon')); ?>',
                    button: {
                        text: '<?php echo esc_js(__('Use this favicon', 'multidomainfavicon')); ?>'
                    },
                    library: {
                        type: ['image/x-icon', 'image/png', 'image/svg+xml', 'image/jpeg', 'image/gif'],
                        uploadedTo: 0 // Show all media, not just uploaded to this post
                    },
                    multiple: false
                });
                
                browseMediaUploader.on('select', function() {
                    var attachment = browseMediaUploader.state().get('selection').first().toJSON();
                    updateFaviconField(target, attachment, inputField, wrapper, preview);
                });
                
                browseMediaUploader.open();
            });
            
            // Shared function to update favicon field
            function updateFaviconField(target, attachment, inputField, wrapper, preview) {
                inputField.val(attachment.url);
                
                // Update preview
                if (preview.length === 0) {
                    preview = $('<div class="mdm-favicon-preview"></div>');
                    wrapper.after(preview);
                }
                
                var previewHtml = '<img src="' + attachment.url + '" alt="Favicon preview" />';
                previewHtml += '<br><small>' + attachment.filename + ' (' + attachment.filesizeHumanReadable + ')</small>';
                preview.html(previewHtml);
                
                // Add remove button if not exists
                var buttonContainer = wrapper.find('.mdm-favicon-buttons');
                if (buttonContainer.find('.mdm-favicon-remove').length === 0) {
                    buttonContainer.append('<button type="button" class="button mdm-favicon-remove" data-target="' + target + '"><?php echo esc_js(__('Remove', 'multidomainfavicon')); ?></button>');
                }
            }
            
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
            
            // Auto-detect favicon format and show preview when URL is manually entered
            $(document).on('blur', '.mdm-favicon-url', function() {
                var input = $(this);
                var url = input.val().trim();
                var target = input.data('target');
                var wrapper = input.closest('.mdm-favicon-wrapper');
                var preview = wrapper.siblings('.mdm-favicon-preview');
                
                if (url && isValidFaviconUrl(url)) {
                    // Update preview for manually entered URLs
                    if (preview.length === 0) {
                        preview = $('<div class="mdm-favicon-preview"></div>');
                        wrapper.after(preview);
                    }
                    
                    var filename = url.substring(url.lastIndexOf('/') + 1);
                    var previewHtml = '<img src="' + url + '" alt="Favicon preview" onerror="this.style.display=\'none\'" />';
                    previewHtml += '<br><small>' + filename + '</small>';
                    preview.html(previewHtml);
                    
                    // Add remove button if not exists
                    var buttonContainer = wrapper.find('.mdm-favicon-buttons');
                    if (buttonContainer.find('.mdm-favicon-remove').length === 0) {
                        buttonContainer.append('<button type="button" class="button mdm-favicon-remove" data-target="' + target + '"><?php echo esc_js(__('Remove', 'multidomainfavicon')); ?></button>');
                    }
                } else if (!url) {
                    // Remove preview if URL is cleared
                    preview.remove();
                    wrapper.find('.mdm-favicon-remove').remove();
                }
            });
            
            // Simple favicon URL validation
            function isValidFaviconUrl(url) {
                var faviconExtensions = ['.ico', '.png', '.svg', '.jpg', '.jpeg', '.gif'];
                var urlLower = url.toLowerCase();
                return faviconExtensions.some(function(ext) {
                    return urlLower.includes(ext);
                });
            }
        });
        </script>
        <?php
    }
    
    /**
     * Debug output for troubleshooting
     */
    public function debug_output() {
        if (!current_user_can('manage_options')) return;
        
        global $FALKE_MultipleDomainMapping;
        
        if (isset($FALKE_MultipleDomainMapping)) {
            $currentMapping = $FALKE_MultipleDomainMapping->getCurrentMapping();
            if (!empty($currentMapping['match'])) {
                echo '<!-- MDM Favicon Debug: ';
                echo 'Domain: ' . (isset($currentMapping['match']['domain']) ? $currentMapping['match']['domain'] : 'none');
                echo ', Favicon: ' . (isset($currentMapping['match']['favicon']) ? $currentMapping['match']['favicon'] : 'none');
                echo ' -->';
            }
        }
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
?>
