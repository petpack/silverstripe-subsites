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
	
	public function getCMSFields($params) {
		$fields = parent::getCMSFields($params);
		
		$fields->addFieldToTab("Root.Main", new LiteralField('whois', $this->whois()));
		
		return $fields;
	}
	
}