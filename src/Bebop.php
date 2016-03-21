<?php

namespace Ponticlaro;

use Ponticlaro\Bebop\Cms\Config;
use Ponticlaro\Bebop\Cms\PostType;
use Ponticlaro\Bebop\Common\Collection;
use Ponticlaro\Bebop\Common\ContextManager;
use Ponticlaro\Bebop\Common\EnvManager;
use Ponticlaro\Bebop\Common\FeatureManager;
use Ponticlaro\Bebop\Common\ObjectTracker;
use Ponticlaro\Bebop\Common\PathManager;
use Ponticlaro\Bebop\Common\UrlManager;
use Ponticlaro\Bebop\Common\Utils;
use Ponticlaro\Bebop\Core\Api;
use Ponticlaro\Bebop\Core\Helpers\BebopFactory;
use Ponticlaro\Bebop\Db;
use Ponticlaro\Bebop\Db\ObjectMeta;
use Ponticlaro\Bebop\Mvc\Models\Media;
use Ponticlaro\Bebop\Mvc\View;
use Ponticlaro\Bebop\ScriptsLoader\Css;
use Ponticlaro\Bebop\ScriptsLoader\Js;
use Ponticlaro\Bebop\UI;

class Bebop extends \Ponticlaro\Bebop\Common\Patterns\SingletonAbstract
{
    /**
     * Name of the constant that sets the development environment
     * 
     */
    const DEV_ENV_VAR_NAME = 'BEBOP_DEV_ENV_ENABLED';

    /**
     * The WordPress version
     * 
     * @var string
     */
    private static $__wp_version;

    /**
     * Contains flag for development environment
     * 
     * @var Bool
     */
    private static $__dev_env_enabled = false;

    /**
     * Checks environment and build defaults 
     * 
     */
    protected function __construct()
    {   
        // Instantiate Feature Manager
        FeatureManager::getInstance();

        // Instantiate Context Manager
        ContextManager::getInstance();

        // Instantiate URLs Manager
        UrlManager::getInstance();

        // Instantiate Paths Manager & set Bebop root directory
        PathManager::getInstance()->set('bebop', __DIR__);

        // Set default Media Mvc Model modifications on the Bebop HTTP API
        Media::onContext('api', function($media) {
            $media->cacheAllImageSizes();
        });

        // Set default views directory
        View::setViewsDir(PathManager::getInstance()->get('theme', 'views'));
 
        // Instantiate CSS Manager
        CSS::getInstance();

        // Instantiate JS Manager
        JS::getInstance();

        // Instantiate UI
        UI::getInstance();
        
        // Instantiate Api
        self::Api();

        // Instantiate Config
        Config::getInstance();

        // Shortcode support for in editor use 
        add_shortcode('Bebop', array($this, '__bebopShortcodes'));
    }

    /**
     * Creates Bebop instance to check environment and build defaults 
     * 
     * @return \Ponticlaro\Bebop
     */
    public static function boot()
    {
        return self::getInstance();
    }

    /**
     * Sets development environment status
     * 
     * @param boolean $enabled True to enable, false otherwise
     */
    public static function setDevEnv($enabled) 
    {
        define(static::DEV_ENV_VAR_NAME, $enabled);
    }

    /**
     * Used to check if development environment is enabled
     * 
     * @return boolean True for enabled, false otherwise
     */
    public static function isDevEnvEnabled()
    {
        return defined(static::DEV_ENV_VAR_NAME) && static::DEV_ENV_VAR_NAME;
    }

    /**
     * Returns WordPress version
     * 
     * @return string
     */
    public static function getVersion() 
    {
        global $wp_version;

        return self::$wp_version;
    }

    /**
     * Returns feature manager OR optionaly a feature configuration object
     * 
     * @param string $id Id of the target feature
     */
    public static function Feature($id = null)
    {
        $feature_manager = FeatureManager::getInstance();

        return $id && is_string($id) ? $feature_manager->get($id) : $feature_manager;
    }

    /**
     * Returns the config manager
     * 
     */
    public static function Config()
    {
        return Config::getInstance();
    }

    /**
     * Returns the context manager
     * 
     */
    public static function Context()
    {
        return ContextManager::getInstance();
    }

    /**
     * Returns the Env class instance or 
     * the target environment by using its key
     * 
     */
    public static function Env($key = null)
    {
        $env_manager = EnvManager::getInstance();

        return is_string($key) && $env_manager->exists($key) ? $env_manager->get($key) : $env_manager;
    }

    /**
     * Returns Db class instance
     * 
     * @param  PDO                     $pdo  Optional PDO instance
     * @return Ponticlaro\Bebop\Db\Db
     */
    public static function Db(\PDO $pdo = null)
    {
        $db = Db::getInstance();

        if ($pdo)
            $db->setConnection($pdo);

        return $db;
    }

