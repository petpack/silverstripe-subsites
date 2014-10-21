<?php

class SubsiteDomain extends DataObject {
	static $db = array(
		"Domain" => "Varchar(255)",
		"IsPrimary" => "Boolean",
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
		$color=""; //red, green, orange. 
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
			
			require_once('../../webapi.php');
			$i = new DomainInfo($this->Domain);
			$msg = $i->check_website();
			if (strpos($msg,"All OK")!== false)
				$color = 'green';
			else $color = "orange";
			$msg = str_replace("\n", "<br />",$msg);
			//$msg = "<span style='font-size:0.7em'>$msg</span>";
		}
			
		return "<div class='trafficlight $color'></div><span style='font-size:0.7em;'>$msg</span>";
	}
	
	public function getCMSFields($params = null) {
		$fields = parent::getCMSFields($params);
		
		require_once('../../webapi.php');
		
		$i = new DomainInfo($this->Domain);
		
		$fields->addFieldToTab("Root.Main", new LiteralField('whois', 
			"<pre>" . $i->check_website() . "</pre>"
		));
		
		return $fields;
	}
	
}