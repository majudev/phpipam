<?php

/**
 * Script to display usermod result
 *************************************/

/* functions */
require_once( dirname(__FILE__) . '/../functions/functions.php' );
require( dirname(__FILE__) . "/../functions/adLDAP/src/adLDAP.php");

# initialize user object
$Database 	= new Database_PDO;
$Admin	 	= new Admin ($Database);

# settings
$adserver = "FakeAD";

# verify that we are running from cli
if(php_sapi_name() !== "cli") die();

# fetch server
$server = $Admin->fetch_object("usersAuthMethod", "description", $adserver);
$server!==false ? : die('Invalid ID, check your config');

//parse parameters
$params = json_decode($server->params);

//no login parameters
if(strlen(@$params->adminUsername)==0 || strlen(@$params->adminPassword)==0) {
	die('Please fill in the credentials in the AD login form');
}

//open connection
try {
	if($server->type == "NetIQ") { $params->account_suffix = ""; }
	//set options
	$options = array(
			'base_dn'=>$params->base_dn,
			'account_suffix'=>$params->account_suffix,
			'domain_controllers'=>explode(";", str_replace(" ", "", $params->domain_controllers)),
			'use_ssl'=>$params->use_ssl,
			'use_tls'=>$params->use_tls,
			'ad_port'=>$params->ad_port
			);
	//AD
	$adldap = new adLDAP($options);

	// set OpenLDAP flag
	if($server->type == "LDAP") { $adldap->setUseOpenLDAP(true); }

	//try to login with higher credentials for search
	$authUser = $adldap->authenticate($params->adminUsername, $params->adminPassword);
	if ($authUser == false) {
		die('Failed to authenticate in AD with credentials supplied in authmethod config');
	}

	//search for domain user!
	//$userinfo = $adldap->user()->info("$_POST[dname]*", array("*"),false,$server->type);
	$usernames = $adldap->user()->all();

	//echo $adldap->getLastError();
} catch (adLDAPException $e) {
	die('LDAP: '.$e->getMessage());
}

foreach ($usernames as $username)
{
    try {
        $user = $Database->findObject("users", "username", $username);
        if($user === null){
            $userinfo = $adldap->user()->info($username, array('samaccountname', 'displayname', 'mail'));
            echo $userinfo[0]['displayname'][0]; echo ' ';
            echo $userinfo[0]['samaccountname'][0]; echo ' ';
            echo $userinfo[0]['mail'][0]; echo "\n";
        }
    } catch (Exception $e) {
        die('Database: '.$e->getMessage());
    }
}

?>