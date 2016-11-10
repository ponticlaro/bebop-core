<?php

namespace Ponticlaro\Bebop\Core;

use Ponticlaro\Bebop\Common\ContextManager;
use Ponticlaro\Bebop\Common\Utils;
use Ponticlaro\Bebop\Core\Exceptions\ApiException;
use Ponticlaro\Bebop\Db;
use Ponticlaro\Bebop\Db\ObjectMeta;
use Ponticlaro\Bebop\Db\SqlProjection;
use Ponticlaro\Bebop\HttpApi;
use Ponticlaro\Bebop\Mvc\Helpers\ModelFactory;

class Api extends \Ponticlaro\Bebop\Common\Patterns\SingletonAbstract {

    /**
     * Api Instance
     * 
     * @var Ponticlaro\Bebop\Core\Api
     */
    protected $api;

    /**
     * Projection for post meta columns
     * 
     * @var \Ponticlaro\Bebop\Db\SqlProjection
     */
    protected $postmeta_projection;

    /**
     * Instantiates the Bebop Api
     * 
     */
    protected function __construct()
    {
        // Instantiate new Api
        $this->api = new HttpApi('bebop:api', '_bebop/api/');

        // Set post meta projection
        $postmeta_projection = new SqlProjection();
        $postmeta_projection->addColumn('meta_id', '__id')
                            ->addColumn('post_id', '__post_id')
                            ->addColumn('meta_key', '__key')
                            ->addColumn('meta_value', 'value')
                            ->setClass('Ponticlaro\Bebop\Resources\Models\ObjectMeta');

        $this->postmeta_projection = $postmeta_projection;

        // Set default routes ONLY after registering custom post types 
        add_action('init', array($this, 'setDefaultRoutes'), 2);
    }

