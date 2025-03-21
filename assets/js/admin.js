/**
 * Multiple Folders for FileBird - Admin JavaScript
 */
(function($) {
    'use strict';
    
    // Initialize when document is ready
    $(document).ready(function() {
        // Initialize multiple folders functionality
        initMultipleFolders();
        
        // Setup attachment edit screen enhancements
        setupAttachmentEditScreen();
        
        // Handle bulk edit mode
        setupBulkEditMode();
    });
    
    /**
     * Initialize multiple folders functionality
     */
    function initMultipleFolders() {
        // Initialize Select2 for better folder selection
        if ($.fn.select2) {
            $('.mffb-folders-select').select2({
                placeholder: mffb_data.strings.select_folders,
                allowClear: true,
                width: '100%'
            });
            
            // Update folder count when selection changes
            $(document).on('change', '.mffb-folders-select', function() {
                const count = $(this).val() ? $(this).val().length : 0;
                $(this).closest('.mffb-folders-container').find('.mffb-folders-count').text(count);
            });
        }
        
        // Listen for FileBird's initialization
        $(document).on('fbv.init', function() {
            console.log('Multiple Folders for FileBird: Initializing');
            
            // Wait for FileBird UI to be fully loaded
            setTimeout(function() {
                enhanceFileBirdUI();
            }, 500);
        });
    }
    
    /**
     * Enhance FileBird's UI with our modifications
     */
    function enhanceFileBirdUI() {
        // Add "Add to Folder" option to FileBird's context menu
        addContextMenuOptions();
        
        // Enhance the drag and drop functionality
        enhanceDragAndDrop();
        
        // Add multi-folder support to modal window
        enhanceModalFolderSelection();
    }
    
    /**
     * Add custom options to FileBird's context menu
     */
    function addContextMenuOptions() {
        // Monitor for context menu opening
        $(document).on('mousedown', '.attachment', function(e) {
            if (e.which === 3) { // Right click
                // Store the current attachment for reference
                window.mffbCurrentAttachment = $(this).data('id');
            }
        });
        
        // Add our custom menu items when context menu is shown
        $(document).on('contextmenu:filled', function() {
            // Look for the context menu
            const $contextMenu = $('.media-frame .media-toolbar .media-toolbar-secondary .media-frame-actions');
            
            if ($contextMenu.length && !$contextMenu.find('.mffb-add-to-folder').length) {
                // Add our custom button
                $contextMenu.append(
                    $('<button class="button mffb-add-to-folder">').text('Add to Folder')
                );
                
                // Handle our custom button click
                $('.mffb-add-to-folder').on('click', function(e) {
                    e.preventDefault();
                    
                    // Show folder selection dialog
                    showFolderSelectionDialog();
                });
            }
        });
    }
    
    /**
     * Show a dialog to select folders
     */
    function showFolderSelectionDialog() {
        // Get selected attachment IDs
        const selectedAttachments = [];
        
        if (window.mffbCurrentAttachment) {
            selectedAttachments.push(window.mffbCurrentAttachment);
        } else {
            $('.attachments .selected').each(function() {
                selectedAttachments.push($(this).data('id'));
            });
        }
        
        if (!selectedAttachments.length) {
            alert('Please select at least one item.');
            return;
        }
        
        // Create dialog if it doesn't exist
        if (!$('#mffb-folder-dialog').length) {
            const $dialog = $('<div id="mffb-folder-dialog" class="mffb-dialog" title="Add to Folder">');
            
            $dialog.append('<p>Select a folder to add these files to:</p>');
            
            // Get folders from FileBird's data
            const folderList = $('<ul class="mffb-folder-tree">');
            
            // Create tree view
            if (window.fbv_data && window.fbv_data.tree) {
                buildFolderTree(window.fbv_data.tree, folderList);
            }
            
            $dialog.append(folderList);
            
            // Add action buttons
            const $actions = $('<div class="mffb-dialog-actions">');
            $actions.append('<button type="button" class="button button-secondary mffb-dialog-cancel">Cancel</button>');
            $actions.append('<button type="button" class="button button-primary mffb-dialog-add">Add to Folder</button>');
            
            $dialog.append($actions);
            
            // Attach to body
            $('body').append($dialog);
            
            // Setup event handlers
            $('.mffb-folder-tree').on('click', 'li', function(e) {
                e.stopPropagation();
                $('.mffb-folder-tree li').removeClass('selected');
                $(this).addClass('selected');
            });
            
            $('.mffb-dialog-cancel').on('click', function() {
                $('#mffb-folder-dialog').hide();
            });
            
            $('.mffb-dialog-add').on('click', function() {
                const selectedFolder = $('.mffb-folder-tree li.selected').data('id');
                
                if (!selectedFolder) {
                    alert('Please select a folder.');
                    return;
                }
                
                // Add files to the selected folder
                addFilesToFolder(selectedAttachments, selectedFolder);
                
                // Hide dialog
                $('#mffb-folder-dialog').hide();
            });
        }
        
        // Show the dialog
        $('#mffb-folder-dialog').show();
    }
    
    /**
     * Build folder tree for selection dialog
     * 
     * @param {Array} folders Folders array
     * @param {jQuery} $element Element to append to
     */
    function buildFolderTree(folders, $element) {
        folders.forEach(function(folder) {
            const $item = $('<li>').text(folder.text).data('id', folder.id);
            
            if (folder.children && folder.children.length) {
                const $sublist = $('<ul>');
                buildFolderTree(folder.children, $sublist);
                $item.append($sublist);
            }
            
            $element.append($item);
        });
    }
    
    /**
     * Add files to a folder without removing from other folders
     * 
     * @param {Array} attachmentIds Attachment IDs
     * @param {Number} folderId Folder ID
     */
    function addFilesToFolder(attachmentIds, folderId) {
        // Show notification
        showNotification(mffb_data.strings.saving, 'info');
        
        // Make AJAX request to add files to folder
        $.ajax({
            url: mffb_data.ajax_url,
            type: 'POST',
            data: {
                action: 'mffb_set_attachment_folders',
                attachment_ids: attachmentIds,
                folder_id: folderId,
                mode: 'add',
                nonce: mffb_data.nonce
            },
            success: function(response) {
                if (response.success) {
                    showNotification(mffb_data.strings.saved, 'success');
                    
                    // Refresh FileBird's counts
                    if (typeof fbv !== 'undefined' && typeof fbv.refreshCountAll === 'function') {
                        fbv.refreshCountAll();
                    }
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
     * Enhance FileBird's drag and drop functionality
     */
    function enhanceDragAndDrop() {
        // Monitor drag and drop events
        $(document).on('mousedown', '.attachments .attachment', function() {
            // Tag attachments that are being dragged
            $(this).addClass('mffb-dragging');
        });
        
        // Override FileBird's drop handler if possible
        if (typeof fbv !== 'undefined' && typeof fbv.droppedAttachments === 'function') {
            const originalDropHandler = fbv.droppedAttachments;
            
            fbv.droppedAttachments = function(folder) {
                // Check if we should add to folder instead of moving
                const draggedItems = $('.mffb-dragging');
                
                if (draggedItems.length && (window.event && (window.event.ctrlKey || window.event.metaKey))) {
                    // Add to folder without removing from other folders (Ctrl/Cmd key pressed)
                    const ids = [];
                    
                    draggedItems.each(function() {
                        ids.push($(this).data('id'));
                    });
                    
                    // Add to folder
                    addFilesToFolder(ids, folder.id);
                    
                    // Reset dragging state
                    $('.mffb-dragging').removeClass('mffb-dragging');
                    
                    return;
                }
                
                // Default behavior (move to folder)
                $('.mffb-dragging').removeClass('mffb-dragging');
                return originalDropHandler.apply(this, arguments);
            };
        }
    }
    
    /**
     * Enhance folder selection in the media modal
     */
    function enhanceModalFolderSelection() {
        // Look for FileBird's folder selector in media modal
        const checkForFolderSelector = setInterval(function() {
            const $folderSelector = $('.media-frame .attachments-browser .media-toolbar .fbv-filter-dropdown');
            
            if ($folderSelector.length && !$folderSelector.hasClass('mffb-enhanced')) {
                // Mark as enhanced
                $folderSelector.addClass('mffb-enhanced');
                
                // Add info about multi-folder capability
                $folderSelector.after(
                    $('<p class="mffb-info">').text('Tip: Hold Ctrl/Cmd when dragging to add to folder without moving')
                );
                
                // Clear interval
                clearInterval(checkForFolderSelector);
            }
        }, 500);
        
        // Clear check after 10 seconds (prevent infinite checking)
        setTimeout(function() {
            clearInterval(checkForFolderSelector);
        }, 10000);
    }
    
    /**
     * Setup attachment edit screen enhancements
     */
    function setupAttachmentEditScreen() {
        // Handle folder selection change in attachment edit
        $(document).on('change', '.mffb-folders-select', function() {
            const $this = $(this);
            const attachmentId = $this.data('attachment-id');
            const selectedFolders = $this.val() || [];
            
            // Disable the select during the save operation
            $this.prop('disabled', true);
            
            // Show saving notification
            const $container = $this.closest('.mffb-folders-container');
            $container.find('.mffb-folders-info').text(mffb_data.strings.saving);
            
            // Save via AJAX
            $.ajax({
                url: mffb_data.ajax_url,
                type: 'POST',
                data: {
                    action: 'mffb_set_attachment_folders',
                    attachment_ids: [attachmentId],
                    folder_ids: selectedFolders,
                    mode: 'set',
                    nonce: mffb_data.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $container.find('.mffb-folders-info').html('<span class="mffb-folders-count">' + selectedFolders.length + '</span> ' + 'folders selected');
                    } else {
                        $container.find('.mffb-folders-info').text(mffb_data.strings.error);
                    }
                    $this.prop('disabled', false);
                },
                error: function() {
                    $container.find('.mffb-folders-info').text(mffb_data.strings.error);
                    $this.prop('disabled', false);
                }
            });
        });
    }
    
    /**
     * Setup bulk edit mode enhancements
     */
    function setupBulkEditMode() {
        // Check if we're in the media grid view
        if ($('.media-frame').length) {
            // Monitor for bulk edit mode
            $(document).on('click', '.select-mode-toggle-button', function() {
                // Wait for bulk edit mode to initialize
                setTimeout(function() {
                    addBulkEditFolderButton();
                }, 100);
            });
        }
    }
    
    /**
     * Add a bulk edit folder button
     */
    function addBulkEditFolderButton() {
        const $bulkActions = $('.media-toolbar-secondary .media-button');
        
        // Add our button if it doesn't exist
        if ($bulkActions.length && !$('.mffb-bulk-add-to-folder').length) {
            $bulkActions.first().after(
                $('<button type="button" class="button media-button mffb-bulk-add-to-folder">').text('Add to Folder')
            );
            
            // Handle button click
            $('.mffb-bulk-add-to-folder').on('click', function() {
                showFolderSelectionDialog();
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