<?php
/**
 * Plugin Name: Multiple Folders for FileBird
 * Plugin URI: https://yp.studio
 * Description: Extends FileBird Pro to allow assigning media files to multiple folders.
 * Version: 1.0.0
 * Author: Young Pandas
 * Author URI: https://yp.studio
 * Text Domain: multiple-folders-for-filebird
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 5.6
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MFFB_VERSION', '1.0.0');
define('MFFB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MFFB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MFFB_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Check if FileBird is active
register_activation_hook(__FILE__, 'mffb_check_filebird_activation');
function mffb_check_filebird_activation() {
    if (!class_exists('FileBird\\Plugin')) {
        deactivate_plugins(MFFB_PLUGIN_BASENAME);
        wp_die('Multiple Folders for FileBird requires FileBird Pro to be installed and activated.');
    }
}

// Main plugin class
class Multiple_Folders_For_FileBird {
    
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
        // Check if FileBird is active
        if (!class_exists('FileBird\\Plugin')) {
            add_action('admin_notices', [$this, 'filebird_missing_notice']);
            return;
        }

        // Include required files
        $this->includes();

        // Initialize hooks
        $this->init_hooks();

        // Initialize the plugin components
        add_action('plugins_loaded', [$this, 'init_plugin']);
    }

    /**
     * Include required files
     */
    private function includes() {
        // Admin
        require_once MFFB_PLUGIN_DIR . 'includes/class-mffb-admin.php';
        
        // Core
        require_once MFFB_PLUGIN_DIR . 'includes/class-mffb-core.php';
        
        // Database
        require_once MFFB_PLUGIN_DIR . 'includes/class-mffb-db.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register activation and deactivation hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }

    /**
     * Initialize the plugin components
     */
    public function init_plugin() {
        // Initialize database
        MFFB_DB::get_instance();
        
        // Initialize core functionality
        MFFB_Core::get_instance();
        
        // Initialize admin
        if (is_admin()) {
            MFFB_Admin::get_instance();
        }
        
        // Load text domain
        $this->load_textdomain();
    }

    /**
     * Activate the plugin
     */
    public function activate() {
        // Create database tables
        MFFB_DB::get_instance()->create_tables();
        
        // Clear transients
        delete_transient('mffb_upgrading');
        
        // Set version
        update_option('mffb_version', MFFB_VERSION);
    }

    /**
     * Deactivate the plugin
     */
    public function deactivate() {
        // Clear any scheduled hooks, transients, etc.
        delete_transient('mffb_upgrading');
    }

    /**
     * Load plugin text domain
     */
    public function load_textdomain() {
        load_plugin_textdomain('multiple-folders-for-filebird', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    /**
     * Admin notice for when FileBird is not active
     */
    public function filebird_missing_notice() {
        ?>
        <div class="error">
            <p><?php _e('Multiple Folders for FileBird requires FileBird Pro to be installed and activated.', 'multiple-folders-for-filebird'); ?></p>
        </div>
        <?php
    }
}

// Initialize the plugin
function mffb_init() {
    return Multiple_Folders_For_FileBird::get_instance();
}

// Start the plugin
mffb_init();