   /**
     * Sets default Api routes
     *
     * @return void
     */
    public function setDefaultRoutes()
    {
        // Hello World route
        $this->api->get('/', function() {
            
            return array('Hello World');
        });

        // Get all registered post types 
        $post_types = get_post_types(array(), 'objects');

        //////////////////////////
        // Add /search endpoint //
        //////////////////////////
        $this->api->get("search(/)(:keywords)", function($keywords = null) {

            // Override context
            ContextManager::getInstance()->overrideCurrent('api/search');

            // Get all registered post types 
            $post_types = get_post_types(array(), 'objects');

            // Default query args
            $default_query = [
                'type' => []
            ];

            // Add all publicly queryable types to search results
            if ($post_types) {
                foreach ($post_types as $type) {
                
                    if ($type->name == 'page' || $type->publicly_queryable)
                        $default_query['type'][] = $type->name;
                }
            }

            // Enable developers to modify $default_query for this resource
            $default_query = apply_filters("bebop:api:search:default_query", $default_query);

            // Capture 'post_type' argument
            if (isset($_GET['post_type'])) {

                $_GET['type'] = $_GET['post_type'];
                unset($_GET['post_type']);
            }

            // Merge default query args with user query args
            $query = array_merge($default_query, $_GET);

            // Add keywords to query
            if ($keywords)
                $query['s'] = $keywords;

            // Enable developers to modify final query for this resource
            $query = apply_filters("bebop:api:search:query", $query);

            // Get results
            $response = Db::wpQuery($query)->setOption('with_meta', true)->execute();

            // Apply model modifications for each post type
            if ($response['items']) {
                foreach ($response['items'] as $index => $post) {
                    
                    if (ModelFactory::canManufacture($post->post_type))
                        $response['items'][$index] = ModelFactory::create($post->post_type, array($post));
                }
            }

            // Enable developers to modify response for this resource
            $response = apply_filters("bebop:api:search:response", $response);

            // Return response
            return $response;
        });

        /////////////////////////////////////////////////
        // Add endpoints for all available posts types //
        /////////////////////////////////////////////////
        foreach ($post_types as $slug => $post_type) {

            if ($post_type->public) {

                $resource_name = Utils::slugify($post_type->labels->name);

                // Add post resource
                $this->api->get("$resource_name(/)(:id)", function($id = null) use($post_type, $resource_name) {

                    if (is_numeric($id)) {

                        // Override context
                        ContextManager::getInstance()->overrideCurrent('api/single/'. $resource_name);

                        $post = get_post($id);

                        if ($post instanceof \WP_Post) {

                            if (ModelFactory::canManufacture($post->post_type)) {
                                
                                $post = ModelFactory::create($post->post_type, array($post));
                            }

                            $response = $post;
                        }

                    } else {

                        // Override context
                        ContextManager::getInstance()->overrideCurrent('api/archive/'. $resource_name);

                        if (isset($_GET['type'])) 
                            unset($_GET['type']);

                        if ($resource_name == 'media') {

                            if (isset($_GET['status'])) 
                                unset($_GET['status']);

                            $_GET['post_type']   = 'attachment';
                            $_GET['post_status'] = 'inherit';

                        } else {

                            $_GET['post_type'] = $post_type->name;
                        }

                        $response = Db::wpQuery($_GET)->setOption('with_meta', true)->execute();

                        if ($response['items']) {

                            foreach ($response['items'] as $index => $post) {
                                
                                if (ModelFactory::canManufacture($post->post_type)) {

                                    $response['items'][$index] = ModelFactory::create($post->post_type, array($post));
                                }
                            }
                        }
                    }

                    // Enable developers to modify response for target resource
                    $response = apply_filters("bebop:api:$resource_name:response", $response);

                    // Return response
                    return $response;
                });
                
                /////////////////////////////////////
                // Get all or individual post meta //
                /////////////////////////////////////
                $this->api->get("$resource_name/:post_id/meta/:meta_key(/)(:meta_id)", function($post_id, $meta_key, $meta_id = null) use($post_type, $resource_name) {

                    // Throw error if post do not exist
                    if (!get_post($post_id) instanceof \WP_Post)
                        throw new ApiException("Target entry do not exist", 404);

                    // Get meta data
                    $post_meta = new ObjectMeta('post', $post_id, array(
                        'projection' => $this->postmeta_projection
                    ));

                    $response  = $meta_id ? $post_meta->get($meta_key, $meta_id) : $post_meta->getAll($meta_key);

                    // Enable developers to modify response
                    $response = apply_filters("bebop:api:postmeta:$meta_key:response", $response, $post_id);

                    // Enable developers to modify response
                    $response = apply_filters('bebop:api:postmeta:response', $response, $meta_key, $post_id);

                    // Return response
                    return $response;
                });

                /////////////////////////////
                // Create single post meta //
                /////////////////////////////
                $this->api->post("$resource_name/:post_id/meta/:meta_key(/)", function($post_id, $meta_key) {

                    // Check if current user can edit the target post
                    if (!current_user_can('edit_post', $post_id))
                        throw new ApiException("You cannot edit the target entry", 403);
                        
                    // Get request body
                    $data = json_decode($this->api->router()->request()->getBody(), true);

                    // Throw error if payload is null
                    if (is_null($data))
                        throw new ApiException("You cannot send an empty request body", 400);

                    // Defined storage method
                    $storage_method = isset($_GET['storage_method']) ? $_GET['storage_method'] : 'json';

                    // Check storage type
                    if (!in_array($storage_method, array('json', 'serialize')))
                        throw new ApiException("Storage method needs to be either 'json' or 'serialize'", 400);

                    // Throw error if post do not exist
                    if (!get_post($post_id) instanceof \WP_Post)
                        throw new ApiException("Target entry do not exist", 404);

                    // Instantiate PostMeta object
                    $post_meta = new ObjectMeta('post', $post_id, array(
                        'projection' => $this->postmeta_projection
                    ));

                    // Add new meta row
                    $new_item = $post_meta->add($meta_key, $data, $storage_method);

                    // Throw error if it was not able to create new postmeta item
                    if (!$new_item)
                        throw new ApiException("Failed to create new postmeta item", 500);

                    // Return response
                    return $new_item;
                });
                
                /////////////////////////////
                // Update single post meta //
                /////////////////////////////
                $this->api->put("$resource_name/:post_id/meta/:meta_key/:meta_id(/)", function($post_id, $meta_key, $meta_id) {

                    // Check if current user can edit the target post
                    if (!current_user_can('edit_post', $post_id))
                        throw new ApiException("You cannot edit the target entry", 403);

                    // Get request body
                    $data = json_decode($this->api->router()->request()->getBody(), true);

                    // Throw error if payload is null
                    if (is_null($data))
                        throw new ApiException("You cannot send an empty request body", 400);

                    // Defined storage method
                    $storage_method = isset($_GET['storage_method']) ? $_GET['storage_method'] : 'json';

                    // Check storage type
                    if (!in_array($storage_method, array('json', 'serialize')))
                        throw new ApiException("Storage method needs to be either 'json' or 'serialize'", 400);

                    // Throw error if post do not exist
                    if (!get_post($post_id) instanceof \WP_Post)
                        throw new ApiException("Target entry do not exist", 404);

                    // Instantiate PostMeta object
                    $post_meta = new ObjectMeta('post', $post_id, array(
                        'projection' => $this->postmeta_projection
                    ));

                    // Update Meta
                    $updated_item = $post_meta->update($meta_key, $meta_id, $data, $storage_method);

                    // Throw error if it was not able to update the target postmeta item
                    if (!$updated_item)
                        throw new ApiException("Failed to update postmeta item", 500);

                    // Return updated item
                    return $updated_item;
                });

                /////////////////////////////
                // Delete single post meta //
                /////////////////////////////
                $this->api->delete("$resource_name/:post_id/meta/:meta_key/:meta_id(/)", function($post_id, $meta_key, $meta_id) use($post_type, $resource_name) {

                    // Check if current user can edit the target post
                    if (!current_user_can('edit_post', $post_id))
                        throw new ApiException("You cannot edit the target entry", 403);

                    // Throw error if post do not exist
                    if (!get_post($post_id) instanceof \WP_Post)
                        throw new ApiException("Target entry do not exist", 404);

                    // Instantiate PostMeta object
                    $post_meta = new ObjectMeta('post', $post_id, array(
                        'projection' => $this->postmeta_projection
                    ));

                    // Delete post meta
                    $remaining_items = $post_meta->delete($meta_key, $meta_id);

                    // Return remaining items
                    return $remaining_items;
                });

            }
        }

        // Add endpoint to inform about available endpoints
        $this->api->get("_resources(/)", function() use($post_types) {

            if (!current_user_can('manage_options')) {
        
                $this->api->router()->slim()->halt(403, json_encode(array(
                    'error' => array(
                        'status'  => 403,
                        'message' => "You're not an authorized user."
                    )
                )));

                exit;
            }

            $base_url = '/'. $this->api->getBaseUrl();

            // Loop through all defined routes
            foreach ($this->api->routes()->getAll() as $route) {

                $resources[] = array(
                    'method'   => strtoupper($route->getMethod()),
                    'endpoint' => $base_url . ltrim($route->getPath(), '/')
                );
            }

            // Return resources
            return $resources;
        });

        return $this;
    }

    /**
     * Calls the Api instance on undefined methods
     * 
     * @param  string $name Method name
     * @param  array  $args Method arguments
     * @return mixed 
     */
    public function __call($name, $args)
    {
        if (method_exists($this->api, $name))
            return call_user_func_array(array($this->api, $name), $args);

        throw new \Exception("Bebop\Api method '$name' do not exist");
    }
}