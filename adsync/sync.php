<?php

/**
 * Script to pull user from AD
 *************************************/

/* functions */
require_once( dirname(__FILE__) . '/../functions/functions.php' );
require_once( dirname(__FILE__) . "/../functions/adLDAP/src/adLDAP.php");

function sync_with_AD($uname){

	# initialize user object
	$Database 	= new Database_PDO;
	$Admin	 	= new Admin ($Database, false);
	$User           = new User ($Database);

	# settings
	$adserver = Config::ValueOf("ADautopull");;

	# fetch server
	$server = $Admin->fetch_object("usersAuthMethod", "description", $adserver);
	$server!==false ? : die('Invalid ID, check your config');

	//parse parameters
	$params = json_decode($server->params);

	//no login parameters
	if(strlen(@$params->adminUsername)==0 || strlen(@$params->adminPassword)==0) {
		die('Please fill in the credentials in the AD login form');
	}

	$user = $Database->findObject("users", "username", $uname);
		if($user === null){
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
			$userinfo = $adldap->user()->info("$uname*", array('samaccountname', 'displayname', 'mail'),false,$server->type);
			//$usernames = $adldap->user()->all(false, "(samaccountname=$uname)");

				if($userinfo !== false){
					//$userinfo = $adldap->user()->info($username, array('samaccountname', 'displayname', 'mail'));
					$realname = $userinfo[0]['displayname'][0];
					$username = $userinfo[0]['samaccountname'][0];
					$mail = $userinfo[0]['mail'][0];
					$role = "User";
					$authmethod = Config::ValueOf("ADautopullID");;
					$lang = 1;
					$perms = array(
						'perm_vlan'=>'2',
						'perm_l2dom'=>'2',
						'perm_vrf'=>'2',
						'perm_devices'=>'2',
						'perm_racks'=>'2',
						'perm_circuits'=>'2',
						'perm_nat'=>'2',
						'perm_customers'=>'2',
						'perm_locations'=>'2',
						'perm_vaults'=>'2'
					);

					if(strlen($realname) == 0) $realname = $username;
					if(strlen($mail) == 0) $mail = "nobody@example.com";
					$values = array(
						"id"             =>@null,
						"real_name"      =>$realname,
						"username"       =>$username,
						"email"          =>$mail,
						"role"           =>$role,
						"authMethod"     =>$authmethod,
						"lang"           =>$lang,
						"mailNotify"     =>"No",
						"mailChangelog"  =>"No",
						"theme"          =>"",
						"disabled"       =>"No",
										"groups"         =>'{"2":"2","3":"3"}'
						);
					# permissions
					$permissions = [];
					# check
					foreach ($User->get_modules_with_permissions() as $m) {
						if (isset($perms['perm_'.$m])) {
							if (is_numeric($perms['perm_'.$m])) {
								$permissions[$m] = $perms['perm_'.$m];
							}
						}
					}
					$values['module_permissions'] = json_encode($permissions);
					$Admin->object_modify("users", "add", "id", $values);
				}

			//echo $adldap->getLastError();
		} catch (adLDAPException $e) {
			die('LDAP: '.$e->getMessage());
		} catch (Exception $e) {
				die('Database: '.$e->getMessage());
		}
	}
}

if(isset($_GET['uname'])) sync_with_AD($_GET['uname']);
header('Location: /index.php?page=login');

?>
<a href="/index.php?page=login"></a>