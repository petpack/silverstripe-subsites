<?php
/**
 * Allows extensions to the Controller class
 * Implement in _config.php using:
 *   Object::add_extension('Controller','SubsiteController');
 */
class SubsiteController extends Extension {
	/**
	 * When in the live environment, always use the primary domain
	 * @see Controller::handleRequest()
	 */
	public function onBeforeInit() {
		if( Director::isLive() && !Director::is_cli() ) {
			$subsite = Subsite::currentSubsite();
			$primary = $subsite->getPrimaryDomain();
			if($primary && $_SERVER['HTTP_HOST'] != $primary ) {
				$path = $_SERVER['REQUEST_URI'];
				//ensure slash between domain and URI, but only when there's a URI:
				if ($path && substr($primary,-1) != '/' && substr($path,0,1) != '/')
					$path = "/$path"; 
				$url = Director::protocol() . "{$primary}{$path}";
				Director::redirect($url);
			}
		}
	}
}