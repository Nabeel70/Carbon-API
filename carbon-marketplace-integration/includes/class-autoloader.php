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
        // Handle both WordPress and standalone contexts
        if (function_exists('plugin_dir_path')) {
            self::$base_dir = plugin_dir_path(__FILE__);
        } else {
            self::$base_dir = dirname(__FILE__) . '/';
        }
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
        $file_path .= $class_name . '.php';
        
        // Convert class name to file name format (PascalCase to kebab-case)
        $file = self::convert_class_name_to_file_name($file_path);
        
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
        
        // Special handling for BaseModel -> abstract-base-model
        if ($filename === 'BaseModel') {
            return $directory . '/abstract-base-model.php';
        }
        
        // Special handling for ModelInterface -> interface-model
        if ($filename === 'ModelInterface') {
            return $directory . '/interface-model.php';
        }
        
        // Convert PascalCase to kebab-case and add class- prefix
        $kebab_case = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $filename));
        
        // Add class- prefix if not already present
        if (strpos($kebab_case, 'class-') !== 0) {
            $kebab_case = 'class-' . $kebab_case;
        }
        
        return $directory . '/' . $kebab_case . '.php';
    }
}