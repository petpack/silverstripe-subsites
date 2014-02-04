<?php
/**
 * A dynamically created subsite. SiteTree objects can now belong to a subsite.
 * You can simulate subsite access without setting up virtual hosts by appending ?SubsiteID=<ID> to the request.
 *
 * @package subsites
 */
class Subsite extends DataObject implements PermissionProvider {

	/**
	 * @var boolean $disable_subsite_filter If enabled, bypasses the query decoration
	 * to limit DataObject::get*() calls to a specific subsite. Useful for debugging.
	 */
	static $disable_subsite_filter = false;

	/**
	 * @var boolean $disable_subsite_selection If enabled, disables the selection of the 
	 * current Subsite based on the SubsiteID in the Session.
	 */
	static $disable_subsite_selection = false;

	/**
	 * Allows you to force a specific subsite ID, or comma separated list of IDs.
	 * Only works for reading. An object cannot be written to more than 1 subsite.
	 */
	static $force_subsite = null;

	static $write_hostmap = true;
	static $default_sort = "\"Title\" ASC";

	static $db = array(
		'Title' => 'Varchar(255)',
		'RedirectURL' => 'Varchar(255)',
		'DefaultSite' => 'Boolean',
		'Theme' => 'Varchar',
		'Language' => 'Varchar(6)',

		// Used to hide unfinished/private subsites from public view.
		// If unset, will default to true
		'IsPublic' => 'Boolean'
	);
	
	static $has_one = array(
	);
	
	static $has_many = array(
		'Domains' => 'SubsiteDomain',
	);
	
	static $belongs_many_many = array(
		"Groups" => "Group",
	);

	static $defaults = array(
		'IsPublic' => 1
	);

	static $searchable_fields = array(
		'Title' => array(
			'title' => 'Subsite Name'
		),
		'Domains.Domain' => array(
			'title' => 'Domain name'
		),
		'IsPublic' => array(
			'title' => 'Active subsite',
		),	
	);

	static $summary_fields = array(
		'Title' => 'Subsite Name',
		'PrimaryDomain' => 'Primary Domain',
		'IsPublic' => 'Active subsite',
	);

	/**
	 * @var array $allowed_themes Numeric array of all themes which are allowed to be selected for all subsites.
	 * Corresponds to subfolder names within the /themes folder. By default, all themes contained in this folder
	 * are listed.
	 */
	protected static $allowed_themes = array();

	/**
	 * Signifies if sub-site filtering has been disabled temporarily using self::temporarily_disable_subsite_filter()
	 *
	 * @var array(bool)
	 * @see self::temporarily_disable_subsite_filter()
	 * @see self::restore_disable_subsite_filter()
	 * @author Adam Rice <development@hashnotadam.com>
	 */
	static $previous_disable_subsite_filter = array();

	/**
	 * Signifies if subsites has been disabled temporarily using self::temporarily_set_subsite()
	 * 
	 * @var array(int)
	 * @see self::temporarily_set_subsite()
	 * @see self::restore_previous_subsite()
	 * @author Alex Hayes <alex.hayes@dimension27.com>
	 */
	static $previous_subsite_ids = array();

	static function set_allowed_domains($domain){
		user_error('Subsite::set_allowed_domains() is deprecated; it is no longer necessary '
			. 'because users can now enter any domain name', E_USER_NOTICE);
	}

	static function set_allowed_themes($themes) {
		self::$allowed_themes = $themes;
	}

	/**
	 * Return the themes that can be used with this subsite, as an array of themecode => description
	 */
	function allowedThemes() {
		if($themes = $this->stat('allowed_themes')) {
			return ArrayLib::valuekey($themes);
		} else {
			$themes = array();
			if(is_dir('../themes/')) {
				foreach(scandir('../themes/') as $theme) {
					if($theme[0] == '.') continue;
					$theme = strtok($theme,'_');
					$themes[$theme] = $theme;
				}
				ksort($themes);
			}
			return $themes;
		}
	}

	public function getLanguage() {
		if($this->getField('Language')) {
			return $this->getField('Language');
		} else {
			return i18n::get_locale();
		}
	}

