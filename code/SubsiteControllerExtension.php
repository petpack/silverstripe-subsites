<?php
class SubsiteController extends Extension {
	public function onBeforeInit( $request ) {
		if( Director::isLive() ) {
			$subsite = Subsite::currentSubsite();
			$primary = $subsite->getPrimaryDomain();
			if( $_SERVER['HTTP_HOST'] != $primary ) {
				Director::redirect(Director::protocol() . "{$primary}{$_SERVER['REQUEST_URI']}");
			}
		}
	}
}