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
        
        // Replace the namespace prefix with the base directory, replace namespace
        // separators with directory separators in the relative class name, append
        // with .php
        $file = self::$base_dir . str_replace('\\', '/', $relative_class) . '.php';
        
        // Convert class name to file name format (PascalCase to kebab-case)
        $file = self::convert_class_name_to_file_name($file);
        
        // If the file exists, require it
        if (file_exists($file)) {
            require $file;
        }
    }
    
    /**
     * Convert PascalCase class names to kebab-case file names
     *
     * @param string $file_path The file path with PascalCase class name
     * @return string The file path with kebab-case file name
     */
    private static function convert_class_name_to_file_name($file_path) {
        $path_parts = pathinfo($file_path);
        $directory = $path_parts['dirname'];
        $filename = $path_parts['filename'];
        
        // Convert PascalCase to kebab-case and add class- prefix
        $kebab_case = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $filename));
        
        // Add class- prefix if not already present
        if (strpos($kebab_case, 'class-') !== 0) {
            $kebab_case = 'class-' . $kebab_case;
        }
        
        return $directory . '/' . $kebab_case . '.php';
    }
}