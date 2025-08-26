jQuery(document).ready(function($) {
    var uploadMediaUploader, browseMediaUploader;

    // Upload new favicon
    $(document).on('click', '.multifama-favicon-upload', function(e) {
        e.preventDefault();

        var button = $(this);
        var target = button.data('target');
        var inputField = $('.multifama-favicon-url[data-target="' + target + '"]');
        var wrapper = button.closest('.multifama-favicon-wrapper');
        var preview = wrapper.siblings('.multifama-favicon-preview');

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
    $(document).on('click', '.multifama-favicon-browse', function(e) {
        e.preventDefault();

        var button = $(this);
        var target = button.data('target');
        var inputField = $('.multifama-favicon-url[data-target="' + target + '"]');
        var wrapper = button.closest('.multifama-favicon-wrapper');
        var preview = wrapper.siblings('.multifama-favicon-preview');

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
    $(document).on('click', '.multifama-favicon-search', function(e) {
        e.preventDefault();

        var button = $(this);
        var target = button.data('target');
        var inputField = $('.multifama-favicon-url[data-target="' + target + '"]');
        var wrapper = button.closest('.multifama-favicon-wrapper');
        var preview = wrapper.siblings('.multifama-favicon-preview');

        var searchTerm = prompt('Enter filename to search for:\n\nExamples:\n• favicon.png\n• logo\n• icon', 'favicon');

        if (searchTerm && searchTerm.trim()) {
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
    $(document).on('click', '.multifama-favicon-convert', function(e) {
        e.preventDefault();

        var button = $(this);
        var target = button.data('target');
        var inputField = $('.multifama-favicon-url[data-target="' + target + '"]');
        var wrapper = button.closest('.multifama-favicon-wrapper');
        var preview = wrapper.siblings('.multifama-favicon-preview');

        var currentUrl = inputField.val().trim();

        if (!currentUrl) {
            var suggestedUrl = prompt(
                'Enter a favicon URL to convert between domains:\n\n' +
                'This will convert base domain URLs to mapped domain URLs for visitors.\n\n' +
                'Example: Converts /wp-content/uploads/favicon.png from your main site to the mapped domain.',
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

                alert('URL converted successfully!\n\n' +
                      'From: ' + currentUrl + '\n' +
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
            alert('URL is already in the correct format or no conversion needed.\n\n' +
                  'Current URL: ' + currentUrl + '\n' +
                  'Target domain: ' + targetDomain + '\n\n' +
                  'Make sure the target domain is different from the current URL domain.');
        }
    });

    // Remove favicon
    $(document).on('click', '.multifama-favicon-remove', function(e) {
        e.preventDefault();

        var button = $(this);
        var target = button.data('target');
        var inputField = $('.multifama-favicon-url[data-target="' + target + '"]');
        var wrapper = button.closest('.multifama-favicon-wrapper');
        var preview = wrapper.siblings('.multifama-favicon-preview');

        inputField.val('');
        preview.remove();
        button.remove();
    });

    // Auto-detect favicon format and show preview when URL is manually entered
    $(document).on('blur', '.multifama-favicon-url', function() {
        var input = $(this);
        var url = input.val().trim();
        var target = input.data('target');
        var wrapper = input.closest('.multifama-favicon-wrapper');
        var preview = wrapper.siblings('.multifama-favicon-preview');

        if (url && isValidFaviconUrl(url)) {
            if (preview.length === 0) {
                preview = $('<div class="mdfm-favicon-preview"></div>');
                wrapper.after(preview);
            }

            var filename = url.substring(url.lastIndexOf('/') + 1);
            var previewHtml = '<img src="' + url + '" alt="Favicon preview" onerror="this.style.display=\'none\'" />';
            previewHtml += '<br><small>' + filename + '</small>';
            preview.html(previewHtml);

            var buttonContainer = wrapper.find('.multifama-favicon-buttons');
            if (buttonContainer.find('.multifama-favicon-remove').length === 0) {
                buttonContainer.append('<button type="button" class="button multifama-favicon-remove" data-target="' + target + '">Remove</button>');
            }
        } else if (!url) {
            preview.remove();
            wrapper.find('.multifama-favicon-remove').remove();
        }
    });

    // Shared function to update favicon field
    function updateFaviconField(target, attachment, inputField, wrapper, preview) {
        inputField.val(attachment.url);

        if (preview.length === 0) {
            preview = $('<div class="mdfm-favicon-preview"></div>');
            wrapper.after(preview);
        }

        var previewHtml = '<img src="' + attachment.url + '" alt="Favicon preview" />';
        previewHtml += '<br><small>' + attachment.filename + ' (' + attachment.filesizeHumanReadable + ')</small>';
        preview.html(previewHtml);

        var buttonContainer = wrapper.find('.multifama-favicon-buttons');
        if (buttonContainer.find('.multifama-favicon-remove').length === 0) {
            buttonContainer.append('<button type="button" class="button multifama-favicon-remove" data-target="' + target + '">Remove</button>');
        }
    }

    // URL conversion helper function
    function convertDomainUrl(url, targetDomain) {
        var baseUrl = multiDomainFaviconManagerData.baseUrl;
        var baseHost = baseUrl.replace(/https?:\/\//, '').replace(/\/$/, '');

        var urlPattern = /https?:\/\/([^\/]+)(\/.*)/;
        var matches = url.match(urlPattern);

        if (matches) {
            var originalDomain = matches[1];
            var path = matches[2];
            var protocol = url.match(/^https?:/)[0];

            if (targetDomain && targetDomain !== originalDomain) {
                return protocol + '//' + targetDomain + path;
            }
            if (originalDomain === baseHost) {
                return url;
            }
        }
        return url;
    }

    // Get target domain for a specific mapping
    function getTargetDomainForMapping(target) {
        var mappingIndex = target.replace('cnt_', '');

        var domainField = $('input[name*="[cnt_' + mappingIndex + '][domain]"]');
        if (domainField.length > 0) {
            return domainField.val();
        }

        if (multiDomainFaviconManagerData.currentMapping && multiDomainFaviconManagerData.currentMapping.length > 0) {
            var mappingNum = parseInt(mappingIndex);
            if (multiDomainFaviconManagerData.currentMapping[mappingNum]) {
                return multiDomainFaviconManagerData.currentMapping[mappingNum].domain;
            }
        }
        return null;
    }

    function isValidFaviconUrl(url) {
        var faviconExtensions = ['.ico', '.png', '.svg', '.jpg', '.jpeg', '.gif'];
        var urlLower = url.toLowerCase();
        return faviconExtensions.some(function(ext) {
            return urlLower.includes(ext);
        });
    }
});
