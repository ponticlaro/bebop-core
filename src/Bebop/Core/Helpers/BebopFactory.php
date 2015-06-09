<?php

namespace Ponticlaro\Bebop\Core\Helpers;

use Ponticlaro\Bebop\Common\ObjectTracker;
use Ponticlaro\Bebop\Common\Patterns\FactoryAbstract;
use Ponticlaro\Bebop\Common\Patterns\TrackableObjectAbstract;

class BebopFactory extends FactoryAbstract {

	/**
	 * List of manufacturable classes
	 * 
	 * @var array
	 */
	protected static $manufacturable = array(
		'AdminPage'  => 'Ponticlaro\Bebop\Cms\AdminPage',
		'Collection' => 'Ponticlaro\Bebop\Common\Collection',
		'Metabox'    => 'Ponticlaro\Bebop\Cms\Metabox',
		'Option'     => 'Ponticlaro\Bebop\Db\Option',
		'PostType'   => 'Ponticlaro\Bebop\Cms\PostType',
		'Shortcode'  => 'Ponticlaro\Bebop\Cms\Shortcode',
		'Taxonomy'   => 'Ponticlaro\Bebop\Cms\Taxonomy'
	);

	/**
	 * Creates instance of target class
	 * 
	 * @param  string $type Class ID
	 * @param  array  $args Class arguments
	 * @return object       Class instance
	 */
	public static function create($type, array $args = array())
	{
		// Create object
		$obj = parent::create($type, $args);

		// Track object if trackable
	    if ($obj instanceof TrackableObjectAbstract) 
	    	ObjectTracker::getInstance()->track($obj);

	    return $obj;
	}
}