    /**
     * Returns an Mvc\View object with an already defined template
     * 
     * @param string $template Path of the template relative to the views directory, without file extension
     */
    public static function View($template)
    {
        return (new View)->setTemplate($template);
    } 

    /**
     * Returns the UrlManager instance
     */
    public static function Urls()
    {
        return UrlManager::getInstance();
    }

    /**
     * Returns the PathManager instance
     */
    public static function Paths()
    {
        return PathManager::getInstance();
    }

    /**
     * Returns the Scripts manager instance
     * or the target registration hook
     */
    public static function CSS($hook_id = null)
    {
        $scripts = Css::getInstance();

        return $hook_id ? $scripts->getHook($hook_id) : $scripts;
    }

    /**
     * Returns the Scripts manager instance
     * or the target registration hook
     */
    public static function JS($hook_id = null)
    {
        $scripts = Js::getInstance();

        return $hook_id ? $scripts->getHook($hook_id) : $scripts;
    }

    /**
     * Used to instantiate an object to easily handle user meta data
     * 
     * @param  int                             $id      ID of the target user
     * @param  array                           $options ObjectMeta options list
     * @return \Ponticlaro\Bebop\Db\ObjectMeta
     */
    public static function UserMeta($id, array $options = array())
    {   
        return new ObjectMeta('user', $id, $options);
    }

    /**
     * Used to instantiate an object to easily handle post meta data
     * 
     * @param  int                             $id      ID of the target post
     * @param  array                           $options ObjectMeta options list
     * @return \Ponticlaro\Bebop\Db\ObjectMeta
     */
    public static function PostMeta($id, array $options = array())
    {   
        return new ObjectMeta('post', $id, $options);
    }

    /**
     * Used to instantiate an object to easily handle comment meta data
     * 
     * @param  int                             $id      ID of the target comment
     * @param  array                           $options ObjectMeta options list
     * @return \Ponticlaro\Bebop\Db\ObjectMeta
     */
    public static function CommentMeta($id, array $options = array())
    {   
        return new ObjectMeta('comment', $id, $options);
    }

    /**
     * Returns the UI class instance
     */
    public static function UI()
    {
        return UI::getInstance();
    }

    /**
     * Returns the Api class instance
     */
    public static function Api()
    {
        return Api::getInstance();
    }

    /**
     * Returns the object tracker class instance
     */
    public static function ObjectTracker()
    {
        return ObjectTracker::getInstance();
    }

    /**
     * Tracks objects created by Bebop
     * 
     * @param  mixed $object Object to be tracked
     * @return void
     */
    public static function track($object)
    {
        ObjectTracker::getInstance()->track($object);
    }

    /**
     * Used to get a previously instantiated trackable object 
     * 
     * @param  string $type Type of the target object
     * @param  string $id   ID of the target object
     * @return object       Target bject
     */
    public static function getTrackedObject($type, $id)
    {
        return ObjectTracker::getInstance()->get($type, $id);
    }

    /**
     * Creates Bebop shortcode for usage inside content editor
     * 
     * @param  array   $attrs    Shortcode attributes
     * @param  string  $content  Shortcode content
     * @return void
     */
    public function __bebopShortcodes($attrs, $content = null) 
    {
        if ($attrs) {

            if (array_key_exists('url', $attrs)) 
                return UrlManager::getInstance()->get($attrs['url']);

            if (array_key_exists('path', $attrs)) 
                return PathManager::getInstance()->get($attrs['path']);
        }
    }

    /**
     * Calls methods from the Ponticlaro\Bebop\Utils class
     * 
     * @return mixed
     */
    public static function util()
    {   
        // Get function arguments
        $args = func_get_args();

        // Get utility method name
        $name = isset($args[0]) && is_string($args[0]) ? $args[0] : null;

        // Unset first argument
        unset($args[0]);

        // Throw error if method to not exit
        if(!$name || !method_exists('Ponticlaro\Bebop\Common\Utils', $name)) 
            throw new \Exception("You need to select an existing utility name");

        // Call utility and return result
        return call_user_func_array(array('Ponticlaro\Bebop\Common\Utils', $name), $args);
    }


    /**
     * Includes a path from the path collection
     * 
     * @param  string  $path_key Path key string
     * @param  boolean $once     True if it should only be included once
     * @return void          
     */
    public static function inc($path_key, $once = false)
    {
        // Include file
        self::__includeFile($path_key, $once);
    }

    /**
     * Requires a path from the paths collection
     * 
     * @param  string  $path_key Path key string
     * @param  boolean $once     True if it should only be required once
     * @return void
     */
    public static function req($path_key, $once = false)
    {
        // Require file
        self::__includeFile($path_key, $once, true);
    }

