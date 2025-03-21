<?php
/**
 * Database operations for Multiple Folders for FileBird
 *
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class MFFB_DB {
    
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
        // Check if we need to update database
        add_action('plugins_loaded', [$this, 'check_update'], 10);
    }

    /**
     * Check if the database needs to be updated
     */
    public function check_update() {
        $current_version = get_option('mffb_db_version', '0.0.0');
        
        if (version_compare($current_version, MFFB_VERSION, '<')) {
            $this->create_tables();
            update_option('mffb_db_version', MFFB_VERSION);
        }
    }

    /**
     * Create database tables
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // We'll use FileBird's existing table structure and handle multiple folders in our code
        // No need to create a new table, as we'll leverage the existing fbv_attachment_folder table
    }

    /**
     * Get all folders for an attachment
     * 
     * @param int $attachment_id Attachment ID
     * @return array Array of folder IDs
     */
    public function get_attachment_folders($attachment_id) {
        global $wpdb;
        
        $folder_table = $wpdb->prefix . 'fbv_attachment_folder';
        
        $folders = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT folder_id FROM $folder_table WHERE attachment_id = %d",
                $attachment_id
            )
        );
        
        return array_map('intval', $folders);
    }

    /**
     * Assign an attachment to multiple folders
     * 
     * @param int $attachment_id Attachment ID
     * @param array $folder_ids Array of folder IDs
     * @return bool Success or failure
     */
    public function set_attachment_folders($attachment_id, $folder_ids) {
        global $wpdb;
        
        $folder_table = $wpdb->prefix . 'fbv_attachment_folder';
        
        // Begin transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // First, remove the attachment from all existing folders
            $wpdb->delete(
                $folder_table,
                ['attachment_id' => $attachment_id],
                ['%d']
            );
            
            // Then, add the attachment to all the selected folders
            foreach ($folder_ids as $folder_id) {
                $folder_id = intval($folder_id);
                if ($folder_id > 0) {
                    $wpdb->insert(
                        $folder_table,
                        [
                            'folder_id' => $folder_id,
                            'attachment_id' => $attachment_id
                        ],
                        ['%d', '%d']
                    );
                }
            }
            
            // Commit transaction
            $wpdb->query('COMMIT');
            
            // Trigger action for other plugins
            do_action('mffb_after_set_attachment_folders', $attachment_id, $folder_ids);
            
            return true;
        } catch (Exception $e) {
            // Rollback transaction on error
            $wpdb->query('ROLLBACK');
            
            error_log('Multiple Folders for FileBird: Error setting attachment folders - ' . $e->getMessage());
            
            return false;
        }
    }

    /**
     * Add an attachment to a folder without removing from existing folders
     * 
     * @param int $attachment_id Attachment ID
     * @param int $folder_id Folder ID
     * @return bool Success or failure
     */
    public function add_attachment_to_folder($attachment_id, $folder_id) {
        global $wpdb;
        
        $folder_table = $wpdb->prefix . 'fbv_attachment_folder';
        
        // Check if the attachment is already in the folder
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $folder_table WHERE attachment_id = %d AND folder_id = %d",
                $attachment_id,
                $folder_id
            )
        );
        
        if ($exists) {
            return true; // Already in folder, nothing to do
        }
        
        // Add the attachment to the folder
        $result = $wpdb->insert(
            $folder_table,
            [
                'folder_id' => $folder_id,
                'attachment_id' => $attachment_id
            ],
            ['%d', '%d']
        );
        
        if ($result) {
            do_action('mffb_after_add_attachment_to_folder', $attachment_id, $folder_id);
            return true;
        }
        
        return false;
    }

    /**
     * Remove an attachment from a specific folder
     * 
     * @param int $attachment_id Attachment ID
     * @param int $folder_id Folder ID
     * @return bool Success or failure
     */
    public function remove_attachment_from_folder($attachment_id, $folder_id) {
        global $wpdb;
        
        $folder_table = $wpdb->prefix . 'fbv_attachment_folder';
        
        $result = $wpdb->delete(
            $folder_table,
            [
                'attachment_id' => $attachment_id,
                'folder_id' => $folder_id
            ],
            ['%d', '%d']
        );
        
        if ($result) {
            do_action('mffb_after_remove_attachment_from_folder', $attachment_id, $folder_id);
            return true;
        }
        
        return false;
    }
}