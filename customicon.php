<?php
/**
 * Plugin Name: Multi-Domain Favicon Manager
 * Plugin URI: https://github.com/jediconcepts/multi-domain-favicon
 * Description: Adds unique favicon support for each domain mapping in the Multiple Domain Mapping plugin. Automatically suppresses WordPress default site icons when custom favicons are defined.
 * Version: 1.0.6
 * Author: JediConcepts
 * Author URI: https://jediconcepts.com
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: multidomainfavicon-main
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
define('MDM_FAVICON_VERSION', '1.0.6');
define('MDM_FAVICON_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MDM_FAVICON_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MDM_FAVICON_PLUGIN_FILE', __FILE__);

/**
 * Main plugin class
 */
class MDM_Favicon_Manager {
    
    private static $instance = null;
    private $mdm_plugin_active = false;
    
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
            
            // Still show the notice even if MDM plugin gets activated during this request
            if (get_option('mdm_favicon_show_mdm_notice')) {
                delete_option('mdm_favicon_show_mdm_notice');
            }
            return;
        }
        
        $this->mdm_plugin_active = true;
        
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
    
    private function check_mdm_plugin() {
        return class_exists('FALKE_MultipleDomainMapping');
    }
    
    public function activate() {
        update_option('mdm_favicon_activated', true);
        
        if (!$this->check_mdm_plugin()) {
            update_option('mdm_favicon_show_mdm_notice', true);
        }
    }
    
    public function deactivate() {
        delete_option('mdm_favicon_activated');
        delete_option('mdm_favicon_show_mdm_notice');
    }
    
    public function mdm_plugin_missing_notice() {
        $class = 'notice notice-error';
        $install_url = '';
        $button_text = '';
        $action_needed = '';
        
        // Check if the plugin exists but is not activated
        if (file_exists(WP_PLUGIN_DIR . '/multiple-domain-mapping-on-single-site/multiple-domain-mapping-on-single-site.php')) {
            // Plugin exists but not activated
            $install_url = wp_nonce_url(
                admin_url('plugins.php?action=activate&plugin=multiple-domain-mapping-on-single-site/multiple-domain-mapping-on-single-site.php'),
                'activate-plugin_multiple-domain-mapping-on-single-site/multiple-domain-mapping-on-single-site.php'
            );
            $button_text = __('Activate Plugin', 'multidomainfavicon-main');
            $action_needed = __('The plugin is installed but not activated.', 'multidomainfavicon-main');
        } else {
            // Plugin needs to be installed
            $install_url = wp_nonce_url(
                admin_url('update.php?action=install-plugin&plugin=multiple-domain-mapping-on-single-site'),
                'install-plugin_multiple-domain-mapping-on-single-site'
            );
            $button_text = __('Install Plugin', 'multidomainfavicon-main');
            $action_needed = __('Click the button below to automatically install it.', 'multidomainfavicon-main');
        }
        
        
        $message = sprintf(
            __('Multi-Domain Favicon Manager requires the multiple-domain-mapping-on-single-site plugin to be installed and activated.', 'multidomainfavicon-main'),
            '<strong>Multiple Domain Mapping on single site</strong>'
        );
        
        echo '<div class="' . esc_attr($class) . '">';
            echo '<p>' . wp_kses_post($message) . '</p>';
            echo '<p>' . esc_html($action_needed) . '</p>';
            echo '<p>';
                echo '<a href="' . esc_url($install_url) . '" class="button button-primary">' . esc_html($button_text) . '</a> ';
                echo '<a href="https://wordpress.org/plugins/multiple-domain-mapping-on-single-site/" target="_blank" class="button button-secondary">' . esc_html(__('View Plugin Info', 'multidomainfavicon-main')) . '</a>';
            echo '</p>';
            echo '<style>';
                echo '.notice.notice-error p:last-of-type { margin-bottom: 10px; }';
                echo '.notice.notice-error .button { margin-right: 10px; }';
            echo '</style>';
        echo '</div>';
    }
    
    public function admin_scripts($hook) {
        // Check for the correct MDM page
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
        
        // Pass domain mapping info to JavaScript
        $http_host = isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '';
        wp_localize_script('jquery', 'mdmFaviconData', array(
            'baseUrl' => home_url(),
            'currentUrl' => (is_ssl() ? 'https://' : 'http://') . $http_host,
            'uploadsUrl' => wp_upload_dir()['baseurl'],
            'currentMapping' => $this->get_current_mapping_context()
        ));
        
        add_action('admin_footer', array($this, 'admin_js_inline'));
        add_action('admin_head', array($this, 'admin_css_inline'));
    }
    
    /**
     * Get current mapping context from the page
     */
    private function get_current_mapping_context() {
        // Try to detect which mapping we're working with from the form context
        global $FALKE_MultipleDomainMapping;
        
        if (isset($FALKE_MultipleDomainMapping)) {
            $mappings = $FALKE_MultipleDomainMapping->getMappings();
            if (!empty($mappings['mappings'])) {
                // Return all mappings so JavaScript can determine context
                return $mappings['mappings'];
            }
        }
        
        return array();
    }
    
    public function add_favicon_field($cnt, $mapping) {
        if ($cnt === 'new') return;
        
        $favicon_url = isset($mapping['favicon']) ? $mapping['favicon'] : '';
        
        echo '<div class="mdm-favicon-field falke_mdm_mapping_additional_input">';
            echo '<p class="falke_mdm_mapping_additional_input_header">';
                echo '<strong>' . esc_html(__('Favicon for this domain', 'multidomainfavicon-main')) . '</strong>';
            echo '</p>';
            
            echo '<div class="mdm-favicon-wrapper">';
                echo '<input type="url" ';
                echo 'name="falke_mdm_mappings[cnt_' . esc_attr($cnt) . '][favicon]" ';
                echo 'value="' . esc_url($favicon_url) . '" ';
                echo 'placeholder="https://example.com/favicon.ico" ';
                echo 'class="mdm-favicon-url regular-text" ';
                echo 'data-target="cnt_' . esc_attr($cnt) . '" />';
                
                echo '<div class="mdm-favicon-buttons">';
                    echo '<button type="button" class="button mdm-favicon-upload" data-target="cnt_' . esc_attr($cnt) . '">';
                        echo esc_html(__('Upload New', 'multidomainfavicon-main'));
                    echo '</button>';
                    
                    echo '<button type="button" class="button mdm-favicon-browse" data-target="cnt_' . esc_attr($cnt) . '">';
                        echo esc_html(__('Browse Media', 'multidomainfavicon-main'));
                    echo '</button>';
                    
                    echo '<button type="button" class="button mdm-favicon-search" data-target="cnt_' . esc_attr($cnt) . '">';
                        echo esc_html(__('Search by Name', 'multidomainfavicon-main'));
                    echo '</button>';
                    
                    echo '<button type="button" class="button mdm-favicon-convert" data-target="cnt_' . esc_attr($cnt) . '">';
                        echo esc_html(__('Convert URL', 'multidomainfavicon-main'));
                    echo '</button>';
                    
                    if ($favicon_url) {
                        echo '<button type="button" class="button mdm-favicon-remove" data-target="cnt_' . esc_attr($cnt) . '">';
                            echo esc_html(__('Remove', 'multidomainfavicon-main'));
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
                echo esc_html(__('Upload new, browse media library, search by filename, or convert domain URLs.', 'multidomainfavicon-main'));
                echo '<br>';
                echo esc_html(__('Convert URL: Converts base domain URLs to mapped domain URLs for this specific mapping.', 'multidomainfavicon-main'));
                echo '<br>';
                echo esc_html(__('Supported formats: .ico, .png, .svg (16x16 or 32x32 pixels recommended).', 'multidomainfavicon-main'));
                echo '<br>';
                echo esc_html(__('This will override the default WordPress site icon when visitors access this domain.', 'multidomainfavicon-main'));
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
        $settings_link = '<a href="' . admin_url('tools.php?page=multiple-domain-mapping-on-single-site%2Fmultidomainmapping.php') . '">' . esc_html(__('Settings', 'multidomainfavicon-main')) . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    public function admin_css_inline() {
        ?>
        <style>
        .mdm-favicon-field {
            border-top: 1px solid #ddd;
            padding: 15px;
            margin-top: 15px;
            background: #f9f9f9;
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
        .mdm-favicon-upload {
            background: #0073aa !important;
            border-color: #0073aa !important;
            color: #fff !important;
        }
        .mdm-favicon-browse {
            background: #00a32a !important;
            border-color: #00a32a !important;
            color: #fff !important;
        }
        .mdm-favicon-search {
            background: #f39c12 !important;
            border-color: #f39c12 !important;
            color: #fff !important;
        }
        .mdm-favicon-convert {
            background: #8e44ad !important;
            border-color: #8e44ad !important;
            color: #fff !important;
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
        </style>
        <?php
    }
    
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
                
                uploadMediaUploader = wp.media({
                    title: 'Upload New Favicon',
                    button: { text: 'Use this favicon' },
                    library: { type: ['image/x-icon', 'image/png', 'image/svg+xml', 'image/jpeg', 'image/gif'] },
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
                
                // Use EXACTLY the same configuration as Upload New
                browseMediaUploader = wp.media({
                    title: 'Select Favicon from Media Library',
                    button: { text: 'Use this favicon' },
                    library: { type: ['image/x-icon', 'image/png', 'image/svg+xml', 'image/jpeg', 'image/gif'] },
                    multiple: false
                });
                
                browseMediaUploader.on('select', function() {
                    var attachment = browseMediaUploader.state().get('selection').first().toJSON();
                    updateFaviconField(target, attachment, inputField, wrapper, preview);
                });
                
                browseMediaUploader.open();
            });
            
            // Search by filename
            $(document).on('click', '.mdm-favicon-search', function(e) {
                e.preventDefault();
                
                var button = $(this);
                var target = button.data('target');
                var inputField = $('.mdm-favicon-url[data-target="' + target + '"]');
                var wrapper = button.closest('.mdm-favicon-wrapper');
                var preview = wrapper.siblings('.mdm-favicon-preview');
                
                var searchTerm = prompt('Enter filename to search for:\\n\\nExamples:\\n• favicon.png\\n• logo\\n• icon', 'favicon');
                
                if (searchTerm && searchTerm.trim()) {
                    // Use the same library config as Upload New, but with search
                    var searchMediaUploader = wp.media({
                        title: 'Search Results for: ' + searchTerm,
                        button: { text: 'Use this file' },
                        library: { 
                            type: ['image/x-icon', 'image/png', 'image/svg+xml', 'image/jpeg', 'image/gif'],
                            search: searchTerm.trim()
                        },
                        multiple: false
                    });
                    
                    searchMediaUploader.on('select', function() {
                        var attachment = searchMediaUploader.state().get('selection').first().toJSON();
                        updateFaviconField(target, attachment, inputField, wrapper, preview);
                    });
                    
                    searchMediaUploader.open();
                }
            });
            
            // Convert URL - handles domain mapping URL conversion
            $(document).on('click', '.mdm-favicon-convert', function(e) {
                e.preventDefault();
                
                var button = $(this);
                var target = button.data('target');
                var inputField = $('.mdm-favicon-url[data-target="' + target + '"]');
                var wrapper = button.closest('.mdm-favicon-wrapper');
                var preview = wrapper.siblings('.mdm-favicon-preview');
                
                var currentUrl = inputField.val().trim();
                
                if (!currentUrl) {
                    var suggestedUrl = prompt(
                        'Enter a favicon URL to convert between domains:\\n\\n' +
                        'This will convert base domain URLs to mapped domain URLs for visitors.\\n\\n' +
                        'Example:\\n' +
                        '• https://basedomain.com/wp-content/uploads/favicon.png\\n' +
                        '  -> https://mappeddomain.com/wp-content/uploads/favicon.png',
                        ''
                    );
                    if (suggestedUrl) {
                        currentUrl = suggestedUrl.trim();
                    } else {
                        return;
                    }
                }
                
                // Get the target domain for this specific mapping
                var targetDomain = getTargetDomainForMapping(target);
                
                if (!targetDomain) {
                    alert('Could not determine the target domain for this mapping. Please ensure the domain field is filled.');
                    return;
                }
                
                var convertedUrl = convertDomainUrl(currentUrl, targetDomain);
                
                if (convertedUrl !== currentUrl) {
                    var testImg = new Image();
                    testImg.onload = function() {
                        var filename = convertedUrl.substring(convertedUrl.lastIndexOf('/') + 1);
                        
                        var fakeAttachment = {
                            url: convertedUrl,
                            filename: filename,
                            filesizeHumanReadable: 'Converted URL'
                        };
                        
                        updateFaviconField(target, fakeAttachment, inputField, wrapper, preview);
                        
                        alert('URL converted successfully!\\n\\n' +
                              'From: ' + currentUrl + '\\n' +
                              'To: ' + convertedUrl);
                    };
                    testImg.onerror = function() {
                        var testOriginal = new Image();
                        testOriginal.onload = function() {
                            var filename = currentUrl.substring(currentUrl.lastIndexOf('/') + 1);
                            
                            var fakeAttachment = {
                                url: currentUrl,
                                filename: filename,
                                filesizeHumanReadable: 'Original URL'
                            };
                            
                            updateFaviconField(target, fakeAttachment, inputField, wrapper, preview);
                            
                            alert('Conversion failed, but original URL works. Using original.');
                        };
                        testOriginal.onerror = function() {
                            alert('Neither converted nor original URL could be loaded. Please check the URL.');
                        };
                        testOriginal.src = currentUrl;
                    };
                    testImg.src = convertedUrl;
                } else {
                    alert('URL is already in the correct format or no conversion needed.\\n\\n' +
                          'Current URL: ' + currentUrl + '\\n' +
                          'Target domain: ' + targetDomain + '\\n\\n' +
                          'Make sure the target domain is different from the current URL domain.');
                }
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
            
            // Auto-detect favicon format and show preview when URL is manually entered
            $(document).on('blur', '.mdm-favicon-url', function() {
                var input = $(this);
                var url = input.val().trim();
                var target = input.data('target');
                var wrapper = input.closest('.mdm-favicon-wrapper');
                var preview = wrapper.siblings('.mdm-favicon-preview');
                
                if (url && isValidFaviconUrl(url)) {
                    if (preview.length === 0) {
                        preview = $('<div class="mdm-favicon-preview"></div>');
                        wrapper.after(preview);
                    }
                    
                    var filename = url.substring(url.lastIndexOf('/') + 1);
                    var previewHtml = '<img src="' + url + '" alt="Favicon preview" onerror="this.style.display=\'none\'" />';
                    previewHtml += '<br><small>' + filename + '</small>';
                    preview.html(previewHtml);
                    
                    var buttonContainer = wrapper.find('.mdm-favicon-buttons');
                    if (buttonContainer.find('.mdm-favicon-remove').length === 0) {
                        buttonContainer.append('<button type="button" class="button mdm-favicon-remove" data-target="' + target + '">Remove</button>');
                    }
                } else if (!url) {
                    preview.remove();
                    wrapper.find('.mdm-favicon-remove').remove();
                }
            });
            
            // Shared function to update favicon field
            function updateFaviconField(target, attachment, inputField, wrapper, preview) {
                inputField.val(attachment.url);
                
                if (preview.length === 0) {
                    preview = $('<div class="mdm-favicon-preview"></div>');
                    wrapper.after(preview);
                }
                
                var previewHtml = '<img src="' + attachment.url + '" alt="Favicon preview" />';
                previewHtml += '<br><small>' + attachment.filename + ' (' + attachment.filesizeHumanReadable + ')</small>';
                preview.html(previewHtml);
                
                var buttonContainer = wrapper.find('.mdm-favicon-buttons');
                if (buttonContainer.find('.mdm-favicon-remove').length === 0) {
                    buttonContainer.append('<button type="button" class="button mdm-favicon-remove" data-target="' + target + '">Remove</button>');
                }
            }
            
            // URL conversion helper function
            function convertDomainUrl(url, targetDomain) {
                var baseUrl = mdmFaviconData.baseUrl;
                var baseHost = baseUrl.replace(/https?:\/\//, '').replace(/\/$/, '');
                
                var urlPattern = /https?:\/\/([^\/]+)(\/.*)/;
                var matches = url.match(urlPattern);
                
                if (matches) {
                    var originalDomain = matches[1];
                    var path = matches[2];
                    var protocol = url.match(/^https?:/)[0];
                    
                    // If we have a target domain (from the mapping context)
                    if (targetDomain && targetDomain !== originalDomain) {
                        return protocol + '//' + targetDomain + path;
                    }
                    
                    // Fallback: if URL is from base domain, try to convert to mapped domain
                    if (originalDomain === baseHost) {
                        // We need to determine which mapped domain this is for
                        // Look at the button's context to determine target domain
                        return url; // Return as-is for now, will be handled by button click
                    }
                }
                
                return url;
            }
            
            // Get target domain for a specific mapping
            function getTargetDomainForMapping(target) {
                // Extract mapping info from the button's data-target
                var mappingIndex = target.replace('cnt_', '');
                
                // Look for the domain input field for this mapping
                var domainField = $('input[name*="[cnt_' + mappingIndex + '][domain]"]');
                if (domainField.length > 0) {
                    return domainField.val();
                }
                
                // Fallback: check if we have mapping data
                if (mdmFaviconData.currentMapping && mdmFaviconData.currentMapping.length > 0) {
                    // Try to find the mapping by index
                    var mappingNum = parseInt(mappingIndex);
                    if (mdmFaviconData.currentMapping[mappingNum]) {
                        return mdmFaviconData.currentMapping[mappingNum].domain;
                    }
                }
                
                return null;
            }
            
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