    /**
     * Echoes single URL
     * with an optionally suffixed realtive URL
     * 
     * @param  string $key          Key for the target URL
     * @param  string $relative_url Optional relative URL
     * @return void
     */
    public static function Url($key, $relative_url = null)
    {
        echo self::Urls()->get($key, $relative_url);    
    }

    /**
     * Sets a single URL
     * 
     * @param string $key   URL key
     * @param string $value URL
     */
    public static function setUrl($key, $value = null)
    {
        self::Urls()->set($key, $value);  
    }

    /**
     * Sets several URLs using an associative array
     * 
     * @param array $urls List of URLs
     */
    public static function setUrls(array $urls = array())
    {
        self::Urls()->set($key);
    }

    /**
     * Returns a single URL using a key
     * with an optionally suffixed realtive URL
     * 
     * @param  string $key          Key for the target URL
     * @param  string $relative_url Optional relative URL
     * @return string               URL
     */
    public static function getUrl($key, $relative_url = null)
    {
        return self::Urls()->get($key, $relative_url);
    }

    /**
     * Returns all URLs
     * 
     */
    public static function getUrls()
    {
        return self::Urls()->getAll();
    }

    /**
     * Remove single URL using its key
     * 
     * @param string $key Key for target URL
     */
    public static function removeUrl($key)
    {
        self::Urls()->remove($key);
    }

    /**
     * Echoes single path
     * with an optionally suffixed realtive path
     * 
     * @param  string $key           Key for target path
     * @param  string $relative_path Optional relative path
     * @return void
     */
    public static function Path($key, $relative_path = null)
    {
        echo self::Paths()->get($key, $relative_path);  
    }

    /**
     * Sets a single Path
     * 
     * @param string $key   Path key
     * @param string $value Path
     */
    public static function setPath($key, $value = null)
    {
        self::Paths()->set($key, $value);
    }

    /**
     * Sets several paths using an associative array
     * 
     * @param array $paths List of Paths
     */
    public static function setPaths(array $paths = array())
    {
        self::Paths()->set($paths);
    }

    /**
     * Gets single path
     * with an optionally suffixed realtive path
     * 
     * @param  string $key           Key for target path
     * @param  string $relative_path Optional relative path
     * @return string                Path
     */
    public static function getPath($key, $relative_path = null)
    {
        return self::Paths()->get($key, $relative_path);
    }

    /**
     * Returns all paths
     * 
     */
    public static function getPaths()
    {
        return self::Paths()->getAll();
    }

    /**
     * Remove single path using its key
     * 
     * @param string $key Key for target path
     */
    public static function removePath($key)
    {
        self::Paths()->remove($key);
    }

    /**
     * Used to manage include/require functions
     * 
     * @param  string  $path Path of the file to include
     * @param  boolean $once True to include/require only once
     * @param  string  $fn   True to use 'require', false to use 'include'
     * @return void
     */
    protected static function __includeFile($path_key, $once = false, $require = false)
    {   
        // Return if path key is not a string
        if (!is_string($path_key)) return;

        // Check for path in path collection
        $path = self::Paths()->get($path_key);

        // Return if $path is not a string or not readable
        if (!$path || !is_string($path) || !is_readable($path)) return;

        // Require file
        if ($require) {
            
            $once ? require_once $path : require $path;
        }

        // Include file
        else {

            $once ? include_once $path : include $path;
        }
    } 

    /**
     * Handles any call to undefined static methods
     * 
     * @param  string $name Name of the target method
     * @param  array  $args Arguments for the target method
     * @return mixed      
     */
    public static function __callStatic($name, $args) 
    {
        //////////////////////////////
        // Check for correct method //
        //////////////////////////////

        // Check for get
        $is_get = substr($name, 0, 3) == 'get' ? true : false;

        /////////
        // Get //
        /////////
        if ($is_get) {

            $get_action     = substr($name, 3, strlen($name));
    
            $is_getPostType = $get_action == 'PostType' ? true : false;
            $is_getTaxonomy = $get_action == 'Taxonomy' ? true : false;
            $is_getMetabox  = $get_action == 'Metabox' ? true : false;
            
            if ($is_getPostType || $is_getTaxonomy || $is_getMetabox) {

                if (!isset($args[0])) return;

                if (is_string($args[0])) {

                    $key = Utils::slugify($args[0]);
                    
                    if ($is_getPostType) $type = 'post_type';
                    if ($is_getTaxonomy) $type = 'taxonomy';
                    if ($is_getMetabox) $type = 'metabox';

                    return $type ? ObjectTracker::get($type, $key) : null;
                }
            }
        }

        //////////////////////////
        // Try to create object //
        //////////////////////////
        else {

            return call_user_func_array(array('Ponticlaro\Bebop\Core\Helpers\BebopFactory', 'create'), array($name, $args));
        }
    }
}