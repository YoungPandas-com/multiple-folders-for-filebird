<?php
/**
 * Admin functionality for Multiple Folders for FileBird
 *
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class MFFB_Admin {
    
    /**
     * Instance of this class
     *
     * @var object
     */
    protected static $instance = null;

    /**
     * Return an instance of this class
     *
     * @return object A single instance of this class
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        // Initialize hooks
        $this->init_hooks();
        
        // Enqueue assets
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Add custom folder selection UI to attachment edit screen
        add_filter('attachment_fields_to_edit', [$this, 'add_multiple_folders_ui'], 99, 2);
        
        // Save custom folder selections
        add_filter('attachment_fields_to_save', [$this, 'save_multiple_folders'], 99, 2);
        
        // Modify FileBird's folder UI in the media library
        add_action('admin_footer', [$this, 'modify_filebird_ui']);
        
        // Add Ajax handler for setting multiple folders
        add_action('wp_ajax_mffb_set_attachment_folders', [$this, 'ajax_set_attachment_folders']);
        
        // Add custom column to media library grid view
        add_filter('manage_media_columns', [$this, 'add_folders_column'], 11);
        add_action('manage_media_custom_column', [$this, 'display_folders_column'], 10, 2);
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts($hook) {
        // Only load on media screens
        if (!in_array($hook, ['upload.php', 'post.php', 'post-new.php'])) {
            return;
        }
        
        // CSS
        wp_enqueue_style(
            'mffb-admin-css',
            MFFB_PLUGIN_URL . 'assets/css/admin.css',
            [],
            MFFB_VERSION
        );
        
        // JavaScript
        wp_enqueue_script(
            'mffb-admin-js',
            MFFB_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery', 'select2'],
            MFFB_VERSION,
            true
        );
        
        // Include Select2 for better folder selection UI
        wp_enqueue_style(
            'select2',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css',
            [],
            '4.1.0-rc.0'
        );
        
        wp_enqueue_script(
            'select2',
            'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js',
            ['jquery'],
            '4.1.0-rc.0',
            true
        );
        
        // Localize script with data and nonce
        wp_localize_script('mffb-admin-js', 'mffb_data', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mffb_nonce'),
            'strings' => [
                'select_folders' => __('Select Folders', 'multiple-folders-for-filebird'),
                'saving' => __('Saving...', 'multiple-folders-for-filebird'),
                'saved' => __('Saved!', 'multiple-folders-for-filebird'),
                'error' => __('Error saving folders.', 'multiple-folders-for-filebird')
            ]
        ]);
    }

    /**
     * Add multiple folders selection UI to attachment edit screen
     * 
     * @param array $form_fields Form fields array
     * @param WP_Post $post Post object
     * @return array Modified form fields
     */
    public function add_multiple_folders_ui($form_fields, $post) {
        // Get all folders
        $all_folders = $this->get_all_folders_hierarchical();
        
        // Get current folders for this attachment
        $current_folders = MFFB_DB::get_instance()->get_attachment_folders($post->ID);
        
        // Create options for select field
        $options = $this->build_folder_options($all_folders, $current_folders);
        
        // Add our custom field after the FileBird folder field
        $form_fields['mffb_folders'] = [
            'label' => __('FileBird Folders', 'multiple-folders-for-filebird'),
            'input' => 'html',
            'html' => $this->render_folders_select_field($post->ID, $options, $current_folders),
            'helps' => __('Select multiple folders where this file should appear', 'multiple-folders-for-filebird'),
        ];
        
        return $form_fields;
    }

    /**
     * Render the multiple folders select field
     * 
     * @param int $attachment_id Attachment ID
     * @param string $options Options HTML
     * @param array $current_folders Current folder IDs
     * @return string HTML for the select field
     */
    private function render_folders_select_field($attachment_id, $options, $current_folders) {
        $output = '<div class="mffb-folders-container">';
        $output .= '<select name="attachments[' . $attachment_id . '][mffb_folders][]" id="mffb-folders-' . $attachment_id . '" class="mffb-folders-select" multiple="multiple" data-attachment-id="' . $attachment_id . '">';
        $output .= $options;
        $output .= '</select>';
        $output .= '<p class="mffb-folders-info"><span class="mffb-folders-count">' . count($current_folders) . '</span> ' . __('folders selected', 'multiple-folders-for-filebird') . '</p>';
        $output .= '</div>';
        
        return $output;
    }

    /**
     * Get all FileBird folders in a hierarchical structure
     * 
     * @return array Folders array
     */
    private function get_all_folders_hierarchical() {
        global $wpdb;
        
        $folders = $wpdb->get_results(
            "SELECT id, name, parent FROM {$wpdb->prefix}fbv
            WHERE created_by = " . apply_filters('fbv_folder_created_by', 0) . "
            ORDER BY ord ASC",
            OBJECT_K
        );
        
        // Convert to hierarchical structure
        $hierarchical = [];
        
        foreach ($folders as $folder) {
            $folder->children = [];
        }
        
        // Build tree structure
        foreach ($folders as $folder_id => $folder) {
            if ($folder->parent === 0) {
                $hierarchical[$folder_id] = $folder;
            } else {
                if (isset($folders[$folder->parent])) {
                    $folders[$folder->parent]->children[$folder_id] = $folder;
                }
            }
        }
        
        return $hierarchical;
    }

    /**
     * Build the options HTML for the select field
     * 
     * @param array $folders Folders array
     * @param array $selected_folders Selected folder IDs
     * @param int $level Nesting level for indentation
     * @return string Options HTML
     */
    private function build_folder_options($folders, $selected_folders, $level = 0) {
        $options = '';
        
        foreach ($folders as $folder) {
            $indent = str_repeat('&mdash; ', $level);
            $selected = in_array($folder->id, $selected_folders) ? 'selected="selected"' : '';
            
            $options .= '<option value="' . $folder->id . '" ' . $selected . '>' . $indent . esc_html($folder->name) . '</option>';
            
            if (!empty($folder->children)) {
                $options .= $this->build_folder_options($folder->children, $selected_folders, $level + 1);
            }
        }
        
        return $options;
    }

    /**
     * Save the multiple folders selection
     * 
     * @param array $post Post data
     * @param array $attachment Attachment data
     * @return array Modified post data
     */
    public function save_multiple_folders($post, $attachment) {
        if (!isset($attachment['mffb_folders'])) {
            return $post;
        }
        
        $attachment_id = $post['ID'];
        $folder_ids = array_map('intval', $attachment['mffb_folders']);
        
        // Save the folders
        MFFB_Core::get_instance()->set_attachment_folders($attachment_id, $folder_ids);
        
        return $post;
    }

    /**
     * Modify FileBird's UI to support multiple folders
     */
    public function modify_filebird_ui() {
        $screen = get_current_screen();
        
        if (!$screen || $screen->base !== 'upload') {
            return;
        }
        
        // Output custom JavaScript to modify FileBird's UI
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Wait for FileBird to initialize
                $(document).on('fbv.init', function() {
                    console.log('Multiple Folders for FileBird: Enhancing FileBird UI');
                    
                    // Override FileBird's context menu to support multiple folders
                    setTimeout(function() {
                        enhanceFileBirdContextMenu();
                    }, 1000);
                });
                
                function enhanceFileBirdContextMenu() {
                    // Look for the move button in FileBird's UI and enhance it
                    if (typeof fbv !== 'undefined' && typeof fbv.deSelectAttachments === 'function') {
                        const originalDeSelectAttachments = fbv.deSelectAttachments;
                        
                        // Override the deselect function to capture selected attachments
                        fbv.deSelectAttachments = function() {
                            const selectedItems = $('.attachments .selected');
                            
                            // Store the IDs for our usage
                            if (selectedItems.length > 0) {
                                const ids = [];
                                selectedItems.each(function() {
                                    ids.push($(this).data('id'));
                                });
                                
                                if (ids.length && $('.fbv-folder:hover').length) {
                                    const folderId = $('.fbv-folder:hover').data('id');
                                    
                                    // Trigger our custom add to folder function
                                    addToAdditionalFolder(ids, folderId);
                                }
                            }
                            
                            // Call the original function
                            return originalDeSelectAttachments.apply(this, arguments);
                        };
                    }
                }
                
                // Function to add files to an additional folder
                function addToAdditionalFolder(attachmentIds, folderId) {
                    if (!attachmentIds.length || !folderId) return;
                    
                    // Show notification
                    showNotification('Adding to folder...', 'info');
                    
                    // Make an AJAX request to add to folder without removing from other folders
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
                                showNotification('Added to folder successfully!', 'success');
                                
                                // Refresh the folder counts
                                if (typeof fbv_refreshCountAll === 'function') {
                                    fbv_refreshCountAll();
                                }
                            } else {
                                showNotification('Error adding to folder', 'error');
                            }
                        },
                        error: function() {
                            showNotification('Error adding to folder', 'error');
                        }
                    });
                }
                
                // Simple notification function
                function showNotification(message, type) {
                    const $notify = $('<div class="mffb-notification mffb-notification-' + type + '">' + message + '</div>');
                    $('body').append($notify);
                    
                    setTimeout(function() {
                        $notify.addClass('mffb-show');
                    }, 10);
                    
                    setTimeout(function() {
                        $notify.removeClass('mffb-show');
                        setTimeout(function() {
                            $notify.remove();
                        }, 300);
                    }, 2000);
                }
                
                // Initialize Select2 for better folder selection
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
            });
        </script>
        <style>
            /* Custom notification styling */
            .mffb-notification {
                position: fixed;
                top: 50px;
                right: 20px;
                padding: 10px 15px;
                background: #fff;
                border-left: 4px solid #00a0d2;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                opacity: 0;
                transform: translateX(30px);
                transition: all 0.3s ease;
                z-index: 999999;
            }
            
            .mffb-notification-success {
                border-left-color: #46b450;
            }
            
            .mffb-notification-error {
                border-left-color: #dc3232;
            }
            
            .mffb-notification-info {
                border-left-color: #00a0d2;
            }
            
            .mffb-notification.mffb-show {
                opacity: 1;
                transform: translateX(0);
            }
        </style>
        <?php
    }

    /**
     * Ajax handler for setting attachment folders
     */
    public function ajax_set_attachment_folders() {
        // Check nonce
        if (!check_ajax_referer('mffb_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }
        
        // Check permissions
        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }
        
        // Get parameters
        $attachment_ids = isset($_POST['attachment_ids']) ? array_map('intval', (array)$_POST['attachment_ids']) : [];
        $folder_id = isset($_POST['folder_id']) ? intval($_POST['folder_id']) : 0;
        $mode = isset($_POST['mode']) ? sanitize_key($_POST['mode']) : 'set';
        
        if (empty($attachment_ids) || $folder_id <= 0) {
            wp_send_json_error(['message' => 'Invalid parameters']);
        }
        
        $db = MFFB_DB::get_instance();
        $success = true;
        
        foreach ($attachment_ids as $attachment_id) {
            if ($mode === 'add') {
                // Add to folder without removing from other folders
                $result = $db->add_attachment_to_folder($attachment_id, $folder_id);
                if (!$result) {
                    $success = false;
                }
            } elseif ($mode === 'remove') {
                // Remove from specific folder
                $result = $db->remove_attachment_from_folder($attachment_id, $folder_id);
                if (!$result) {
                    $success = false;
                }
            } else {
                // Get current folders
                $current_folders = $db->get_attachment_folders($attachment_id);
                
                // Set all folders
                $result = $db->set_attachment_folders($attachment_id, array_unique(array_merge($current_folders, [$folder_id])));
                if (!$result) {
                    $success = false;
                }
            }
        }
        
        if ($success) {
            wp_send_json_success(['message' => 'Folders updated successfully']);
        } else {
            wp_send_json_error(['message' => 'Error updating folders']);
        }
    }

    /**
     * Add custom folders column to media library
     * 
     * @param array $columns Media library columns
     * @return array Modified columns
     */
    public function add_folders_column($columns) {
        // Remove FileBird's original column if it exists
        if (isset($columns['fb_folder'])) {
            unset($columns['fb_folder']);
        }
        
        // Add our enhanced version
        $columns['mffb_folders'] = __('FileBird Folders', 'multiple-folders-for-filebird');
        
        return $columns;
    }

    /**
     * Display folders in the custom column
     * 
     * @param string $column_name Column name
     * @param int $post_id Post ID
     */
    public function display_folders_column($column_name, $post_id) {
        if ($column_name !== 'mffb_folders') {
            return;
        }
        
        // Get all folders for this attachment
        $folder_ids = MFFB_DB::get_instance()->get_attachment_folders($post_id);
        
        if (empty($folder_ids)) {
            echo '<span class="mffb-no-folders">' . __('Uncategorized', 'multiple-folders-for-filebird') . '</span>';
            return;
        }
        
        // Get folder names
        global $wpdb;
        $folder_names = $wpdb->get_results(
            "SELECT id, name FROM {$wpdb->prefix}fbv 
            WHERE id IN (" . implode(',', $folder_ids) . ")",
            OBJECT_K
        );
        
        echo '<div class="mffb-folder-list">';
        
        foreach ($folder_ids as $folder_id) {
            if (isset($folder_names[$folder_id])) {
                echo '<span class="mffb-folder-tag" data-folder-id="' . $folder_id . '">';
                echo esc_html($folder_names[$folder_id]->name);
                echo '</span>';
            }
        }
        
        echo '</div>';
    }
}