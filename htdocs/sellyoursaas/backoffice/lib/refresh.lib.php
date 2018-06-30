<?php
// Files with some lib

// Show totals
$serverlocation=185.9;	// Price dollar
$dollareuro=0.78;		// Price euro
$serverprice=price2num($serverlocation * $dollareuro, 'MT');
$part=0.3;	// 30%


include_once(DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php');


/**
 * Process refresh of setup files for customer $object.
 * This does not update any lastcheck fields.
 *
 * @param 	Conf						$conf					Conf
 * @param 	Database					$db						Database handler
 * @param 	Contract|DoliCloudCustomer 	$object	    			Customer (can modify caller)
 * @param	array						$errors	    			Array of errors
 * @param	int							$printoutput			Print output information
 * @param	int							$recreateauthorizekey	1=Recreate authorized key if not found
 * @return	int													1
 */
function dolicloud_files_refresh($conf, $db, &$object, &$errors, $printoutput=0, $recreateauthorizekey=0)
{
	$instance = $object->instance;
	if (empty($instance)) $instance = $object->ref_customer;
	$username_web = $object->username_web;
	if (empty($username_web)) $username_web = $object->array_options['options_username_os'];
	$password_web = $object->password_web;
	if (empty($password_web)) $password_web = $object->array_options['options_password_os'];
	$database_db = $object->database_db;
	if (empty($database_db)) $database_db = $object->array_options['options_database_db'];

	$server=$instance;
	if (! preg_match('/on\.dolicloud\.com/', $instance) && ! preg_match('/with\.dolicloud\.com/', $instance) && ! preg_match('/home\.lan/', $instance))
	{
		$server=$instance.'.on.dolicloud.com';
	}

	// SFTP refresh
	if (function_exists("ssh2_connect"))
	{
		if ($printoutput) print "ssh2_connect ".$server." ".$username_web." ".$password_web."\n";

		$connection = ssh2_connect($server, 22);
		if ($connection)
		{
			if ($printoutput) print $instance." ".$username_web." ".$password_web."\n";

			if (! @ssh2_auth_password($connection, $username_web, $password_web))
			{
				dol_syslog("Could not authenticate with username ".$username_web." . and password ".preg_replace('/./', '*', $password_web), LOG_ERR);
			}
			else
			{
				$sftp = ssh2_sftp($connection);
				if (! $sftp)
				{
					dol_syslog("Could not execute ssh2_sftp",LOG_ERR);
					$errors[]='Failed to connect to ssh2 to '.$server;
					return 1;
				}

				$dir=preg_replace('/_([a-zA-Z0-9]+)$/','',$database_db);
				//$file="ssh2.sftp://".$sftp.$conf->global->DOLICLOUD_EXT_HOME.'/'.$object->username_web.'/'.$dir.'/htdocs/conf/conf.php';
				$file="ssh2.sftp://".intval($sftp).$conf->global->DOLICLOUD_EXT_HOME.'/'.$username_web.'/'.$dir.'/htdocs/conf/conf.php';    // With PHP 5.6.27+

				// Update ssl certificate
				// Dir .ssh must have rwx------ permissions
				// File authorized_keys must have rw------- permissions

				// Check if authorized_key exists
				//$filecert="ssh2.sftp://".$sftp.$conf->global->DOLICLOUD_EXT_HOME.'/'.$object->username_web.'/.ssh/authorized_keys';
				$filecert="ssh2.sftp://".intval($sftp).$conf->global->DOLICLOUD_EXT_HOME.'/'.$username_web.'/.ssh/authorized_keys';
				$fstat=@ssh2_sftp_stat($sftp, $conf->global->DOLICLOUD_EXT_HOME.'/'.$username_web.'/.ssh/authorized_keys');
				// Create authorized_keys file
				if (empty($fstat['atime']))
				{
					if ($recreateauthorizekey)
					{
						@ssh2_sftp_mkdir($sftp, $conf->global->DOLICLOUD_EXT_HOME.'/'.$username_web.'/.ssh');

						if ($printoutput) print 'Write file '.$conf->global->DOLICLOUD_EXT_HOME.'/'.$username_web.'/.ssh/authorized_keys'."\n";

						$stream = @fopen($filecert, 'w');
						//var_dump($stream);exit;
						if ($stream)
						{
							$publickeystodeploy = $conf->global->SELLYOURSAAS_PUBLIC_KEY;
							fwrite($stream, $publickeystodeploy);
							fclose($stream);
							$fstat=ssh2_sftp_stat($sftp, $conf->global->DOLICLOUD_EXT_HOME.'/'.$username_web.'/.ssh/authorized_keys');
						}
						else
						{
							$errors[]='Failed to open for write '.$filecert."\n";
						}
					}
					else
					{
						if ($printoutput) print 'File '.$conf->global->DOLICLOUD_EXT_HOME.'/'.$username_web."/.ssh/authorized_keys not found\n";
					}
				}
				else
				{
					if ($printoutput) print 'File '.$conf->global->DOLICLOUD_EXT_HOME.'/'.$username_web."/.ssh/authorized_keys already exists\n";
				}
				$object->fileauthorizedkey=(empty($fstat['mtime'])?'':$fstat['mtime']);

				// Check if install.lock exists
				//$fileinstalllock="ssh2.sftp://".$sftp.$conf->global->DOLICLOUD_EXT_HOME.'/'.$object->username_web.'/'.$dir.'/documents/install.lock';
				$fileinstalllock="ssh2.sftp://".intval($sftp).$conf->global->DOLICLOUD_EXT_HOME.'/'.$username_web.'/'.$dir.'/documents/install.lock';
				$fstatlock=@ssh2_sftp_stat($sftp, $conf->global->DOLICLOUD_EXT_HOME.'/'.$username_web.'/'.$dir.'/documents/install.lock');
				$object->filelock=(empty($fstatlock['atime'])?'':$fstatlock['atime']);

				// Define dates
				/*if (empty($object->date_registration) || empty($object->date_endfreeperiod))
				{
					// Overwrite only if not defined
					$object->date_registration=$fstatlock['mtime'];
					//$object->date_endfreeperiod=dol_time_plus_duree($object->date_registration,1,'m');
					$object->date_endfreeperiod=($object->date_registration?dol_time_plus_duree($object->date_registration,15,'d'):'');
				}*/
			}
		}
		else {
			$errors[]='Failed to connect to ssh2 to '.$server;
		}
	}
	else {
		$errors[]='ssh2_connect not supported by this PHP';
	}

	return 1;
}


/**
 * Process refresh of database for customer $object
 * This also update database field lastcheck.
 * This set a lot of object->xxx properties
 * 	->lastlogin_admin, ->lastpass_admin,
 * 	->nbofusers,
 * 	->modulesenabled, version, date_lastcheck, lastcheck
 *
 * @param 	Conf						$conf		Conf
 * @param 	Database					$db			Database handler
 * @param 	Contract|DoliCloudCustomer 	$object	    Customer (can modify caller)
 * @param	array						$errors	    Array of errors
 * @return	int										1
 */
function dolicloud_database_refresh($conf, $db, &$object, &$errors)
{
	$instance = $object->instance;
	if (empty($instance)) $instance = $object->ref_customer;
	$username_web = $object->username_web;
	if (empty($username_web)) $username_web = $object->array_options['options_username_os'];
	$password_web = $object->password_web;
	if (empty($password_web)) $password_web = $object->array_options['options_password_os'];

	$username_db = $object->username_db;
	if (empty($username_db)) $username_db = $object->array_options['options_username_db'];
	$password_db = $object->password_db;
	if (empty($password_db)) $password_db = $object->array_options['options_password_db'];
	$database_db = $object->database_db;
	if (empty($database_db)) $database_db = $object->array_options['options_database_db'];

	$server=$instance;
	if (! preg_match('/on\.dolicloud\.com/', $instance) && ! preg_match('/with\.dolicloud\.com/', $instance) && ! preg_match('/home\.lan/', $instance))
	{
		$server=$instance.'.on.dolicloud.com';
	}

	$newdb=getDoliDBInstance('mysqli', $server, $username_db, $password_db, $database_db, 3306);

	$ret=1;

	unset($object->lastlogin);
	unset($object->lastpass);
	unset($object->date_lastlogin);
	unset($object->date_lastcheck);
	unset($object->lastlogin_admin);
	unset($object->lastpass_admin);
	unset($object->modulesenabled);
	unset($object->version);
	unset($object->nbofusers);

	if (is_object($newdb))
	{
		$error=0;
		$done=0;

		if ($newdb->connected && $newdb->database_selected)
		{
			// Get user/pass of last admin user
			if (! $error)
			{
				$sql="SELECT login, pass FROM llx_user WHERE admin = 1 AND login <> '".$conf->global->SELLYOURSAAS_LOGIN_FOR_SUPPORT."' ORDER BY statut DESC, datelastlogin DESC LIMIT 1";
				$resql=$newdb->query($sql);
				if ($resql)
				{
					$obj = $newdb->fetch_object($resql);
					$object->lastlogin_admin=$obj->login;
					$object->lastpass_admin=$obj->pass;
					$lastloginadmin=$object->lastlogin_admin;
					$lastpassadmin=$object->lastpass_admin;
				}
				else $error++;
			}

			// Get list of modules
			if (! $error)
			{
				$modulesenabled=array(); $lastinstall=''; $lastupgrade='';
				$sql="SELECT name, value FROM llx_const WHERE name LIKE 'MAIN_MODULE_%' or name = 'MAIN_VERSION_LAST_UPGRADE' or name = 'MAIN_VERSION_LAST_INSTALL'";
				$resql=$newdb->query($sql);
				if ($resql)
				{
					$num=$newdb->num_rows($resql);
					$i=0;
					while ($i < $num)
					{
						$obj = $newdb->fetch_object($resql);
						if (preg_match('/MAIN_MODULE_/',$obj->name))
						{
							$name=preg_replace('/^[^_]+_[^_]+_/','',$obj->name);
							if (! preg_match('/_/',$name)) $modulesenabled[$name]=$name;
						}
						if (preg_match('/MAIN_VERSION_LAST_UPGRADE/',$obj->name))
						{
							$lastupgrade=$obj->value;
						}
						if (preg_match('/MAIN_VERSION_LAST_INSTALL/',$obj->name))
						{
							$lastinstall=$obj->value;
						}
						$i++;
					}
					$object->modulesenabled=join(',',$modulesenabled);
					$object->version=($lastupgrade?$lastupgrade:$lastinstall);
				}
				else $error++;
			}

			// Get nb of users
			if (! $error)
			{
				$sql="SELECT COUNT(login) as nbofusers FROM llx_user WHERE statut <> 0 AND login <> '".$conf->global->SELLYOURSAAS_LOGIN_FOR_SUPPORT."'";
				$resql=$newdb->query($sql);
				if ($resql)
				{
					$obj = $newdb->fetch_object($resql);
					$object->nbofusers	= $obj->nbofusers;
				}
				else $error++;
			}

			$deltatzserver=(getServerTimeZoneInt()-0)*3600;	// Diff between TZ of NLTechno and DoliCloud

			// Get last login of users
			if (! $error)
			{
				$sql="SELECT login, pass, datelastlogin FROM llx_user WHERE statut <> 0 AND login <> '".$conf->global->SELLYOURSAAS_LOGIN_FOR_SUPPORT."' ORDER BY datelastlogin DESC LIMIT 1";
				$resql=$newdb->query($sql);
				if ($resql)
				{
					$obj = $newdb->fetch_object($resql);

					$object->lastlogin  = $obj->login;
					$object->lastpass   = $obj->pass;
					$object->date_lastlogin = ($obj->datelastlogin ? ($newdb->jdate($obj->datelastlogin)+$deltatzserver) : '');
				}
				else
				{
					$error++;
					$errors[]='Failed to connect to database '.$instance.'.on.dolicloud.com'.' '.$username_db;
				}
			}

			$done++;
		}
		else
		{
			$errors[]='Failed to connect '.$conf->db->type.' '.$instance.'.on.dolicloud.com '.$username_db.' '.$password_db.' '.$database_db.' 3306';
			$ret=-1;
		}

		$newdb->close();

		if (! $error && $done)
		{
			$now=dol_now();
			$object->date_lastcheck=$now;
			$object->lastcheck=$now;	// For backward compatibility

			//$object->array_options['options_filelock']=$now;
			//$object->array_options['options_fileauthorizekey']=$now;
			//$object->array_options['options_latestresupdate_date']=$now;

			$result = $object->update($user);	// persist
			if (method_exists($object,'update_old')) $result = $object->update_old($user);	// persist

			if ($result < 0)
			{
				dol_syslog("Failed to persist data on object into database", LOG_ERR);
				if ($object->error) $errors[]=$object->error;
				$errors=array_merge($errors,$object->errors);
			}
			else
			{
				//var_dump($object);
			}
		}
	}
	else
	{
		$errors[]='Failed to connect '.$conf->db->type.' '.$server.' '.$username_db.' '.$password_db.' '.$database_db.' 3306';
		$ret=-1;
	}

	return $ret;
}


/**
 * Calculate stats ('total', 'totalcommissions', 'totalinstancespaying' (nbclients 'ACTIVE' not at trial), 'totalinstances' (nb clients not at trial, include suspended), 'totalusers')
 * at date datelim (or realtime if date is empty)
 *
 * Rem: Comptage des users par status
 * SELECT sum(im.value), c.status as customer_status, i.status as instance_status, s.payment_status
 * FROM app_instance as i LEFT JOIN app_instance_meter as im ON i.id = im.app_instance_id AND im.meter_id = 1, customer as c
 * LEFT JOIN channel_partner_customer as cc ON cc.customer_id = c.id LEFT JOIN channel_partner as cp ON cc.channel_partner_id = cp.id LEFT JOIN person as per ON c.primary_contact_id = per.id, subscription as s, plan as pl
 * LEFT JOIN plan_add_on as pao ON pl.id=pao.plan_id and pao.meter_id = 1, app_package as p
 * WHERE i.customer_id = c.id AND c.id = s.customer_id AND s.plan_id = pl.id AND pl.app_package_id = p.id AND s.payment_status NOT IN ('TRIAL', 'TRIALING', 'TRIAL_EXPIRED') AND i.deployed_date <= '20141201005959'
 * group by c.status,  i.status, s.payment_status
 * order by sum(im.value) desc
 *
 * @param	Database	$db			Database handler
 * @param	date		$datelim	Date limit
 * @return	array					Array of data
 */
function dolicloud_calculate_stats($db, $datelim)
{
	$total = $totalcommissions = $totalinstancespaying = $totalinstances = $totalusers = 0;
	$listofcustomers=array(); $listofcustomerspaying=array();

	$sql = "SELECT";
	$sql.= " i.id,";

	$sql.= " i.version,";
	$sql.= " i.app_package_id,";
	$sql.= " i.created_date as date_registration,";
	$sql.= " i.customer_id,";
	$sql.= " i.db_name,";
	$sql.= " i.db_password,";
	$sql.= " i.db_port,";
	$sql.= " i.db_server,";
	$sql.= " i.db_username,";
	$sql.= " i.default_password,";
	$sql.= " i.deployed_date,";
	$sql.= " i.domain_id,";
	$sql.= " i.fs_path,";
	$sql.= " i.install_time,";
	$sql.= " i.ip_address,";
	$sql.= " i.last_login as date_lastlogin,";
	$sql.= " i.last_updated,";
	$sql.= " i.name as instance,";
	$sql.= " i.os_password,";
	$sql.= " i.os_username,";
	$sql.= " i.rm_install_url,";
	$sql.= " i.rm_web_app_name,";
	$sql.= " i.status as instance_status,";
	$sql.= " i.undeployed_date,";
	$sql.= " i.access_enabled,";
	$sql.= " i.default_username,";
	$sql.= " i.ssh_port,";

	$sql.= " p.id as planid,";
	$sql.= " p.name as plan,";

	$sql.= " im.value as nbofusers,";
	$sql.= " im.last_updated as lastcheck,";

	$sql.= " pao.amount as price_user,";
	$sql.= " pao.min_threshold as min_threshold,";

	$sql.= " pl.amount as price_instance,";
	$sql.= " pl.meter_id as plan_meter_id,";
	$sql.= " pl.name as plan,";
	$sql.= " pl.interval_unit as interval_unit,";

	$sql.= " c.org_name as organization,";
	$sql.= " c.status as status,";
	$sql.= " c.past_due_start,";
	$sql.= " c.suspension_date,";

	$sql.= " s.payment_status,";
	$sql.= " s.status as subscription_status,";

	$sql.= " per.username as email,";
	$sql.= " per.first_name as firstname,";
	$sql.= " per.last_name as lastname,";

	$sql.= " cp.org_name as partner";

	$sql.= " FROM app_instance as i";
	$sql.= " LEFT JOIN app_instance_meter as im ON i.id = im.app_instance_id AND im.meter_id = 1,";	// meter_id = 1 = users
	$sql.= " customer as c";
	$sql.= " LEFT JOIN channel_partner_customer as cc ON cc.customer_id = c.id";
	$sql.= " LEFT JOIN channel_partner as cp ON cc.channel_partner_id = cp.id";
	$sql.= " LEFT JOIN person as per ON c.primary_contact_id = per.id,";
	$sql.= " subscription as s, plan as pl";
	$sql.= " LEFT JOIN plan_add_on as pao ON pl.id=pao.plan_id and pao.meter_id = 1,";	// meter_id = 1 = users
	$sql.= " app_package as p";
	$sql.= " WHERE i.customer_id = c.id AND c.id = s.customer_id AND s.plan_id = pl.id AND pl.app_package_id = p.id";
	$sql.= " AND s.payment_status NOT IN ('TRIAL', 'TRIALING', 'TRIAL_EXPIRED')";	// We keep OK, FAILURE, PAST_DUE. Filter on CLOSED will be done later.
	if ($datelim) $sql.= " AND i.deployed_date <= '".$db->idate($datelim)."'";

	dol_syslog($script_file." dolicloud_calculate_stats sql=".$sql, LOG_DEBUG);
	$resql=$db->query($sql);
	if ($resql)
	{
	    $num = $db->num_rows($resql);
	    $i = 0;
	    if ($num)
	    {
	        while ($i < $num)
	        {
	            $obj = $db->fetch_object($resql);
	            if ($obj)
	            {
    				//print "($obj->price_instance * ($obj->plan_meter_id == 1 ? $obj->nbofusers : 1)) + (max(0,($obj->nbofusers - ($obj->min_threshold ? $obj->min_threshold : 0))) * $obj->price_user)";
                    // Voir aussi dolicloud_list.php
                    $price=($obj->price_instance * ($obj->plan_meter_id == 1 ? $obj->nbofusers : 1)) + (max(0,($obj->nbofusers - ($obj->min_threshold ? $obj->min_threshold : 0))) * $obj->price_user);
                    if ($obj->interval_unit == 'Year') $price = $price / 12;

					$totalinstances++;
					$totalusers+=$obj->nbofusers;

					$activepaying=1;
					if (in_array($obj->status,array('SUSPENDED'))) $activepaying=0;
					if (in_array($obj->status,array('CLOSED','CLOSE_QUEUED','CLOSURE_REQUESTED')) || in_array($obj->instance_status,array('UNDEPLOYED'))) $activepaying=0;
					if (in_array($obj->payment_status,array('TRIAL','TRIALING','TRIAL_EXPIRED','FAILURE','PAST_DUE'))) $activepaying=0;

	                if (! $activepaying)
	                {
	                	$listofcustomers[$obj->customer_id]++;
						//print "cpt=".$totalinstances." customer_id=".$obj->customer_id." instance=".$obj->instance." status=".$obj->status." instance_status=".$obj->instance_status." payment_status=".$obj->payment_status." => Price = ".$obj->price_instance.' * '.($obj->plan_meter_id == 1 ? $obj->nbofusers : 1)." + ".max(0,($obj->nbofusers - $obj->min_threshold))." * ".$obj->price_user." = ".$price." -> 0<br>\n";
	                }
	                else
	                {
	              		$listofcustomerspaying[$obj->customer_id]++;

	                	$totalinstancespaying++;
	                	$total+=$price;

	                	//print "cpt=".$totalinstancespaying." customer_id=".$obj->customer_id." instance=".$obj->instance." status=".$obj->status." instance_status=".$obj->instance_status." payment_status=".$obj->payment_status." => Price = ".$obj->price_instance.' * '.($obj->plan_meter_id == 1 ? $obj->nbofusers : 1)." + ".max(0,($obj->nbofusers - $obj->min_threshold))." * ".$obj->price_user." = ".$price."<br>\n";
	                	if (! empty($obj->partner))
	                	{
	                		$totalcommissions+=price2num($price * 0.2);
	                	}
	                }
	            }
	            $i++;
	        }
	    }
	}
	else
	{
	    $error++;
	    dol_print_error($db);
	}

	return array('total'=>(double) $total, 'totalcommissions'=>(double) $totalcommissions,
				   'totalinstancespaying'=>(int) $totalinstancespaying,'totalinstances'=>(int) $totalinstances, 'totalusers'=>(int) $totalusers,
				   'totalcustomerspaying'=>(int) count($listofcustomerspaying), 'totalcustomers'=>(int) count($listofcustomers)
		);
}



/**
 * Calculate stats ('total', 'totalcommissions', 'totalinstancespaying' (nbclients 'ACTIVE' not at trial), 'totalinstances' (nb clients not at trial, include suspended), 'totalusers')
 * at date datelim (or realtime if date is empty)
 *
 * Rem: Comptage des users par status
 *
 * @param	Database	$db			Database handler
 * @param	date		$datelim	Date limit
 * @return	array					Array of data
 */
function sellyoursaas_calculate_stats($db, $datelim)
{
	$total = $totalcommissions = $totalinstancespaying = $totalinstances = $totalusers = 0;
	$listofcustomers=array(); $listofcustomerspaying=array();

	// Get list of instance
	$sql = "SELECT c.rowid as id, c.ref_customer as instance, c.fk_soc as customer_id,";
	$sql.= " ce.deployment_status as instance_status";
	$sql.= " FROM ".MAIN_DB_PREFIX."contrat as c LEFT JOIN ".MAIN_DB_PREFIX."contrat_extrafields as ce ON c.rowid = ce.fk_object";
	$sql.= " WHERE c.ref_customer <> '' AND c.ref_customer IS NOT NULL";
	$sql.= " AND ce.deployment_status IS NOT NULL";
	//$sql.= " AND s.payment_status NOT IN ('TRIAL', 'TRIALING', 'TRIAL_EXPIRED')";	// We keep OK, FAILURE, PAST_DUE
	if ($datelim) $sql.= " AND ce.deployment_date_end <= '".$db->idate($datelim)."'";

	dol_syslog("sellyoursaas_calculate_stats sql=".$sql, LOG_DEBUG, 1);
	$resql=$db->query($sql);
	if ($resql)
	{
		$num = $db->num_rows($resql);
		$i = 0;
		if ($num)
		{
			include_once DOL_DOCUMENT_ROOT.'/contrat/class/contrat.class.php';
			dol_include_once('/sellyoursaas/lib/sellyoursaas.lib.php');

			$object = new Contrat($db);

			while ($i < $num)
			{
				$obj = $db->fetch_object($resql);
				if ($obj)
				{
					unset($object->linkedObjects);

					// Get resource for instance
					$object->fetch($obj->id);

					$tmpdata = sellyoursaasGetExpirationDate($object);
					$nbofuser = $tmpdata['nbusers'];

					$totalinstances++;
					$totalusers+=$nbofuser;

					/*$activepaying=1;
					if (in_array($obj->status,array('SUSPENDED'))) $activepaying=0;
					if (in_array($obj->status,array('CLOSED','CLOSE_QUEUED','CLOSURE_REQUESTED')) || in_array($obj->instance_status,array('UNDEPLOYED'))) $activepaying=0;
					if (in_array($obj->payment_status,array('TRIAL','TRIALING','TRIAL_EXPIRED','FAILURE','PAST_DUE'))) $activepaying=0;*/

					$ispaid = sellyoursaasIsPaidInstance($object);		// This also load $object->linkedObjects['facturerec']

					if (! $ispaid)
					{
						$listofcustomers[$obj->customer_id]++;
						//print "cpt=".$totalinstances." customer_id=".$obj->customer_id." instance=".$obj->instance." status=".$obj->status." instance_status=".$obj->instance_status." payment_status=".$obj->payment_status." => Price = ".$obj->price_instance.' * '.($obj->plan_meter_id == 1 ? $obj->nbofusers : 1)." + ".max(0,($obj->nbofusers - $obj->min_threshold))." * ".$obj->price_user." = ".$price." -> 0<br>\n";
					}
					else
					{
						$listofcustomerspaying[$obj->customer_id]++;

						// Calculate price on invoicing
						$price = 0;
						//var_dump(count($object->linkedObjects));
						//$object->fetchObjectLinked();
						if (is_array($object->linkedObjects['facturerec']))
						{
							foreach($object->linkedObjects['facturerec'] as $idtemplateinvoice => $templateinvoice)
							{
								if (! $templateinvoice->suspended)
								{
									if ($templateinvoice->unit_frequency == 'm' && $templateinvoice->frequency == 1)
									{
										$price += $templateinvoice->total_ht;
									}
									elseif ($templateinvoice->unit_frequency == 'y' && $templateinvoice->frequency == 1)
									{
										$price += ($templateinvoice->total_ht / 12);
									}
									else
									{
										$price += $templateinvoice->total_ht;
									}
								}
							}
						}

						$totalinstancespaying++;
						$total+=$price;

						//print "cpt=".$totalinstancespaying." customer_id=".$obj->customer_id." instance=".$obj->instance." status=".$obj->status." instance_status=".$obj->instance_status." payment_status=".$obj->payment_status." => Price = ".$obj->price_instance.' * '.($obj->plan_meter_id == 1 ? $obj->nbofusers : 1)." + ".max(0,($obj->nbofusers - $obj->min_threshold))." * ".$obj->price_user." = ".$price."<br>\n";
						if (! empty($object->parent))
						{
							$totalcommissions+=price2num($price * $object->thirdparty->array_options['commission'] / 100);
						}
					}
				}
				$i++;
			}
		}
	}
	else
	{
		$error++;
		dol_print_error($db);
	}

	dol_syslog("sellyoursaas_calculate_stats end", LOG_DEBUG, -1);

	return array(
		'total'=>(double) $total, 'totalcommissions'=>(double) $totalcommissions,
		'totalinstancespaying'=>(int) $totalinstancespaying,'totalinstances'=>(int) $totalinstances, 'totalusers'=>(int) $totalusers,
		'totalcustomerspaying'=>(int) count($listofcustomerspaying), 'totalcustomers'=>(int) count($listofcustomers)
	);
}
