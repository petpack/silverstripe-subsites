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
			if( $_SERVER['HTTP_HOST'] != $primary ) {
				Director::redirect(Director::protocol() . "{$primary}{$_SERVER['REQUEST_URI']}");
			}
		}
	}
}