	/**
	 * Whenever a Subsite is written, rewrite the hostmap
	 *
	 * @return void
	 */
	public function onAfterWrite() {
		parent::onAfterWrite();
		Subsite::writeHostMap();
	}
	
	/**
	 * Return the domain of this site
	 *
	 * @return string The full domain name of this subsite (without protocol prefix)
	 */
	function domain() {
		if($this->ID) {
			$domains = DataObject::get("SubsiteDomain", "\"SubsiteID\" = $this->ID", "\"IsPrimary\" DESC",
				"", 1);
			if($domains) {
				$domain = $domains->First()->Domain;
				// If there are wildcards in the primary domain (not recommended), make some
				// educated guesses about what to replace them with
				$domain = preg_replace("/\\.\\*\$/",".$_SERVER[HTTP_HOST]", $domain);
				$domain = preg_replace("/^\\*\\./","subsite.", $domain);
				$domain = str_replace('.www.','.', $domain);
				return $domain;
			}
			
		// SubsiteID = 0 is often used to refer to the main site, just return $_SERVER['HTTP_HOST']
		} else {
			return $_SERVER['HTTP_HOST'];
		}
	}
	
	function getPrimaryDomain() {
		return $this->domain();
	}

	function absoluteBaseURL() {
		return "http://" . $this->domain() . Director::baseURL();
	}

	/**
	 * Show the configuration fields for each subsite
	 */
	function getCMSFields() {
		$domainTable = new TableField("Domains", "SubsiteDomain", 
			array("Domain" => "Domain (use * as a wildcard)", "IsPrimary" => "Primary domain?"), 
			array("Domain" => "TextField", "IsPrimary" => "CheckboxField"), 
			"SubsiteID", $this->ID);
			
		$languageSelector = new DropdownField('Language', 'Language', i18n::get_common_locales());

		$fields = new FieldSet(
			new TabSet('Root',
				new Tab('Configuration',
					new HeaderField($this->getClassName() . ' configuration', 2),
					new TextField('Title', 'Name of subsite:', $this->Title),
					
					new HeaderField("Domains for this subsite"),
					$domainTable,
					$languageSelector,
					// new TextField('RedirectURL', 'Redirect to URL', $this->RedirectURL),
					new CheckboxField('DefaultSite', 'Default site', $this->DefaultSite),
					new CheckboxField('IsPublic', 'Enable public access', $this->IsPublic),

					new DropdownField('Theme','Theme', $this->allowedThemes(), $this->Theme)
				)
			),
			new HiddenField('ID', '', $this->ID),
			new HiddenField('IsSubsite', '', 1)
		);

		$this->extend('updateCMSFields', $fields);
		return $fields;
	}

	/**
	 * @todo getClassName is redundant, already stored as a database field?
	 */
	function getClassName() {
		return $this->class;
	}

	function getCMSActions() {
		return new FieldSet(
            new FormAction('callPageMethod', "Create copy", null, 'adminDuplicate')
		);
	}

	function adminDuplicate() {
		$newItem = $this->duplicate();
		$JS_title = Convert::raw2js($this->Title);
		return <<<JS
			statusMessage('Created a copy of $JS_title', 'good');
			$('Form_EditForm').loadURLFromServer('admin/subsites/show/$newItem->ID');
JS;
	}

	/**
	 * Gets the subsite currently set in the session.
	 *
	 * @uses ControllerSubsites->controllerAugmentInit()
	 * 
	 * @return Subsite
	 */
	static function currentSubsite() {
		// get_by_id handles caching so we don't have to
		return DataObject::get_by_id('Subsite', self::currentSubsiteID());
	}

	/**
	 * This function gets the current subsite ID from the session. It used in the backend so Ajax requests
	 * use the correct subsite. The frontend handles subsites differently. It calls getSubsiteIDForDomain
	 * directly from ModelAsController::getNestedController. Only gets Subsite instances which have their
	 * {@link IsPublic} flag set to TRUE.
	 *
	 * You can simulate subsite access without creating virtual hosts by appending ?SubsiteID=<ID> to the request.
	 *
	 * @todo Pass $request object from controller so we don't have to rely on $_REQUEST
	 *
	 * @param boolean $cache
	 * @return int ID of the current subsite instance
	 */
	static function currentSubsiteID() {
		if( self::$disable_subsite_selection ) {
			return self::getSubsiteIDForDomain();
		}
		
		if(isset($_REQUEST['SubsiteID']) && is_int($_REQUEST['SubsiteID']) ) $id = $_REQUEST['SubsiteID'];
		else $id = Session::get('SubsiteID');

		if($id === NULL) {
			$id = self::getSubsiteIDForDomain();
			// Session::set('SubsiteID', $id);
		}

		return (int)$id;
	}
	
