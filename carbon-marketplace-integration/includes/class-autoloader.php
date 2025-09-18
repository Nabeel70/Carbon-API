<?php
/**
 * Autoloader for Carbon Marketplace Integration
 *
 * @package CarbonMarketplace
 */

namespace CarbonMarketplace;

/**
 * PSR-4 Autoloader for the Carbon Marketplace plugin
 */
class Autoloader {
    
    /**
     * The namespace prefix for this plugin
     */
    const NAMESPACE_PREFIX = 'CarbonMarketplace\\';
    
    /**
     * The base directory for the namespace prefix
     */
    private static $base_dir;
    
    /**
     * Initialize the autoloader
     */
    public static function init() {
        self::$base_dir = plugin_dir_path(__FILE__);
        spl_autoload_register(array(__CLASS__, 'load_class'));
    }
    
    /**
     * Load a class file
     *
     * @param string $class The fully-qualified class name
     * @return mixed The mapped file name on success, or boolean false on failure
     */
    public static function load_class($class) {
        // Does the class use the namespace prefix?
        $len = strlen(self::NAMESPACE_PREFIX);
        if (strncmp(self::NAMESPACE_PREFIX, $class, $len) !== 0) {
            // No, move to the next registered autoloader
            return;
        }
        
        // Get the relative class name
        $relative_class = substr($class, $len);
        
        // Convert namespace separators to directory separators and make directories lowercase
        $path_parts = explode('\\', $relative_class);
        $class_name = array_pop($path_parts); // Get the class name
        $directories = array_map('strtolower', $path_parts); // Convert directories to lowercase
        
        // Build the file path
        $file_path = self::$base_dir;
        if (!empty($directories)) {
            $file_path .= implode('/', $directories) . '/';
        }
        
        // Convert class name to file name format (PascalCase to kebab-case with class- prefix)
        $kebab_case = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $class_name));
        $file_name = 'class-' . $kebab_case . '.php';
        $file_path .= $file_name;
        
        // If the file exists, require it
        if (file_exists($file_path)) {
            require $file_path;
        }
    }
}