/**
 * Multiple Folders for FileBird - Admin JavaScript
 */
(function($) {
    'use strict';
    
    // Initialize when document is ready
    $(document).ready(function() {
        // Initialize multiple folders enhancement
        initMultipleFolders();
    });
    
    /**
     * Initialize multiple folders functionality
     */
    function initMultipleFolders() {
        // Enhance the FileBird folder selection UI
        enhanceFileBirdFolderUI();
        
        // Listen for FileBird's initialization in Media Library
        $(document).on('fbv.init', function() {
            console.log('Multiple Folders for FileBird: Initializing media library enhancements');
            enhanceMediaLibraryUI();
        });
    }
    
    /**
     * Enhance FileBird's folder selection UI in attachment edit screen
     */
    function enhanceFileBirdFolderUI() {
        // Wait for DOM to be ready with the attachment edit screen
        if ($('.fbv-attachment-edit-wrapper').length > 0) {
            initAttachmentEditEnhancements();
        } else {
            // For dynamically loaded content (like in modal)
            $(document).on('click', '.attachment-details', function() {
                setTimeout(function() {
                    initAttachmentEditEnhancements();
                }, 300);
            });
        }
        
        // Listen for the folder selection popup to open
        $(document).on('fbv_after_open_folders_dropdown', function() {
            enhanceFolderSelectionPopup();
        });
    }
    
    /**
     * Initialize enhancements for the attachment edit screen
     */
    function initAttachmentEditEnhancements() {
        // Add toggle button for multi-select mode
        $('.fbv-attachment-edit-wrapper').each(function() {
            const $wrapper = $(this);
            
            // Only add if not already enhanced
            if (!$wrapper.hasClass('mffb-enhanced')) {
                $wrapper.addClass('mffb-enhanced');
                
                // Check if it has multiple folders data
                if ($wrapper.data('multiple')) {
                    $wrapper.addClass('mffb-multiple-folders');
                    
                    // Get the multiple folders
                    const folderIds = $wrapper.data('multiple').toString().split(',');
                    
                    // Update the UI to show multiple selection
                    if (folderIds.length > 1) {
                        $wrapper.addClass('mffb-has-multiple');
                        
                        // Add indicator of multiple folders
                        $wrapper.append('<span class="mffb-multi-indicator">' + folderIds.length + '</span>');
                    }
                }
                
                // Add multi-select toggle if not already added
                if (!$wrapper.find('.mffb-toggle-multi').length) {
                    $wrapper.append('<span class="mffb-toggle-multi" title="' + mffb_data.strings.click_to_toggle + '">+</span>');
                }
                
                // Handle clicks on the toggle button
                $wrapper.find('.mffb-toggle-multi').on('click', function(e) {
                    e.stopPropagation();
                    toggleMultiSelectMode($wrapper);
                });
            }
        });
    }
    
    /**
     * Toggle multi-select mode for a folder wrapper
     * 
     * @param {jQuery} $wrapper The folder wrapper element
     */
    function toggleMultiSelectMode($wrapper) {
        $wrapper.toggleClass('mffb-multi-select-mode');
        
        const isMultiMode = $wrapper.hasClass('mffb-multi-select-mode');
        const $toggle = $wrapper.find('.mffb-toggle-multi');
        
        if (isMultiMode) {
            $toggle.text('✓');
            $toggle.attr('title', mffb_data.strings.multi_select_enabled);
            
            // Add a hidden input to indicate multi-select mode is active
            $wrapper.append('<input type="hidden" name="attachments[' + $wrapper.data('attachment-id') + '][fbv_multi_mode]" value="true">');
            
            // Show notification
            showNotification(mffb_data.strings.multi_select_enabled, 'info');
        } else {
            $toggle.text('+');
            $toggle.attr('title', mffb_data.strings.click_to_toggle);
            
            // Remove the hidden input
            $wrapper.find('input[name$="[fbv_multi_mode]"]').remove();
        }
    }
    
    /**
     * Enhance the folder selection popup to support multiple selections
     */
    function enhanceFolderSelectionPopup() {
        const $popup = $('.fbv-folders-dropdown');
        
        if ($popup.length && !$popup.hasClass('mffb-enhanced')) {
            $popup.addClass('mffb-enhanced');
            
            // Check if we're in multi-select mode
            const $activeWrapper = $('.fbv-attachment-edit-wrapper.mffb-multi-select-mode');
            
            if ($activeWrapper.length) {
                // Add multi-select indicator to the popup
                $popup.addClass('mffb-multi-select-mode');
                $popup.prepend('<div class="mffb-multi-select-indicator">' + mffb_data.strings.multi_select_enabled + '</div>');
                
                // Get current folder IDs
                const attachmentId = $activeWrapper.data('attachment-id');
                const currentFolderIds = $activeWrapper.data('multiple') ? 
                    $activeWrapper.data('multiple').toString().split(',').map(Number) : 
                    [$activeWrapper.data('folder-id')];
                
                // Highlight folders that are already selected
                if (currentFolderIds.length) {
                    setTimeout(function() {
                        $('.fbv-dropdown-item').each(function() {
                            const folderId = $(this).data('id');
                            if (currentFolderIds.includes(folderId)) {
                                $(this).addClass('mffb-already-selected');
                            }
                        });
                    }, 50);
                }
                
                // Modify the click behavior to support multiple selections
                $(document).off('click.fbv.dropdown.item');
                $(document).on('click.fbv.dropdown.item', '.fbv-dropdown-item', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const $item = $(this);
                    const folderId = $item.data('id');
                    
                    // Toggle selection for this folder
                    if ($item.hasClass('mffb-already-selected')) {
                        // Remove from selection
                        $item.removeClass('mffb-already-selected');
                        
                        // Update the data attribute
                        const updatedFolderIds = currentFolderIds.filter(id => id !== folderId);
                        $activeWrapper.data('multiple', updatedFolderIds.join(','));
                        
                        // Update via AJAX
                        updateAttachmentFolders(attachmentId, updatedFolderIds);
                    } else {
                        // Add to selection
                        $item.addClass('mffb-already-selected');
                        
                        // Update the data attribute
                        currentFolderIds.push(folderId);
                        $activeWrapper.data('multiple', currentFolderIds.join(','));
                        
                        // Update via AJAX
                        updateAttachmentFolders(attachmentId, currentFolderIds);
                    }
                    
                    // Prevent closing the dropdown
                    return false;
                });
                
                // Add a close button to finalize selection
                if (!$popup.find('.mffb-close-button').length) {
                    $popup.append('<div class="mffb-close-button">Done</div>');
                    
                    $popup.find('.mffb-close-button').on('click', function() {
                        $popup.hide();
                    });
                }
            }
        }
    }
    
    /**
     * Update attachment folders via AJAX
     * 
     * @param {Number} attachmentId Attachment ID
     * @param {Array} folderIds Array of folder IDs
     */
    function updateAttachmentFolders(attachmentId, folderIds) {
        $.ajax({
            url: mffb_data.ajax_url,
            type: 'POST',
            data: {
                action: 'mffb_set_attachment_folders',
                attachment_id: attachmentId,
                folder_ids: folderIds,
                mode: 'set',
                nonce: mffb_data.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Update the UI to reflect the new folders
                    const $wrapper = $('.fbv-attachment-edit-wrapper[data-attachment-id="' + attachmentId + '"]');
                    
                    if (folderIds.length > 1) {
                        $wrapper.addClass('mffb-has-multiple');
                        
                        // Update or add multi indicator
                        if ($wrapper.find('.mffb-multi-indicator').length) {
                            $wrapper.find('.mffb-multi-indicator').text(folderIds.length);
                        } else {
                            $wrapper.append('<span class="mffb-multi-indicator">' + folderIds.length + '</span>');
                        }
                        
                        // Show notification
                        showNotification('File is now in ' + folderIds.length + ' folders', 'success');
                    } else if (folderIds.length === 1) {
                        $wrapper.removeClass('mffb-has-multiple');
                        $wrapper.find('.mffb-multi-indicator').remove();
                        
                        // Update the text input with the folder name (if available)
                        if (response.data && response.data.folders && response.data.folders.length) {
                            $wrapper.find('input[type="text"]').val(response.data.folders[0].name);
                        }
                        
                        // Show notification
                        showNotification('Folder updated successfully', 'success');
                    } else {
                        $wrapper.removeClass('mffb-has-multiple');
                        $wrapper.find('.mffb-multi-indicator').remove();
                        $wrapper.find('input[type="text"]').val('Uncategorized');
                        
                        // Show notification
                        showNotification('File removed from all folders', 'info');
                    }
                    
                    // Update the data attributes
                    $wrapper.data('multiple', folderIds.join(','));
                    $wrapper.data('folder-id', folderIds.length ? folderIds[0] : 0);
                } else {
                    showNotification(mffb_data.strings.error, 'error');
                }
            },
            error: function() {
                showNotification(mffb_data.strings.error, 'error');
            }
        });
    }
    
    /**
     * Enhance the media library UI
     */
    function enhanceMediaLibraryUI() {
        // Add multi-folder support for drag and drop
        enhanceDragAndDrop();
        
        // Add multi-folder column view
        enhanceMediaLibraryColumns();
    }
    
    /**
     * Enhance drag and drop in media library
     */
    function enhanceDragAndDrop() {
        // Monitor keypress state for Ctrl/Cmd key
        let ctrlKeyPressed = false;
        
        $(document).on('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                ctrlKeyPressed = true;
                $('.fbv-tree').addClass('mffb-multi-drag-mode');
            }
        }).on('keyup', function(e) {
            if (!e.ctrlKey && !e.metaKey) {
                ctrlKeyPressed = false;
                $('.fbv-tree').removeClass('mffb-multi-drag-mode');
            }
        });
        
        // Add info tooltip about multi-drag
        if ($('.fbv-header').length && !$('.mffb-drag-tip').length) {
            $('.fbv-header').append('<div class="mffb-drag-tip">Hold Ctrl/Cmd when dropping files to add to folder without moving</div>');
        }
        
        // Override FileBird's drop handler if possible
        if (typeof window.fbv !== 'undefined' && typeof window.fbv.droppedAttachments === 'function') {
            const originalDropHandler = window.fbv.droppedAttachments;
            
            window.fbv.droppedAttachments = function(folder) {
                // Add to folder without removing from other folders when Ctrl/Cmd is pressed
                if (ctrlKeyPressed) {
                    const ids = [];
                    const selectedItems = $('.attachment.selected');
                    
                    selectedItems.each(function() {
                        ids.push($(this).data('id'));
                    });
                    
                    if (ids.length) {
                        // Add to folder via our AJAX handler
                        addToAdditionalFolder(ids, folder.id);
                        
                        // Show notification
                        showNotification('Added to folder without moving', 'success');
                        
                        // Prevent default handling
                        return;
                    }
                }
                
                // Default behavior (move to folder)
                return originalDropHandler.apply(this, arguments);
            };
        }
    }
    
    /**
     * Add files to an additional folder
     * 
     * @param {Array} attachmentIds Attachment IDs
     * @param {Number} folderId Folder ID
     */
    function addToAdditionalFolder(attachmentIds, folderId) {
        // Make sure we have valid data
        if (!attachmentIds.length || !folderId) return;
        
        // Show notification
        showNotification('Adding to folder...', 'info');
        
        // Add to folder via AJAX
        attachmentIds.forEach(function(attachmentId) {
            $.ajax({
                url: mffb_data.ajax_url,
                type: 'POST',
                data: {
                    action: 'mffb_set_attachment_folders',
                    attachment_id: attachmentId,
                    folder_ids: [folderId],
                    mode: 'add',
                    nonce: mffb_data.nonce
                },
                success: function(response) {
                    if (!response.success) {
                        showNotification(mffb_data.strings.error, 'error');
                    }
                }
            });
        });
        
        // Refresh FileBird's folder counts
        setTimeout(function() {
            if (typeof window.fbv !== 'undefined' && typeof window.fbv.refreshCountAll === 'function') {
                window.fbv.refreshCountAll();
            }
        }, 500);
    }
    
    /**
     * Enhance the media library columns
     */
    function enhanceMediaLibraryColumns() {
        // Add a custom column for folder information
        if (typeof window.fbv !== 'undefined') {
            // Hook into FileBird's attachment rendering
            const originalRenderAttachment = window.fbv.renderAttachment;
            
            if (typeof originalRenderAttachment === 'function') {
                window.fbv.renderAttachment = function(attachment) {
                    const result = originalRenderAttachment.apply(this, arguments);
                    
                    // After rendering, add our custom folder indicator
                    setTimeout(function() {
                        addFolderIndicator(attachment);
                    }, 100);
                    
                    return result;
                };
            }
        }
    }
    
    /**
     * Add folder indicator to attachment
     * 
     * @param {Object} attachment Attachment object
     */
    function addFolderIndicator(attachment) {
        if (!attachment || !attachment.id) return;
        
        const $attachment = $('.attachment[data-id="' + attachment.id + '"]');
        
        if ($attachment.length && !$attachment.find('.mffb-folder-count').length) {
            // Get folders via AJAX
            $.ajax({
                url: mffb_data.ajax_url,
                type: 'POST',
                data: {
                    action: 'mffb_get_attachment_folders',
                    attachment_id: attachment.id,
                    nonce: mffb_data.nonce
                },
                success: function(response) {
                    if (response.success && response.data && response.data.folders) {
                        const folderCount = response.data.folders.length;
                        
                        if (folderCount > 1) {
                            // Add folder count badge
                            $attachment.append('<div class="mffb-folder-count" title="This file is in ' + folderCount + ' folders">' + folderCount + '</div>');
                        }
                    }
                }
            });
        }
    }
    
    /**
     * Show a notification message
     * 
     * @param {String} message Message text
     * @param {String} type Notification type (success, error, info)
     */
    function showNotification(message, type) {
        // Remove any existing notification
        $('.mffb-notification').remove();
        
        // Create new notification
        const $notification = $('<div class="mffb-notification mffb-notification-' + type + '">' + message + '</div>');
        $('body').append($notification);
        
        // Show notification
        setTimeout(function() {
            $notification.addClass('mffb-show');
        }, 10);
        
        // Hide after 2 seconds
        setTimeout(function() {
            $notification.removeClass('mffb-show');
            setTimeout(function() {
                $notification.remove();
            }, 300);
        }, 2000);
    }
    
})(jQuery);