	/**
	 * Returns true if a subsite id is set, false otherwise.
	 * 
	 * @return bool
	 * @author Alex Hayes <alex.hayes@dimension27.com>
	 */
	static function isSubsite() {
		$subsiteId = self::currentSubsiteID();
		if( $subsiteId > 0 ) {
			return true;
		}
		return false;
	}
	
	/**
	 * Switch to another subsite.
	 *
	 * @param int|Subsite $subsite Either the ID of the subsite, or the subsite object itself
	 */
	static function changeSubsite($subsite) {
		if( is_object($subsite) ) $subsiteID = $subsite->ID;
		else $subsiteID = $subsite;

		//* debug */ Debug::message('changeSubsite: ' . $subsiteID . " Trace: \n" . SS_Backtrace::get_rendered_backtrace(debug_backtrace(), true));
		Session::set('SubsiteID', (int)$subsiteID);
		// currentSubsiteID() values the $_REQUEST over the session
		if( isset($_REQUEST['SubsiteID']) ) unset($_REQUEST['SubsiteID']);
		// Set locale
		if( is_object($subsite) && $subsite->Language != '' && isset(i18n::$likely_subtags[$subsite->Language]) )
			i18n::set_locale(i18n::$likely_subtags[$subsite->Language]);
		
		// Only flush the cache if the Subsite actually needed to be changed
		if($subsiteID != self::currentSubsiteID()) Permission::flush_permission_cache();
	}

	/**
	 * Make this subsite the current one
	 */
	public function activate() {
		Subsite::changeSubsite($this);
	}

	/**
	 * @todo Possible security issue, don't grant edit permissions to everybody.
	 */
	function canEdit() {
		return true;
	}

	/**
	 * Get a matching subsite for the given host, or for the current HTTP_HOST.
	 * 
	 * @param $host The host to find the subsite for.  If not specified, $_SERVER['HTTP_HOST']
	 * is used.
	 *
	 * @return int Subsite ID
	 */
	static function getSubsiteIDForDomain($host = null, $returnMainIfNotFound = true) {
		static $subsiteForDomain = array();
		
		if($host == null) $host = $_SERVER['HTTP_HOST'];
		if(isset($subsiteForDomain[$host])) return $subsiteForDomain[$host];
		
		//treat 'www.domain' the same as 'domain':
		//DM: only replace www. at start of string: www.domain.www.somedomain.com 
		//	is perfectly valid
		$host = preg_replace("/^www\./", '', $host);
		
		//Treat 'staging.domain' (and, by extension, 'www.staging.domain') the 
		//	same as 'domain':
		$host = preg_replace('/^staging\./','',$host);
		
		//remove port numbers from host name:
		$host = preg_replace('/:\d+$/','',$host);
		
		$SQL_host = Convert::raw2sql($host);
		
		$matchingDomains = DataObject::get("SubsiteDomain", "'$SQL_host' LIKE replace(\"SubsiteDomain\".\"Domain\",'*','%')",
			"\"IsPrimary\" DESC", "INNER JOIN \"Subsite\" ON \"Subsite\".\"ID\" = \"SubsiteDomain\".\"SubsiteID\" AND
			\"Subsite\".\"IsPublic\"=1");
		
		
		if($matchingDomains) {
			$subsiteIDs = array_unique($matchingDomains->column('SubsiteID'));
			if(sizeof($subsiteIDs) > 1) user_error("Multiple subsites match '$host'", E_USER_WARNING);
			$subsiteForDomain[$host] = $subsiteIDs[0];
			return $subsiteIDs[0];
		}
		
		// Check for a 'default' subsite
		if ($returnMainIfNotFound && ($default = DataObject::get_one('Subsite', "\"DefaultSite\" = 1"))) {
			$subsiteForDomain[$host] = $default->ID;
			return $default->ID;
		}
		
		// Default subsite id = 0, the main site
		return 0;
	}

