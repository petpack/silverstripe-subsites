<?php

class SubsiteDomain extends DataObject {
	static $db = array(
		"Domain" => "Varchar(255)",
		"IsPrimary" => "Boolean",
		"SelfManaged" => "Boolean",
	);
	static $has_one = array(
 		"Subsite" => "Subsite",
	);
	
	/**
	 * List of supported top level domains (TLDs)
	 * @var Array
	 */
	static $tlds = Array(
		'.com.au',
		'.net.au',
		'.org.au',
		'.com',
		'.net',
		'.org',
	);
	
	function TitleField() {
		return 'Domain';
	}
	
	/**
	 * Return the TLD for a given domain (or subdomain)
	 * @param string $domain
	 */
	static public function getTLD($domain) {
		foreach (self::$tlds as $tld) {
			if (substr($domain,strlen($tld)*-1) == $tld)
				return $tld;
		}
		return null;
	}
	
	static public function removeTLD($domain) {
		$tld = self::getTLD($domain);
		if ($tld) 
			return substr($domain,0,strlen($domain) - strlen($tld));
		else
			return $domain;
	}
	
	static public function isSubdomain($domain) {
		return (strpos(self::removeTLD($domain),".") != false);
	}
	
	static public function subDomains($domain) {
		//removes TLD and returns domain split into subdomains
		return explode(".",self::removeTLD($domain));
	}
	
	/**
	 * Whenever a Subsite Domain is written, rewrite the hostmap
	 *
	 * @return void
	 */
	public function onAfterWrite() {
		Subsite::writeHostMap();
	}
	
	public function validate() {
		$ret = new ValidationResult(True);
		
		if (strtolower(substr(trim($this->Domain),0,4)) == "www.")
			$ret->error("You cannot add a www. subdomain, www is handled automatically.<br />Add the root domain (" . substr($this->Domain,4) . ") instead.");
		
		$sql = "Select * from SubsiteDomain where Domain = '" . $this->Domain . "'";
		if ($this->ID)
			$sql .= " and ID != " . $this->ID;
		$res = DB::query($sql);
		foreach ($res as $row) {
			$subsite = DataObject::get_by_id("Subsite", $row['SubsiteID']);
			$ret->error("The domain '" . $this->Domain . "' is already in the system (Subsite: " . $subsite->title . ")");
			break;
		}
		
		return $ret;
	}
	
	/**
	 * Do a whois on the domain
	 * @return string
	 */
	public function whois() {
		
		//domain.com.au , domain.com, or domain.net
		
		if (self::isSubdomain($this->Domain))
			//remove subdomains: 
			$domain = array_pop(self::subDomains($this->Domain)) . 
			self::getTLD($this->Domain);
		else 
			$domain = $this->Domain;
		
		$whois = shell_exec("whois " . $domain);
		$whois = str_replace("\n", "<br />", $whois); 
		
		$whois = "<h4>Whois info:</h4><pre>$whois</pre>";
		return $whois;
	}
	
	public function DNSCheck() {
		Requirements::css('pet-pack/css/admin.css');
		$color=""; //red, green, orange. anything else is black.
		if (
			(strpos(strtolower($this->Domain),"petpack") !== false) ||
			(strpos(strtolower($this->Domain),"localhost") !== false) ||
			(strpos(strtolower($this->Domain),"staging") !== false) ||
			(substr(strtolower($this->Domain),0,4) == "www.")	
		) {
			//$color = "orange";
			$msg = "<em>N/A</em>"; 
		} else {
			$color = "green";
			
			require_once('../lib/webapi.php');
			$i = new DomainInfo($this->Domain);
			$msg = $i->check_website();
			if (strpos($msg,"perfect")!== false)
				$color = 'green';
			else $color = "orange";
			//$msg = str_replace("\n", "<br />",$msg);
			//$msg = "<span style='font-size:0.7em'>$msg</span>";
		}
		
		return "<div class='trafficlight $color'></div>$msg";
	}
	
	function DNSCheck_brief() {
		$ret = $this->DNSCheck();
		if (strlen($ret) > 60)
			$ret = substr($ret,0,60) . "...";
		return $ret;
	}
	
	public function getCMSFields($params = null) {
		$fields = parent::getCMSFields($params);
		
		/*
		require_once('../lib/webapi.php');
		
		$i = new DomainInfo($this->Domain);
		
		$fields->addFieldToTab("Root.Main", new LiteralField('whois', 
			$i->report()
		));
		*/
		
		return $fields;
	}
	
}