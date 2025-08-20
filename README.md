# Multi-Domain Favicon Manager

A WordPress plugin that adds unique favicon support for each domain mapping in the [Multiple Domain Mapping on single site](https://wordpress.org/plugins/multiple-domain-mapping-on-single-site/) plugin. Automatically suppresses WordPress default site icons when custom favicons are defined.

**Plugin Repository**: [https://github.com/JediConcepts/MultiDomainFavicon](https://github.com/JediConcepts/MultiDomainFavicon)  
**Support**: dev@jediconcepts.com

## Features

- **Unique favicons per mapped domain** - Set different favicons for each of your mapped domains
- **Media library integration** - Upload new favicons or browse existing media
- **Smart domain conversion** - Automatically converts URLs between base and mapped domains
- **Conflict resolution** - Removes WordPress default site icons to prevent conflicts
- **Search functionality** - Find favicon files by filename when media browser filters them out
- **Multiple file format support** - Works with .ico, .png, .svg, .jpg, and .gif files
- **Auto-preview** - See favicon previews when entering URLs manually

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- **[Multiple Domain Mapping on single site](https://wordpress.org/plugins/multiple-domain-mapping-on-single-site/)** plugin (required dependency)

## Installation

### Method 1: GitHub Download
1. Download the latest release from [GitHub](https://github.com/JediConcepts/MultiDomainFavicon/releases)
2. Upload the `mdm-favicon-manager` folder to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Navigate to **Tools → Multidomain** to configure favicons

### Method 2: WordPress Admin Upload
1. Download the plugin zip from [GitHub](https://github.com/JediConcepts/MultiDomainFavicon)
2. Go to **Plugins → Add New** in WordPress admin
3. Click **Upload Plugin** and select the zip file
4. Activate the plugin
5. Navigate to **Tools → Multidomain** to configure favicons

## Usage

### Setting Up Favicons

1. **Navigate to domain mappings**: Go to **Tools → Multidomain** in your WordPress admin
2. **Find your mapping**: Locate the domain mapping you want to add a favicon for
3. **Choose your method**:
   - **Upload New**: Upload a fresh favicon file from your computer
   - **Browse Media**: Select from existing files in your media library
   - **Search by Name**: Find specific files by filename (useful when media browser doesn't show all files)
   - **Convert URL**: Convert between base domain and mapped domain URLs

### Button Functions

| Button | Purpose | When to Use |
|--------|---------|-------------|
| **Upload New** (Blue) | Upload fresh favicon files | When you have a new favicon on your computer |
| **Browse Media** (Green) | Select from media library | When reusing existing uploaded favicons |
| **Search by Name** (Orange) | Search by filename | When Browse Media doesn't show all files |
| **Convert URL** (Purple) | Convert domain URLs | When you have a base domain URL that needs converting |

### Domain URL Conversion

The **Convert URL** feature helps with domain mapping scenarios:

- **Input**: `https://basedomain.com/wp-content/uploads/favicon.png`
- **Output**: `https://mappeddomain.com/wp-content/uploads/favicon.png`

This is useful because:
1. WordPress stores files on the base domain
2. Visitors access your site via mapped domains
3. Favicons need to be served from the domain visitors see

### File Format Support

Supported favicon formats:
- **.ico** - Traditional favicon format
- **.png** - Modern format, recommended for most uses
- **.svg** - Scalable vector format
- **.jpg/.jpeg** - JPEG images
- **.gif** - GIF images

**Recommended size**: 16x16 or 32x32 pixels

## How It Works

### Frontend Behavior
When a visitor accesses a mapped domain:
1. Plugin detects the current domain mapping
2. Checks if a custom favicon is configured for that mapping
3. Outputs the custom favicon HTML tags
4. Suppresses WordPress default site icon to prevent conflicts

### Admin Interface
The plugin adds favicon management fields to each domain mapping:
- Integrates seamlessly with the existing Multiple Domain Mapping interface
- Provides intuitive upload and selection tools
- Shows preview of selected favicons
- Validates favicon URLs automatically

## Troubleshooting

### Media Library Issues

**Problem**: Browse Media shows fewer images than Upload New
**Solution**: All buttons now use identical configurations. If you still see differences, try the Search by Name feature.

**Problem**: Can't find a specific favicon file
**Solution**: Use the Search by Name button and search for the filename (e.g., "favicon.png" or just "favicon").

### Domain Conversion Issues

**Problem**: Convert URL says "no conversion needed"
**Solution**: Make sure you're entering a URL from a different domain than the target mapping. The plugin converts FROM base domain TO mapped domain.

**Problem**: Converted URL doesn't work
**Solution**: The plugin tests both original and converted URLs. If conversion fails, it will use the original URL that works.

### Favicon Not Showing

**Problem**: Favicon not appearing on frontend
**Solutions**:
1. Clear browser cache (favicons are heavily cached)
2. Check that the favicon URL is accessible
3. Verify the file format is supported
4. Ensure the domain mapping is working correctly

**Problem**: Multiple favicons appearing
**Solution**: The plugin automatically removes WordPress default site icons. If you still see conflicts, check for favicon code in your theme or other plugins.

## FAQ

### Q: Do I need to configure favicons for every mapped domain?
A: No, only configure favicons for domains where you want a different favicon than the default WordPress site icon.

### Q: Can I use the same favicon for multiple mapped domains?
A: Yes, you can select the same favicon file for multiple domain mappings.

### Q: What happens if I don't set a favicon for a mapped domain?
A: The WordPress default site icon will be used (if configured in Customizer → Site Identity).

### Q: Can I use external favicon URLs?
A: Yes, you can manually enter any favicon URL. The plugin will validate that it loads correctly.

### Q: Will this work with CDN or external media storage?
A: Yes, as long as the favicon URLs are accessible from the browser, any URL will work.

## Support

### Getting Help
1. Check this README for common solutions
2. Verify that the [Multiple Domain Mapping plugin](https://wordpress.org/plugins/multiple-domain-mapping-on-single-site/) is installed and active
3. Test with a simple .png favicon first
4. Check browser developer tools for any favicon loading errors
5. Contact support: **dev@jediconcepts.com**

### Reporting Issues
When reporting issues to **dev@jediconcepts.com**, please include:
- WordPress version
- PHP version  
- Multiple Domain Mapping plugin version
- Browser information
- Steps to reproduce the issue
- Any error messages from browser console

You can also report issues on [GitHub](https://github.com/JediConcepts/MultiDomainFavicon/issues).

## Technical Details

### Plugin Structure
```
mdm-favicon-manager/
├── mdm-favicon-manager.php    # Main plugin file
├── README.md                  # This file
└── languages/                 # Translation files (if added)
```

### Database Storage
Favicon URLs are stored in the existing Multiple Domain Mapping plugin's options:
- Option name: `falke_mdm_mappings`
- Favicon data: Stored in each mapping's `favicon` field

### Hooks and Filters
The plugin uses these WordPress hooks:
- `falke_mdma_after_mapping_body` - Adds favicon fields to admin interface
- `falke_mdmf_save_mapping` - Saves favicon data with mappings
- `template_redirect` - Removes default site icons on mapped domains
- `wp_head` - Outputs custom favicon HTML tags

## Changelog

### 1.0.3
- Fixed media library filtering issues
- Improved domain conversion logic
- Enhanced error handling and user feedback
- Added comprehensive search functionality

### 1.0.2
- Added Convert URL functionality
- Improved media browser performance
- Fixed JavaScript conflicts

### 1.0.1
- Initial release
- Basic favicon upload and selection
- WordPress site icon suppression

## License

This plugin is licensed under the GPL2 license.

## Credits

**Developed by**: [JediConcepts](https://jediconcepts.com)  
**Support**: dev@jediconcepts.com  
**Repository**: [https://github.com/JediConcepts/MultiDomainFavicon](https://github.com/JediConcepts/MultiDomainFavicon)

This plugin extends the functionality of the "[Multiple Domain Mapping on single site](https://wordpress.org/plugins/multiple-domain-mapping-on-single-site/)" plugin by Matthias Wagner (FALKEmedia).

---

**Note**: This plugin requires the "[Multiple Domain Mapping on single site](https://wordpress.org/plugins/multiple-domain-mapping-on-single-site/)" plugin to function. Make sure it's installed and activated before using this favicon manager.
