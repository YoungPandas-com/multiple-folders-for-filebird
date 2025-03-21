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
        // Intercept FileBird's folder assignment functions
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Intercept FileBird's folder assignment action
        add_action('fbv_after_set_folder', [$this, 'handle_filebird_folder_assignment'], 10, 2);
        
        // Filter to modify the "assigned to folder" logic
        add_filter('fbv_folder_from_post_id', [$this, 'modify_folder_from_post_id'], 10, 1);
        
        // Filter to modify folder queries to include files in multiple folders
        add_filter('fbv_get_count_where_query', [$this, 'modify_folder_count_query'], 10, 1);
        add_filter('fbv_all_folders_and_count', [$this, 'modify_all_folders_count'], 10, 2);
        
        // Filter for the display of folders in the attachment details
        add_filter('attachment_fields_to_edit', [$this, 'modify_attachment_fields'], 10, 2);
        
        // Modify FileBird's relationship query
        add_filter('posts_clauses', [$this, 'modify_posts_clauses'], 20, 2);
    }

    /**
     * Handle assignment of folders by FileBird
     * This hook gives us the opportunity to track the assignment in our system
     * 
     * @param int $post_id Post ID
     * @param int $folder_id Folder ID
     */
    public function handle_filebird_folder_assignment($post_id, $folder_id) {
        // If this is a singular assignment, we store it in our secondary data
        // Note: FileBird already handles the main folder assignment
        // We don't do anything here because we'll store all folders on save
    }

    /**
     * Modify how folders are retrieved for a post
     * 
     * @param array $folders Folder data from FileBird
     * @return array Modified folder data
     */
    public function modify_folder_from_post_id($folders) {
        // For now we'll let FileBird's original function handle this
        // We'll add UI to display all folders in the attachment edit screen
        return $folders;
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

    /**
     * Get all folder IDs an attachment is assigned to
     * 
     * @param int $attachment_id Attachment ID
     * @return array Array of folder IDs
     */
    public function get_attachment_folders($attachment_id) {
        $db = MFFB_DB::get_instance();
        return $db->get_attachment_folders($attachment_id);
    }

    /**
     * Set the folders for an attachment
     * 
     * @param int $attachment_id Attachment ID
     * @param array $folder_ids Array of folder IDs
     * @return bool Success or failure
     */
    public function set_attachment_folders($attachment_id, $folder_ids) {
        $db = MFFB_DB::get_instance();
        return $db->set_attachment_folders($attachment_id, $folder_ids);
    }

    /**
     * Modify the posts clauses to handle displaying files in all assigned folders
     * 
     * @param array $clauses Query clauses
     * @param WP_Query $query Query object
     * @return array Modified query clauses
     */
    public function modify_posts_clauses($clauses, $query) {
        global $wpdb;
        
        // Only modify queries that are filtering by FileBird folder
        if ($query->get('post_type') !== 'attachment' || !isset($query->query_vars['fbv'])) {
            return $clauses;
        }
        
        $folder_id = $query->query_vars['fbv'];
        
        // No need to modify if showing all files or uncategorized
        if ($folder_id == -1 || $folder_id == 0) {
            return $clauses;
        }
        
        // The query already filters by folder ID using the fbv_attachment_folder table
        // So it already supports showing files in multiple folders
        // We don't need to modify the query
        
        return $clauses;
    }

    /**
     * Modify the attachment fields to add custom folder selection
     * 
     * @param array $form_fields Form fields array
     * @param WP_Post $post Post object
     * @return array Modified form fields
     */
    public function modify_attachment_fields($form_fields, $post) {
        // We'll implement this in the Admin class to keep UI separate from core functionality
        return $form_fields;
    }
}