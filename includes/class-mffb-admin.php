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
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Add admin notices
        add_action('admin_notices', [$this, 'admin_notices']);
        
        // Add settings link to plugins page
        add_filter('plugin_action_links_' . MFFB_PLUGIN_BASENAME, [$this, 'plugin_action_links']);
    }

    /**
     * Display admin notices
     */
    public function admin_notices() {
        // Check if FileBird Pro is active
        if (!class_exists('FileBird\\Plugin')) {
            ?>
            <div class="notice notice-error">
                <p><?php _e('Multiple Folders for FileBird requires FileBird Pro to be installed and activated.', 'multiple-folders-for-filebird'); ?></p>
            </div>
            <?php
        }
    }

    /**
     * Add settings link to plugins page
     * 
     * @param array $links Existing links
     * @return array Modified links
     */
    public function plugin_action_links($links) {
        // Add a link to the media library
        $media_link = '<a href="' . admin_url('upload.php') . '">' . __('Media Library', 'multiple-folders-for-filebird') . '</a>';
        array_unshift($links, $media_link);
        
        return $links;
    }
}