	function getMembersByPermission($permissionCodes = array('ADMIN')){
		if(!is_array($permissionCodes))
			user_error('Permissions must be passed to Subsite::getMembersByPermission as an array', E_USER_ERROR);
		$SQL_permissionCodes = Convert::raw2sql($permissionCodes);

		$SQL_permissionCodes = join("','", $SQL_permissionCodes);

		return DataObject::get(
			'Member',
			"\"Group\".\"SubsiteID\" = $this->ID AND \"Permission\".\"Code\" IN ('$SQL_permissionCodes')",
			'',
			"LEFT JOIN \"Group_Members\" ON \"Member\".\"ID\" = \"Group_Members\".\"MemberID\"
			LEFT JOIN \"Group\" ON \"Group\".\"ID\" = \"Group_Members\".\"GroupID\"
			LEFT JOIN \"Permission\" ON \"Permission\".\"GroupID\" = \"Group\".\"ID\""
		);
	
	}

	static function hasMainSitePermission($member = null, $permissionCodes = array('ADMIN')) {
		if(!is_array($permissionCodes))
			user_error('Permissions must be passed to Subsite::hasMainSitePermission as an array', E_USER_ERROR);

		if(!$member && $member !== FALSE) $member = Member::currentMember();

		if(!$member) return false;
		
		if(!in_array("ADMIN", $permissionCodes)) $permissionCodes[] = "ADMIN";

		$SQLa_perm = Convert::raw2sql($permissionCodes);
		$SQL_perms = join("','", $SQLa_perm);
		$memberID = (int)$member->ID;
		
		$groupCount = DB::query("
			SELECT COUNT(\"Permission\".\"ID\")
			FROM \"Permission\"
			INNER JOIN \"Group\" ON \"Group\".\"ID\" = \"Permission\".\"GroupID\" AND \"Group\".\"AccessAllSubsites\" = 1
			INNER JOIN \"Group_Members\" ON \"Group_Members\".\"GroupID\" = \"Permission\".\"GroupID\"
			WHERE \"Permission\".\"Code\" IN ('$SQL_perms')
			AND \"MemberID\" = {$memberID}
		")->value();

		return ($groupCount > 0);

	}

	/**
	 * Duplicate this subsite
	 */
	function duplicate() {
		$newTemplate = parent::duplicate();

		$oldSubsiteID = Session::get('SubsiteID');
		self::changeSubsite($this->ID);

		/*
		 * Copy data from this template to the given subsite. Does this using an iterative depth-first search.
		 * This will make sure that the new parents on the new subsite are correct, and there are no funny
		 * issues with having to check whether or not the new parents have been added to the site tree
		 * when a page, etc, is duplicated
		 */
		$stack = array(array(0,0));
		while(count($stack) > 0) {
			list($sourceParentID, $destParentID) = array_pop($stack);

			$children = Versioned::get_by_stage('Page', 'Live', "\"ParentID\" = $sourceParentID", '');

			if($children) {
				foreach($children as $child) {
					$childClone = $child->duplicateToSubsite($newTemplate, false);
					$childClone->ParentID = $destParentID;
					$childClone->writeToStage('Stage');
					$childClone->publish('Stage', 'Live');
					array_push($stack, array($child->ID, $childClone->ID));
				}
			}
		}

		self::changeSubsite($oldSubsiteID);

		return $newTemplate;
	}


	/**
	 * Return the subsites that the current user can access.
	 * Look for one of the given permission codes on the site.
	 *
	 * Sites and Templates will only be included if they have a Title
	 *
	 * @param $permCode array|string Either a single permission code or an array of permission codes.
	 * @param $includeMainSite If true, the main site will be included if appropriate.
	 * @param $mainSiteTitle The label to give to the main site
	 */
	function accessible_sites($permCode, $includeMainSite = false, $mainSiteTitle = "Main site", $member = null) {
		// Rationalise member arguments
		if(!$member) $member = Member::currentUser();
		if(!$member) return new DataObjectSet();
		if(!is_object($member)) $member = DataObject::get_by_id('Member', $member);

		if(is_array($permCode))	$SQL_codes = "'" . implode("', '", Convert::raw2sql($permCode)) . "'";
		else $SQL_codes = "'" . Convert::raw2sql($permCode) . "'";

		$templateClassList = "'" . implode("', '", ClassInfo::subclassesFor("Subsite_Template")) . "'";

		$subsites = DataObject::get(
			'Subsite',
			"\"Subsite\".\"Title\" != ''",
			'',
			"LEFT JOIN \"Group_Subsites\" 
				ON \"Group_Subsites\".\"SubsiteID\" = \"Subsite\".\"ID\"
			INNER JOIN \"Group\" ON \"Group\".\"ID\" = \"Group_Subsites\".\"GroupID\"
				OR \"Group\".\"AccessAllSubsites\" = 1
			INNER JOIN \"Group_Members\" 
				ON \"Group_Members\".\"GroupID\"=\"Group\".\"ID\"
				AND \"Group_Members\".\"MemberID\" = $member->ID
			INNER JOIN \"Permission\" 
				ON \"Group\".\"ID\"=\"Permission\".\"GroupID\"
				AND \"Permission\".\"Code\" IN ($SQL_codes, 'ADMIN')"
		);

		$rolesSubsites = DataObject::get(
			'Subsite',
			"\"Subsite\".\"Title\" != ''",
			'',
			"LEFT JOIN \"Group_Subsites\" 
				ON \"Group_Subsites\".\"SubsiteID\" = \"Subsite\".\"ID\"
			INNER JOIN \"Group\" ON \"Group\".\"ID\" = \"Group_Subsites\".\"GroupID\"
				OR \"Group\".\"AccessAllSubsites\" = 1
			INNER JOIN \"Group_Members\" 
				ON \"Group_Members\".\"GroupID\"=\"Group\".\"ID\"
				AND \"Group_Members\".\"MemberID\" = $member->ID
			INNER JOIN \"Group_Roles\"
				ON \"Group_Roles\".\"GroupID\"=\"Group\".\"ID\"
			INNER JOIN \"PermissionRole\"
				ON \"Group_Roles\".\"PermissionRoleID\"=\"PermissionRole\".\"ID\"
			INNER JOIN \"PermissionRoleCode\"
				ON \"PermissionRole\".\"ID\"=\"PermissionRoleCode\".\"RoleID\"
				AND \"PermissionRoleCode\".\"Code\" IN ($SQL_codes, 'ADMIN')"
		);

		if(!$subsites && $rolesSubsites) return $rolesSubsites;

		if($rolesSubsites) foreach($rolesSubsites as $subsite) {
			if(!$subsites->containsIDs(array($subsite->ID))) {
				$subsites->push($subsite);
			}
		}

		// Include the main site
		if(!$subsites) $subsites = new DataObjectSet();
		if($includeMainSite) {
			if(!is_array($permCode)) $permCode = array($permCode);
			if(self::hasMainSitePermission($member, $permCode)) {
				$mainSite = new Subsite();
				$mainSite->Title = $mainSiteTitle;
				$subsites->insertFirst($mainSite);
			}
		}

		return $subsites;
	}
	
	/**
	 * Write a host->domain map to subsites/host-map.php
	 *
	 * This is used primarily when using subsites in conjunction with StaticPublisher
	 *
	 * @return void
	 * @scalability-concern Selection of ALL subsites is done, a better approach, however more difficult
	 *                      approach would be to append the file on create and modify on update/delete.
	 */
	static function writeHostMap($file = null) {
		if (!self::$write_hostmap) return;
		
		if (!$file) $file = Director::baseFolder().'/subsites/host-map.php';
		$hostmap = array();
		
		$subsites = DataObject::get('Subsite');
		
		if ($subsites) foreach($subsites as $subsite) {
			$domains = $subsite->Domains();
			if ($domains) foreach($domains as $domain) {
				$hostmap[str_replace('www.', '', $domain->Domain)] = $subsite->domain(); 
			}
			if ($subsite->DefaultSite) $hostmap['default'] = $subsite->domain();
		}
		
		$data = "<?php \n";
		$data .= "// Generated by Subsite::writeHostMap() on " . date('d/M/y') . "\n";
		$data .= '$subsiteHostmap = ' . var_export($hostmap, true) . ';';

		if (is_writable(dirname($file)) || is_writable($file)) {
			file_put_contents($file, $data);
		}
	}

	//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
	// CMS ADMINISTRATION HELPERS
	//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

	/**
	 * Return the FieldSet that will build the search form in the CMS
	 */
	function adminSearchFields() {
		return new FieldSet(
			new TextField('Name', 'Sub-site name')
		);
	}

	function providePermissions() {
		return array(
			'SUBSITE_ASSETS_CREATE_SUBSITE' => array(
				'name' => _t('Subsite.MANAGE_ASSETS', 'Manage assets for subsites'),
				'category' => _t('Permissions.PERMISSIONS_CATEGORY', 'Roles and access permissions'),
				'help' => _t('Subsite.MANAGE_ASSETS_HELP', 'Ability to select the subsite to which an asset folder belongs. Requires "Access to Files & Images."'),
				'sort' => 300
			)
		);
	}

	static function get_from_all_subsites($className, $filter = "", $sort = "", $join = "", $limit = "") {
		$oldState = self::$disable_subsite_filter;
		self::$disable_subsite_filter = true;
		$result = DataObject::get($className, $filter, $sort, $join, $limit);
		self::$disable_subsite_filter = $oldState;
		return $result;
	}

	/**
	 * Disable the sub-site filtering; queries will select from all subsites
	 */
	static function disable_subsite_filter($disabled = true) {
		self::$disable_subsite_filter = $disabled;
	}

	/**
	 * Disable sub-site filtering but store the current filtering state for restoration
	 *
	 * @see self::restore_disable_subsite_filter()
	 * @author Adam Rice <development@hashnotadam.com>
	 */
	static function temporarily_disable_subsite_filter() {
		self::$previous_disable_subsite_filter[] = self::$disable_subsite_filter;
		self::disable_subsite_filter();
	}

	/**
	 * Restores the sub-site filtering state in use before temporarily_disable_subsite_filter was called
	 *
	 * @see self::temporarily_disable_subsite_filter()
	 * @author Adam Rice <development@hashnotadam.com>
	 */
	static function restore_disable_subsite_filter() {
		if( self::$previous_disable_subsite_filter ) {
			$state = array_pop(self::$previous_disable_subsite_filter);
			self::disable_subsite_filter($state);
		}
	}

	/**
	 * Disables the selection of the current Subsite based on the SubsiteID in the Session.
	 * @param boolean $disabled
	 */
	static function disable_subsite_selection($disabled = true) {
		self::$disable_subsite_selection = $disabled;
	}
	
	/**
	 * Sets the subsite to '0' providing ability to restore it at a later date using restore_previous_subsite
	 *
	 * <code>
	 * Subsite::temporarily_set_subsite();
	 * // Do something....
	 * Subsite::restore_previous_subsite();
	 * </code>
	 * 
	 * or...
	 * 
	 * <code>
	 * $subsite->temporarily_set_subsite();
	 * // Do something....
	 * $subsite->restore_previous_subsite();
	 * </code>
	 * 
	 * @param null|int|Subsite $subsite     Temporarily set the subsite, the following outlines the behaviour of this
	 *                                      method:
	 *                                      - null: if called from an instance then the subsite is assumed 
	 *                                              to the instance, however if called statically called then
	 *                                              the subsite is set to 0 (ie no subsite).
	 *                                      - int: assumed this is subsite id.
	 *                                      - Subsite: sets the temporary subsite to this Subsite's id.
	 *                                             
	 * @return bool                         True if the subsite could be changed, false if the current subsite 
	 *                                      is already set to $subsite_id
	 * 
	 * @see self::restore_previous_subsite()
	 * @author Alex Hayes <alex.hayes@dimension27.com>
	 */
	public function temporarily_set_subsite( $subsite = 0 ) {
		if( is_int($subsite) && $subsite === 0 && isset($this) ) {
			$subsite = $this;
		}
		if( is_object($subsite)) {
			$subsite_id = $subsite instanceof Subsite ? $subsite->ID : 0;
		} else {
			$subsite_id = $subsite;
		}
		$current_subsite_id = Subsite::currentSubsiteID();
		if( $current_subsite_id == $subsite_id ) {
			//* debug */ Debug::message('ignore temporarily_set_subsite: ' . $subsite_id);
			return false;
		}
		self::$previous_subsite_ids[] = $current_subsite_id;
		//* debug */ Debug::message('temporarily_set_subsite to ' . $subsite_id . ' - currently ' . $current_subsite_id); 
		Subsite::changeSubsite($subsite_id);
		return true;
	}
	
	/**
	 * Restores the previous subsite before temporarily_set_subsite was called.
	 *
	 * @return bool    True if the subsite could be restore, false if there is no previous subsite to restore.
	 * 
	 * @see self::temporarily_set_subsite()
	 * @author Alex Hayes <alex.hayes@dimension27.com>
	 */
	static function restore_previous_subsite() {
		if( self::$previous_subsite_ids ) {
			$previousId = array_pop(self::$previous_subsite_ids);
			//* debug */ Debug::message('restore_previous_subsite: ' . $previousId);
			Subsite::changeSubsite($previousId);
			return true;
		}
		//* debug */ Debug::message('ignore restore_previous_subsite');
		return false;
	}

	static public function call_func_with_subsite($callback, $args = null) {
		$filter = self::$disable_subsite_filter;
		self::disable_subsite_filter(false);
		$subsiteID = self::currentSubsiteID();
		if( !$subsiteID && $_SESSION['SubsiteID'] )
			self::temporarily_set_subsite($_SESSION['SubsiteID']);

		if( $args && !is_array($args) ) $args = array($args);
		$rv = $args ? call_user_func_array($callback, $args) : call_user_func($callback);

		if( !$subsiteID ) self::restore_previous_subsite();
		self::disable_subsite_filter($filter);

		return $rv;
	}
	
	static public function call_func_without_subsite($callback, $args = null) {
		return self::call_func_on_subsite(0, $callback, $args);
	}

	static public function call_func_on_subsite($subsiteId, $callback, $args = null) {
		self::temporarily_set_subsite($subsiteId);
		if( $args && !is_array($args) ) $args = array($args);
		$rv = $args ? call_user_func_array($callback, $args) : call_user_func($callback);
		self::restore_previous_subsite($subsiteId);
		return $rv;
	}
}

/**
 * An instance of subsite that can be duplicated to provide a quick way to create new subsites.
 *
 * @package subsites
 */
class Subsite_Template extends Subsite {
	/**
	 * Create an instance of this template, with the given title & domain
	 */
	function createInstance($title, $domain = null) {
		$intranet = Object::create('Subsite');
		$intranet->Title = $title;
		$intranet->TemplateID = $this->ID;
		$intranet->write();
		
		if($domain) {
			$intranetDomain = Object::create('SubsiteDomain');
			$intranetDomain->SubsiteID = $intranet->ID;
			$intranetDomain->Domain = $domain;
			$intranetDomain->write();
		}

		$oldSubsiteID = Session::get('SubsiteID');
		self::changeSubsite($this->ID);

		/*
		 * Copy site content from this template to the given subsite. Does this using an iterative depth-first search.
		 * This will make sure that the new parents on the new subsite are correct, and there are no funny
		 * issues with having to check whether or not the new parents have been added to the site tree
		 * when a page, etc, is duplicated
		 */
		$stack = array(array(0,0));
		while(count($stack) > 0) {
			list($sourceParentID, $destParentID) = array_pop($stack);

			$children = Versioned::get_by_stage('SiteTree', 'Live', "\"ParentID\" = $sourceParentID", '');

			if($children) {
				foreach($children as $child) {
					$childClone = $child->duplicateToSubsite($intranet);
					$childClone->ParentID = $destParentID;
					$childClone->writeToStage('Stage');
					$childClone->publish('Stage', 'Live');
					array_push($stack, array($child->ID, $childClone->ID));
				}
			}
		}

		self::changeSubsite($oldSubsiteID);

		return $intranet;
	}
}
?>
