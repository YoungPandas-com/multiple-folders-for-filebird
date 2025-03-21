<?php
/**
 * Core functionality for Multiple Folders for FileBird
 *
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class MFFB_Core {
    
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
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Enhance FileBird's folder UI in the attachment edit screen
        add_filter('attachment_fields_to_edit', [$this, 'enhance_filebird_folder_ui'], 20, 2);
        
        // Handle saving multiple folders
        add_filter('attachment_fields_to_save', [$this, 'save_multiple_folders'], 99, 2);
        
        // Add AJAX handlers for folder operations
        add_action('wp_ajax_mffb_get_attachment_folders', [$this, 'ajax_get_attachment_folders']);
        add_action('wp_ajax_mffb_set_attachment_folders', [$this, 'ajax_set_attachment_folders']);
        
        // Modify FileBird's folder display
        add_filter('fbv_folder_from_post_id', [$this, 'show_multiple_folders'], 10, 1);
        
        // Filter to modify folder queries to include files in multiple folders
        add_filter('fbv_get_count_where_query', [$this, 'modify_folder_count_query'], 10, 1);
        add_filter('fbv_all_folders_and_count', [$this, 'modify_all_folders_count'], 10, 2);
        
        // Enqueue scripts and styles
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
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
            ['jquery'],
            MFFB_VERSION,
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
                'error' => __('Error saving folders.', 'multiple-folders-for-filebird'),
                'multi_select_enabled' => __('Multi-select enabled', 'multiple-folders-for-filebird'),
                'click_to_toggle' => __('Click to toggle multi-select mode', 'multiple-folders-for-filebird')
            ]
        ]);
    }

    /**
     * Enhance FileBird's folder UI to support multiple folders
     * 
     * @param array $form_fields Form fields array
     * @param WP_Post $post Post object
     * @return array Modified form fields
     */
    public function enhance_filebird_folder_ui($form_fields, $post) {
        // Check if FileBird's field exists
        if (!isset($form_fields['fbv'])) {
            return $form_fields;
        }
        
        // Get all folder IDs for this attachment
        $folder_ids = MFFB_DB::get_instance()->get_attachment_folders($post->ID);
        
        // Add data attribute for multiple folders
        if (count($folder_ids) > 1) {
            $folder_names = $this->get_folder_names($folder_ids);
            $folder_list = implode(', ', $folder_names);
            
            // Modify FileBird's field to show it's in multiple folders
            $form_fields['fbv']['html'] = str_replace(
                'data-attachment-id=',
                'data-multiple="' . implode(',', $folder_ids) . '" data-attachment-id=',
                $form_fields['fbv']['html']
            );
            
            // Add indicator that it's in multiple folders
            $form_fields['fbv']['helps'] = sprintf(
                __('This file is in multiple folders: %s. Click to modify.', 'multiple-folders-for-filebird'),
                $folder_list
            );
        } else {
            // Add our multi-select toggle button
            $form_fields['fbv']['html'] = str_replace(
                '</div>',
                '<span class="mffb-toggle-multi" title="' . esc_attr__('Enable multi-select mode', 'multiple-folders-for-filebird') . '">+</span></div>',
                $form_fields['fbv']['html']
            );
        }
        
        return $form_fields;
    }

    /**
     * Get folder names from folder IDs
     * 
     * @param array $folder_ids Array of folder IDs
     * @return array Array of folder names
     */
    private function get_folder_names($folder_ids) {
        global $wpdb;
        
        if (empty($folder_ids)) {
            return [];
        }
        
        $folder_table = $wpdb->prefix . 'fbv';
        $placeholder = implode(',', array_fill(0, count($folder_ids), '%d'));
        
        $query = $wpdb->prepare(
            "SELECT name FROM $folder_table WHERE id IN ($placeholder)",
            $folder_ids
        );
        
        return $wpdb->get_col($query);
    }

    /**
     * Save multiple folders when attachment is updated
     * 
     * @param array $post Post data
     * @param array $attachment Attachment data
     * @return array Modified post data
     */
    public function save_multiple_folders($post, $attachment) {
        // Check if our multi folders data is present
        if (isset($attachment['fbv_folders'])) {
            $folder_ids = array_map('intval', explode(',', $attachment['fbv_folders']));
            if (!empty($folder_ids)) {
                MFFB_DB::get_instance()->set_attachment_folders($post['ID'], $folder_ids);
            }
        } elseif (isset($attachment['fbv'])) {
            // If regular FileBird is operating, make sure to handle it as an additional folder
            $folder_id = intval($attachment['fbv']);
            if ($folder_id > 0) {
                // Get current folders
                $current_folders = MFFB_DB::get_instance()->get_attachment_folders($post['ID']);
                
                // If multi-select mode is active, add to existing folders
                if (isset($attachment['fbv_multi_mode']) && $attachment['fbv_multi_mode'] === 'true') {
                    if (!in_array($folder_id, $current_folders)) {
                        $current_folders[] = $folder_id;
                        MFFB_DB::get_instance()->set_attachment_folders($post['ID'], $current_folders);
                    }
                } else {
                    // Regular mode - replace with single folder
                    MFFB_DB::get_instance()->set_attachment_folders($post['ID'], [$folder_id]);
                }
            }
        }
        
        return $post;
    }

    /**
     * Modify the folders display to show multiple folders
     * 
     * @param array $folders The folders data
     * @return array Modified folders data
     */
    public function show_multiple_folders($folders) {
        // We'll keep FileBird's behavior for now, but this hook allows us to modify it later
        return $folders;
    }

    /**
     * AJAX handler for getting attachment folders
     */
    public function ajax_get_attachment_folders() {
        // Check nonce
        if (!check_ajax_referer('mffb_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Invalid nonce']);
        }
        
        // Check permissions
        if (!current_user_can('upload_files')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }
        
        // Get the attachment ID
        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
        
        if (!$attachment_id) {
            wp_send_json_error(['message' => 'Invalid attachment ID']);
        }
        
        // Get all folders for this attachment
        $folder_ids = MFFB_DB::get_instance()->get_attachment_folders($attachment_id);
        
        // Get folder names
        $folder_names = $this->get_folder_names($folder_ids);
        
        // Create response with folder data
        $folders = [];
        foreach ($folder_ids as $key => $id) {
            $folders[] = [
                'id' => $id,
                'name' => isset($folder_names[$key]) ? $folder_names[$key] : 'Unknown'
            ];
        }
        
        wp_send_json_success([
            'attachment_id' => $attachment_id,
            'folders' => $folders
        ]);
    }

    /**
     * AJAX handler for setting attachment folders
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
        $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
        $folder_ids = isset($_POST['folder_ids']) ? array_map('intval', (array)$_POST['folder_ids']) : [];
        $mode = isset($_POST['mode']) ? sanitize_key($_POST['mode']) : 'set';
        
        if (!$attachment_id) {
            wp_send_json_error(['message' => 'Invalid attachment ID']);
        }
        
        $db = MFFB_DB::get_instance();
        $success = false;
        
        if ($mode === 'set') {
            // Set specific folders
            $success = $db->set_attachment_folders($attachment_id, $folder_ids);
        } elseif ($mode === 'add' && !empty($folder_ids)) {
            // Add to existing folders
            $current_folders = $db->get_attachment_folders($attachment_id);
            $updated_folders = array_unique(array_merge($current_folders, $folder_ids));
            $success = $db->set_attachment_folders($attachment_id, $updated_folders);
        } elseif ($mode === 'remove' && !empty($folder_ids)) {
            // Remove from specific folders
            $current_folders = $db->get_attachment_folders($attachment_id);
            $updated_folders = array_diff($current_folders, $folder_ids);
            $success = $db->set_attachment_folders($attachment_id, $updated_folders);
        }
        
        if ($success) {
            // Get updated folder names for response
            $updated_folders = $db->get_attachment_folders($attachment_id);
            $folder_names = $this->get_folder_names($updated_folders);
            
            $folders = [];
            foreach ($updated_folders as $key => $id) {
                $folders[] = [
                    'id' => $id,
                    'name' => isset($folder_names[$key]) ? $folder_names[$key] : 'Unknown'
                ];
            }
            
            wp_send_json_success([
                'message' => 'Folders updated successfully',
                'attachment_id' => $attachment_id,
                'folders' => $folders
            ]);
        } else {
            wp_send_json_error(['message' => 'Error updating folders']);
        }
    }

    /**
     * Modify folder count query to include attachments in multiple folders
     * 
     * @param array $where_query WHERE query parts
     * @return array Modified WHERE query parts
     */
    public function modify_folder_count_query($where_query) {
        // No need to modify this as FileBird already counts based on the fbv_attachment_folder table
        return $where_query;
    }

    /**
     * Modify all folders count
     * 
     * @param string $query SQL query
     * @param string $lang Language
     * @return string Modified SQL query
     */
    public function modify_all_folders_count($query, $lang) {
        // FileBird's query already counts based on records in fbv_attachment_folder
        // So we don't need to modify it for multiple folders
        return $query;
    }
}