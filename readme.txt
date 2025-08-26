=== Multi-Domain Favicon Manager ===
Contributors: jediconcepts
Tags: favicon, domain mapping, multisite, domains, icons
Donate link: https://github.com/jediconcepts/multi-domain-favicon-manager
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.7
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Unique favicon support for each domain mapping in Multiple Domain Mapping plugin.

== Description ==

A WordPress plugin that adds unique favicon support for each domain mapping in the [Multiple Domain Mapping on single site](https://wordpress.org/plugins/multiple-domain-mapping-on-single-site/) plugin.

**Plugin Repository**: [https://github.com/jediconcepts/multi-domain-favicon-manager]
**Support**: dev@jediconcepts.com

= Features =

* **Unique favicons per mapped domain** - Set different favicons for each of your mapped domains
* **Media library integration** - Upload new favicons or browse existing media
* **Smart domain conversion** - Automatically converts URLs between base and mapped domains
* **Conflict resolution** - Removes WordPress default site icons to prevent conflicts
* **Search functionality** - Find favicon files by filename when media browser filters them out
* **Multiple file format support** - Works with .ico, .png, .svg, .jpg, and .gif files
* **Auto-preview** - See favicon previews when entering URLs manually

= Requirements =

* WordPress 5.0 or higher
* PHP 7.4 or higher
* **[Multiple Domain Mapping on single site](https://wordpress.org/plugins/multiple-domain-mapping-on-single-site/)** plugin (required dependency)

= How It Works =

**Frontend Behavior**
When a visitor accesses a mapped domain:

1. Plugin detects the current domain mapping
1. Checks if a custom favicon is configured for that mapping
1. Outputs the custom favicon HTML tags
1. Suppresses WordPress default site icon to prevent conflicts

**Admin Interface**
The plugin adds favicon management fields to each domain mapping:

* Integrates seamlessly with the existing Multiple Domain Mapping interface
* Provides intuitive upload and selection tools
* Shows preview of selected favicons
* Validates favicon URLs automatically

= Button Functions =

* **Upload New** (Blue) - Upload fresh favicon files from your computer
* **Browse Media** (Green) - Select from existing files in your media library
* **Search by Name** (Orange) - Find specific files by filename when media browser doesn't show all files
* **Convert URL** (Purple) - Convert between base domain and mapped domain URLs

= File Format Support =

Supported favicon formats:

* **.ico** - Traditional favicon format
* **.png** - Modern format, recommended for most uses
* **.svg** - Scalable vector format
* **.jpg/.jpeg** - JPEG images
* **.gif** - GIF images

**Recommended size**: 16x16 or 32x32 pixels

== Installation ==

= Method 1: GitHub Download =
1. Download the latest release from [GitHub](https://github.com/jediconcepts/multi-domain-favicon-manager/releases)
1. Upload the `multi-domain-favicon` folder to `/wp-content/plugins/`
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Navigate to **Tools → Multidomain** to configure favicons

= Method 2: WordPress Admin Upload =
1. Download the plugin zip from [GitHub](https://github.com/jediconcepts/multi-domain-favicon-manager)
1. Go to **Plugins → Add New** in WordPress admin
1. Click **Upload Plugin** and select the zip file
1. Activate the plugin
1. Navigate to **Tools → Multidomain** to configure favicons

== Frequently Asked Questions ==

= Do I need to configure favicons for every mapped domain? =

No, only configure favicons for domains where you want a different favicon than the default WordPress site icon.

= Can I use the same favicon for multiple mapped domains? =

Yes, you can select the same favicon file for multiple domain mappings.

= What happens if I don't set a favicon for a mapped domain? =

The WordPress default site icon will be used (if configured in Customizer → Site Identity).

= Can I use external favicon URLs? =

Yes, you can manually enter any favicon URL. The plugin will validate that it loads correctly.

= Will this work with CDN or external media storage? =

Yes, as long as the favicon URLs are accessible from the browser, any URL will work.

= The Convert URL feature says "no conversion needed" - what does this mean? =

Make sure you're entering a URL from a different domain than the target mapping. The plugin converts FROM base domain TO mapped domain.

= My favicon isn't showing on the frontend - what should I check? =

1. Clear browser cache (favicons are heavily cached)
1. Check that the favicon URL is accessible
1. Verify the file format is supported
1. Ensure the domain mapping is working correctly

= I see multiple favicons appearing - how do I fix this? =

The plugin automatically removes WordPress default site icons. If you still see conflicts, check for favicon code in your theme or other plugins.

== Screenshots ==

1. Favicon management interface integrated with Multiple Domain Mapping settings
2. Media library integration with Upload New, Browse Media, and Search by Name options
3. Favicon preview showing selected icon with file information
4. Convert URL functionality for domain mapping scenarios

== Changelog ==

= 1.0.7 =
* Fixed WordPress.org plugin directory review issues
* Updated function/class/constant naming for better uniqueness (MULTIFAMA_ prefix)
* Removed example domain references in JavaScript
* Improved code compliance with WordPress plugin guidelines
* Enhanced security and naming conventions

= 1.0.6 =
* Updated WordPress compatibility to 6.8
* Fixed JavaScript string escaping issues
* Improved translator comments for internationalization
* Updated repository URL to lowercase format
* Enhanced error handling and user feedback
* Fixed text domain to match plugin slug
* Improved media library filtering

= 1.0.3 =
* Fixed media library filtering issues
* Improved domain conversion logic
* Enhanced error handling and user feedback
* Added comprehensive search functionality

= 1.0.2 =
* Added Convert URL functionality
* Improved media browser performance
* Fixed JavaScript conflicts

= 1.0.1 =
* Initial release
* Basic favicon upload and selection
* WordPress site icon suppression

== Upgrade Notice ==

= 1.0.6 =
Important update with WordPress 6.8 compatibility, improved internationalization support, and bug fixes. Recommended for all users.

= 1.0.3 =
Major improvements to media library functionality and domain conversion logic. Upgrade recommended for better user experience.

= 1.0.1 =
Initial release of Multi-Domain Favicon Manager. Install to add unique favicon support for each domain mapping.
