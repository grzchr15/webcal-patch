<?php

defined ( '_ISVALID' ) or die ( 'You cannot access this file directly!' );

/************************* Config ***********************************/

// Location of postnuke config.php file (with trailing slash)
$app_path = dirname(__FILE__)."/../";

// URL to wordpress (with trailing slash)
$app_url = 'http://'.$_SERVER['SERVER_NAME'].'/';

require_once( dirname(__FILE__)."/user-wordpress-config.php");
require_once( dirname(__FILE__)."/../../".'wp-includes/class-phpass.php');



/**
 * Authentication functions.
 *
 * This file contains all the functions for getting information about users.
 * So, if you want to use an authentication scheme other than the webcal_user
 * table, you can just create a new version of each function found below.
 *
 * <b>Note:</b> this application assumes that usernames (logins) are unique.
 *
 * <b>Note #2:</b> If you are using HTTP-based authentication, then you still
 * need these functions and you will still need to add users to webcal_user.
 *
 * @author Christian Bretterhofer <christian.bretterhofer@gmail.com>
 * @copyright Christian Bretterhofer <christian.bretterhofer@gmail.com>, Craig Knudsen, <cknudsen@cknudsen.com>, http://www.k5n.us/cknudsen
 * @license http://www.gnu.org/licenses/gpl.html GNU GPL
 * @version $Id: user_wordpress.php
 * @package WebCalendar
 * @subpackage Authentication
 */
defined ( '_ISVALID' ) or die ( 'You cannot access this file directly!' );

// Set some global config variables about your system.
$user_can_update_password = true;
$admin_can_add_user = true;
$admin_can_delete_user = true;
$admin_can_disable_user = false;

/**
 * Check to see if a given login/password is valid.
 *
 * If invalid, the error message will be placed in $error.
 *
 * @param string $login    User login
 * @param string $password User password
 * @param bool $#silent  if truem do not return any $error
 *
 * @return bool True on success
 *
 * @global string Error message
 */

function user_valid_login ( $login, $password, $silent=false ) {
	global $error;
	global $app_host, $app_login, $app_pass, $app_db, $app_same_db;
	global $c, $db_host, $db_login, $db_password, $db_database;

	// if wordpress is in a separate db, we have to connect to it
	if ($app_same_db != '1') $c = dbi_connect($app_host, $app_login, $app_pass, $app_db);

	$ret = false;

	//  $msg='user-wordpress user_valid_login: '.$login.' '.$password;
	//  error_log($msg,3,bre_debug_file);
	$wp_hasher = new PasswordHash(8, TRUE);

	// OLD $hash = md5 ( $password );
	//$hash = $wp_hasher->HashPassword($password);
	//

	$sql = 'SELECT user_login,user_pass FROM wp_users WHERE user_login = ? ';
	$res = dbi_execute ( $sql, array ( $login ) );
	if ( $res ) {
		$row = dbi_fetch_row ( $res );
		if ( $row && $row[0] != '' ) {
			//      error_log(" user-wordpress user_valid_login:" .bre_var_dump("row",$row),3,bre_debug_file);
			 
			$user_login=$row[0];
			$user_pass=$row[1];
			// MySQL seems to do case insensitive matching, so double-check the login.
			if ( $row[0] == $login )
			$ret = true; // found login
			else if ( ! $silent )
			$error = translate ( 'Invalid login', true ) . ': ' .
			translate ( 'incorrect password', true );
		} else if ( ! $silent ) {
			$error = translate ( 'Invalid login', true );
			// Could be no such user or bad password
			// Check if user exists, so we can tell.
			$res2 = dbi_execute ( 'SELECT user_login,user_pass  FROM wp_users
        WHERE user_login = ?', array ( $login ) );
			if ( $res2 ) {
				$row = dbi_fetch_row ( $res2 );
				if ( $row && ! empty ( $row[0] ) ) {
					// got a valid username, but wrong password
					$error = translate ( 'Invalid login', true ) . ': ' .
					translate ( 'incorrect password', true );
				} else {
					// No such user.
					$error = translate ( 'Invalid login', true) . ': ' .
					translate ( 'no such user', true );
				}
				dbi_free_result ( $res2 );
			}
		}

		dbi_free_result ( $res );
	} else if ( ! $silent ) {
		$error = db_error ();
	}
	// Password checking needed extra
	$hash=$user_pass;
	$ret=$wp_hasher->CheckPassword($password, $hash);
	//error_log(" user-wordpress user_valid_login:" . $hash. " ". $password,3,bre_debug_file);

	// if wordpress is in a separate db, we have to connect back to the webcal db
	if ($app_same_db != '1') $c = dbi_connect($db_host, $db_login, $db_password, $db_database);

	return $ret;
}

/**
 * Check to see if a given login/crypted password is valid.
 *
 * If invalid, the error message will be placed in $error.
 *
 * @param string $login          User login
 * @param string $crypt_password Encrypted user password
 *
 * @return bool True on success
 *
 * @global string Error message
 */
function user_valid_crypt ( $login, $crypt_password ) {
	global $error;
	global $app_host, $app_login, $app_pass, $app_db, $app_same_db;
	global $c, $db_host, $db_login, $db_password, $db_database;

	// if wordpress is in a separate db, we have to connect to it
	if ($app_same_db != '1') $c = dbi_connect($app_host, $app_login, $app_pass, $app_db);
	$ret = false;

	//error_log(" user-wordpress user_valid_crypt:" . $login. " ". $crypt_password,3,bre_debug_file);

	$sql = 'SELECT user_login, user_pass FROM wp_users WHERE user_login = ?';
	$res = dbi_execute ( $sql, array ( $login ) );
	if ( $res ) {
		$row = dbi_fetch_row ( $res );
		if ( $row && $row[0] != '' ) {
			// MySQL seems to do case insensitive matching, so double-check
			// the login.
			// also check if password matches
			if ( ($row[0] == $login) && ( (crypt($row[1], $crypt_password) == $crypt_password) ) )
			$ret = true; // found login/password
			else
			$error = 'Invalid login';
		} else {
			$error = 'Invalid login';
		}
		dbi_free_result ( $res );
	} else {
		$error = 'Database error: ' . dbi_error ();
	}
	// if wordpress is in a separate db, we have to connect back to the webcal db
	if ($app_same_db != '1') $c = dbi_connect($db_host, $db_login, $db_password, $db_database);

	return $ret;
}

/**
 * Load info about a user (first name, last name, admin) and set globally.
 *
 * @param string $user User login
 * @param string $prefix Variable prefix to use
 *
 * @return bool True on success
 */
/**
 * Add user meta data as properties to given user object.
 *
 * The finished user data is cached, but the cache is not used to fill in the
 * user data for the given object. Once the function has been used, the cache
 * should be used to retrieve user data. The purpose seems then to be to ensure
 * that the data in the object is always fresh.
 *
 * @access private
 * @since 2.5.0
 * @uses $wpdb WordPress database object for queries
 *
 * @param object $user The user data object.
 */
function _fill_user( &$user ) {
	global $app_host, $app_login, $app_pass, $app_db, $app_same_db;
	global $c, $db_host, $db_login, $db_password, $db_database;

	// if wordpress is in a separate db, we have to connect to it
	if ($app_same_db != '1') $c = dbi_connect($app_host, $app_login, $app_pass, $app_db);

	//error_log(" user-wordpress _fill_user:" . bre_var_dump("user=",$user),3,bre_debug_file);

	$sql ="SELECT meta_key, meta_value FROM $wpdb->usermeta WHERE user_id = ?";
	$metavalues = dbi_get_cached_rows ( $sql, array ( $user->ID ) );
	if ( $metavalues ) {
		foreach ( (array) $metavalues as $meta ) {
			$meta_key =$metavalues[0];
			$meta_value=$metavalues[1];
			$value = maybe_unserialize($meta_value);
			//$value = maybe_unserialize($meta->meta_value);
			//$user->{$meta->meta_key} = $value;
			$user->{$meta_key} = $value;
		}
	}

	$level = $wpdb->prefix . 'user_level';
	if ( isset( $user->{$level} ) )
	$user->user_level = $user->{$level};

	// For backwards compat.
	if ( isset($user->first_name) )
	$user->user_firstname = $user->first_name;
	if ( isset($user->last_name) )
	$user->user_lastname = $user->last_name;
	if ( isset($user->description) )
	$user->user_description = $user->description;

	wp_cache_add($user->ID, $user, 'users');
	wp_cache_add($user->user_login, $user->ID, 'userlogins');
	wp_cache_add($user->user_email, $user->ID, 'useremail');
	wp_cache_add($user->user_nicename, $user->ID, 'userslugs');

	// if wordpress is in a separate db, we have to connect back to the webcal db
	if ($app_same_db != '1') $c = dbi_connect($db_host, $db_login, $db_password, $db_database);


}
/**
 * Unserialize value only if it was serialized.
 *
 * @since 2.0.0
 *
 * @param string $original Maybe unserialized original, if is needed.
 * @return mixed Unserialized data can be any type.
 */
function maybe_unserialize( $original ) {
	if ( is_serialized( $original ) ) // don't attempt to unserialize data that wasn't serialized going in
	return @unserialize( $original );
	return $original;
}
/**
 * Check value to find if it was serialized.
 *
 * If $data is not an string, then returned value will always be false.
 * Serialized data is always a string.
 *
 * @since 2.0.5
 *
 * @param mixed $data Value to check to see if was serialized.
 * @return bool False if not serialized and true if it was.
 */
function is_serialized( $data ) {
	// if it isn't a string, it isn't serialized
	if ( !is_string( $data ) )
	return false;
	$data = trim( $data );
	if ( 'N;' == $data )
	return true;
	if ( !preg_match( '/^([adObis]):/', $data, $badions ) )
	return false;
	switch ( $badions[1] ) {
		case 'a' :
		case 'O' :
		case 's' :
			if ( preg_match( "/^{$badions[1]}:[0-9]+:.*[;}]\$/s", $data ) )
			return true;
			break;
		case 'b' :
		case 'i' :
		case 'd' :
			if ( preg_match( "/^{$badions[1]}:[0-9.E-]+;\$/", $data ) )
			return true;
			break;
	}
	return false;
}

/**
 * Check whether serialized data is of string type.
 *
 * @since 2.0.5
 *
 * @param mixed $data Serialized data
 * @return bool False if not a serialized string, true if it is.
 */
function is_serialized_string( $data ) {
	// if it isn't a string, it isn't a serialized string
	if ( !is_string( $data ) )
	return false;
	$data = trim( $data );
	if ( preg_match( '/^s:[0-9]+:.*;$/s', $data ) ) // this should fetch all serialized strings
	return true;
	return false;
}

function user_load_variables ( $login, $prefix ) {
	global $PUBLIC_ACCESS_FULLNAME, $NONUSER_PREFIX, $cached_user_var, $SCRIPT;
	global $app_host, $app_login, $app_pass, $app_db, $app_same_db;
	global $c, $db_host, $db_login, $db_password, $db_database;

	$ret = false;

	//error_log("\nuser-wordpress user_load_variables-:" . " login=".$login." prefix=". $prefix,3,bre_debug_file);


	if ( ! empty ( $cached_user_var[$login][$prefix] ) ){
		
		return  $cached_user_var[$login][$prefix];
	}
	// if wordpress is in a separate db, we have to connect to it
	if ($app_same_db != '1') $c = dbi_connect($app_host, $app_login, $app_pass, $app_db);
	
	$cached_user_var = array ();

	//help prevent spoofed username attempts from disclosing fullpath
	$GLOBALS[$prefix . 'fullname'] = '';
	if ($NONUSER_PREFIX && substr ($login, 0, strlen ($NONUSER_PREFIX) ) == $NONUSER_PREFIX) {
		nonuser_load_variables ( $login, $prefix );

		// if wordpress is in a separate db, we have to connect back to the webcal db
		if ($app_same_db != '1') $c = dbi_connect($db_host, $db_login, $db_password, $db_database);

		return true;
	}
	if ( $login == '__public__' || $login == '__default__' ) {
		$GLOBALS[$prefix . 'login'] = $login;
		$GLOBALS[$prefix . 'firstname'] = '';
		$GLOBALS[$prefix . 'lastname'] = '';
		$GLOBALS[$prefix . 'is_admin'] = 'N';
		$GLOBALS[$prefix . 'email'] = '';
		$GLOBALS[$prefix . 'fullname'] = ( $login == '__public__'?
		$PUBLIC_ACCESS_FULLNAME : translate ( 'DEFAULT CONFIGURATION' ) );
		$GLOBALS[$prefix . 'password'] = '';

		// if wordpress is in a separate db, we have to connect back to the webcal db
		if ($app_same_db != '1') $c = dbi_connect($db_host, $db_login, $db_password, $db_database);

		return true;
	}

	$sql =
    'SELECT user_nicename, display_name,  user_email, user_pass, ' .
    ' id FROM wp_users WHERE user_login = ?';
	//error_log("\n user-wordpress user_load_variables-1-:" ."sql=".$sql,3,bre_debug_file);
	$rows = dbi_get_cached_rows ( $sql, array ( $login ) );
	if ( $rows ) {
		//error_log("\n user-wordpress user_load_variables--:" .bre_var_dump("rows",$rows),3,bre_debug_file);
		 
		$row = $rows[0];
		$GLOBALS[$prefix . 'login'] = $login;
		$GLOBALS[$prefix . 'firstname'] = $row[0];
		$GLOBALS[$prefix . 'lastname'] = $row[1];
		$GLOBALS[$prefix . 'email'] = empty ( $row[2] ) ? '' : $row[2];
		/*
		if ( strlen ( $row[1] ) ) {
			$GLOBALS[$prefix . 'fullname'] = "$row[1]";
		} else {
			if ( strlen ( $row[0] ) )
				$GLOBALS[$prefix . 'fullname'] = "$row[0]";
			else
				$GLOBALS[$prefix . 'fullname'] = $login;
		}
		 */
		$GLOBALS[$prefix . 'fullname'] = "$row[1]";
		if ( strlen ( $row[0] )>0 )
				$GLOBALS[$prefix . 'fullname'] .= " $row[0]";
		$GLOBALS[$prefix . 'password'] = $row[3];
		$GLOBALS[$prefix . 'id'] = $row[4];

		$ret = true;
	} else {

		// if wordpress is in a separate db, we have to connect back to the webcal db
		if ($app_same_db != '1') $c = dbi_connect($db_host, $db_login, $db_password, $db_database);

		return false;
	}
	$is_admin= 'N';
	$sql ="SELECT meta_key, meta_value FROM wp_usermeta WHERE user_id = ?";
	$metavalues = dbi_get_cached_rows ( $sql, array ( $GLOBALS[$prefix . 'id'] ) );
	if ( $metavalues ) {
		//    error_log("\nuser-wordpress user_load_variables---:" .bre_var_dump("metavalues",$metavalues),3,bre_debug_file);
		foreach ( $metavalues as $i => $meta ) {
			list($meta_key, $meta_value) = $meta;
			$value = maybe_unserialize($meta_value);

			//$value = maybe_unserialize($meta->meta_value);
			//$user->{$meta->meta_key} = $value;
			$GLOBALS[$prefix . $meta_key] = $value;
			//error_log("\nuser-wordpress user_load_variables: ".$i."/".$cnt."id=".$GLOBALS[$prefix . 'id']. " ". bre_var_dump("meta_key",$meta_key)."=".bre_var_dump("value",$value),3,bre_debug_file);
			//error_log("\nuser-wordpress user_load_variables: ".$i."/".$cnt."id=".$GLOBALS[$prefix . 'id']. " ". "meta_key=".$meta_key." "."value=".$value." meta_value=".$meta_value,3,bre_debug_file);
			if( $meta_key == 'first_name'){
				$GLOBALS[$prefix . 'firstname'] = $value;
			}
			if( $meta_key == 'last_name'){
				$GLOBALS[$prefix . 'lastname'] = $value;
			}
			if(  $meta_key == 'wp_user_level' ){
				if(  $value >= 7 ) {
					$is_admin = 'Y';
				}  else  {
					$is_admin= 'N';
				}
			}
		}
	}

	//  error_log(" user-wordpress user_valid_login: xxxx".$prefix . 'user_level'."=" .$GLOBALS[$prefix . 'user_level'],3,bre_debug_file);

	$GLOBALS[$prefix . 'is_admin'] =$is_admin;
	//error_log("\nuser-wordpress user_load_variables: is_admin=".$GLOBALS[$prefix . 'is_admin'],3,bre_debug_file);
	$GLOBALS[$prefix . 'enabled'] = 'Y';
	//save these results
	$cached_user_var[$login][$prefix] = $ret;

	// if wordpress is in a separate db, we have to connect back to the webcal db
	if ($app_same_db != '1') $c = dbi_connect($db_host, $db_login, $db_password, $db_database);

	return $ret;
}

/**
 * Add a new user.
 *
 * @param string $user      User login
 * @param string $password  User password
 * @param string $firstname User first name
 * @param string $lastname  User last name
 * @param string $email     User email address
 * @param string $admin     Is the user an administrator? ('Y' or 'N')
 *
 * @return bool True on success
 *
 * @global string Error message
 */
function user_add_user ( $user, $password, $firstname,
$lastname, $email, $admin, $enabled='Y' ) {
	global $error;

	//error_log(" user-wordpress user_add_user:" . " user=".$user." password=". $password,3,bre_debug_file);


	if ( $user == '__public__' ) {
		$error = translate ( 'Invalid user login', true);
		return false;
	}
	return false;
}

/**
 * Update a user.
 *
 * @param string $user      User login
 * @param string $firstname User first name
 * @param string $lastname  User last name
 * @param string $mail      User email address
 * @param string $admin     Is the user an administrator? ('Y' or 'N')
 * @param string $enabled   Is the user account enabled? ('Y' or 'N')
 *
 * @return bool True on success
 *
 * @global string Error message
 */
function user_update_user ( $user, $firstname, $lastname, $email,
$admin, $enabled='Y' ) {
	global $error;

	//error_log(" user-wordpress user_update_user:" . " user=".$user." email=". $email,3,bre_debug_file);


	if ( $user == '__public__' ) {
		$error = translate ( 'Invalid user login' );
		return false;
	}
	return false;
}

/**
 * Update user password.
 *
 * @param string $user     User login
 * @param string $password User password
 *
 * @return bool True on success
 *
 * @global string Error message
 */
function user_update_user_password ( $user, $password ) {
	global $error;
	//error_log(" user-wordpress user_update_user_password:" . " user=".$user." password=". $password,3,bre_debug_file);

	return false;
}

/**
 * Delete a user from the system.
 *
 * This will also delete any of the user's events in the system that have
 * no other participants. Any layers that point to this user
 * will be deleted. Any views that include this user will be updated.
 *
 * @param string $user User to delete
 */
function user_delete_user ( $user ) {
	global $app_host, $app_login, $app_pass, $app_db, $app_same_db;
	global $c, $db_host, $db_login, $db_password, $db_database;

	// if wordpress is in a separate db, we have to connect to it
	if ($app_same_db != '1') $c = dbi_connect($app_host, $app_login, $app_pass, $app_db);

	//error_log(" user-wordpress user_delete_user:" . " user=".$user,3,bre_debug_file);


	// Get event ids for all events this user is a participant
	$events = get_users_event_ids ( $user );

	// Now count number of participants in each event...
	// If just 1, then save id to be deleted
	$delete_em = array ();
	$evcnt = count ( $events );
	foreach ( $events as $x ) {
		$res = dbi_execute ( 'SELECT COUNT(*) FROM webcal_entry_user ' .
      'WHERE cal_id = ?', array ( $x ) );
		if ( $res ) {
			if ( $row = dbi_fetch_row ( $res ) ) {
				if ( $row[0] == 1 )
				$delete_em[] = $x;
			}
			dbi_free_result ( $res );
		}
	}
	$delete_emcnt = count ( $delete_em );
	// Now delete events that were just for this user
	foreach ( $delete_em as $x ) {
		dbi_execute ( 'DELETE FROM webcal_entry_repeats WHERE cal_id = ?',
		array ( $x ) );
		dbi_execute ( 'DELETE FROM webcal_entry_repeats_not WHERE cal_id = ?',
		array ( $x ) );
		dbi_execute ( 'DELETE FROM webcal_entry_log WHERE cal_entry_id = ?',
		array ( $x )  );
		dbi_execute ( 'DELETE FROM webcal_import_data WHERE cal_id = ?',
		array ( $x )  );
		dbi_execute ( 'DELETE FROM webcal_site_extras WHERE cal_id = ?',
		array ( $x )  );
		dbi_execute ( 'DELETE FROM webcal_entry_ext_user WHERE cal_id = ?',
		array ( $x )  );
		dbi_execute ( 'DELETE FROM webcal_reminders WHERE cal_id = ?',
		array ( $x )  );
		dbi_execute ( 'DELETE FROM webcal_blob WHERE cal_id = ?',
		array ( $x )  );
		dbi_execute ( 'DELETE FROM webcal_entry WHERE cal_id = ?',
		array ( $x )  );
	}

	// Delete user participation from events
	dbi_execute ( 'DELETE FROM webcal_entry_user WHERE user_login = ?',
	array ( $user ) );
	// Delete preferences
	dbi_execute ( 'DELETE FROM wp_users_pref WHERE user_login = ?',
	array ( $user ) );
	// Delete from groups
	dbi_execute ( 'DELETE FROM webcal_group_user WHERE user_login = ?',
	array ( $user ) );
	// Delete bosses & assistants
	dbi_execute ( 'DELETE FROM webcal_asst WHERE cal_boss = ?',
	array ( $user ) );
	dbi_execute ( 'DELETE FROM webcal_asst WHERE cal_assistant = ?',
	array ( $user ) );
	// Delete user's views
	$delete_em = array ();
	$res = dbi_execute ( 'SELECT cal_view_id FROM webcal_view WHERE cal_owner = ?',
	array ( $user ) );
	if ( $res ) {
		while ( $row = dbi_fetch_row ( $res ) ) {
			$delete_em[] = $row[0];
		}
		dbi_free_result ( $res );
	}
	$delete_emcnt = count ( $delete_em );
	foreach ( $delete_em as $x  ) {
		dbi_execute ( 'DELETE FROM webcal_view_user WHERE cal_view_id = ?',
		array ( $x ) );
	}
	dbi_execute ( 'DELETE FROM webcal_view WHERE cal_owner = ?',
	array ( $user ) );
	//Delete them from any other user's views
	dbi_execute ( 'DELETE FROM webcal_view_user WHERE user_login = ?',
	array ( $user ) );
	// Delete layers
	dbi_execute ( 'DELETE FROM wp_users_layers WHERE user_login = ?',
	array ( $user ) );
	// Delete any layers other users may have that point to this user.
	dbi_execute ( 'DELETE FROM wp_users_layers WHERE cal_layeruser = ?',
	array ( $user ) );
	// Delete user
	dbi_execute ( 'DELETE FROM wp_users WHERE user_login = ?',
	array ( $user ) );
	// Delete function access
	dbi_execute ( 'DELETE FROM webcal_access_function WHERE user_login = ?',
	array ( $user ) );
	// Delete user access
	dbi_execute ( 'DELETE FROM webcal_access_user WHERE user_login = ?',
	array ( $user ) );
	dbi_execute ( 'DELETE FROM webcal_access_user WHERE cal_other_user = ?',
	array ( $user ) );
	// Delete user's categories
	dbi_execute ( 'DELETE FROM webcal_categories WHERE cat_owner = ?',
	array ( $user ) );
	dbi_execute ( 'DELETE FROM webcal_entry_categories WHERE cat_owner = ?',
	array ( $user ) );
	// Delete user's reports
	$delete_em = array ();
	$res = dbi_execute ( 'SELECT cal_report_id FROM webcal_report WHERE user_login = ?',
	array ( $user ) );
	if ( $res ) {
		while ( $row = dbi_fetch_row ( $res ) ) {
			$delete_em[] = $row[0];
		}
		dbi_free_result ( $res );
	}
	$delete_emcnt = count ( $delete_em );
	foreach ( $delete_em as $x ) {
		dbi_execute ( 'DELETE FROM webcal_report_template WHERE cal_report_id = ?',
		array ( $x ) );
	}
	dbi_execute ( 'DELETE FROM webcal_report WHERE user_login = ?',
	array ( $user ) );
	//not sure about this one???
	dbi_execute ( 'DELETE FROM webcal_report WHERE cal_user = ?',
	array ( $user ) );
	// Delete user templates
	dbi_execute ( 'DELETE FROM wp_users_template WHERE user_login = ?',
	array ( $user ) );

	// if wordpress is in a separate db, we have to connect back to the webcal db
	if ($app_same_db != '1') $c = dbi_connect($db_host, $db_login, $db_password, $db_database);

}

/**
 * Get a list of users and return info in an array.
 *
 * @param bool  $publicOnly  return only public data
 * @return array Array of user info
 */
function user_get_users ( $publicOnly=false ) {
	global $PUBLIC_ACCESS, $PUBLIC_ACCESS_FULLNAME,$USER_SORT_ORDER;
	global $app_host, $app_login, $app_pass, $app_db, $app_same_db;
	global $c, $db_host, $db_login, $db_password, $db_database;

	// if wordpress is in a separate db, we have to connect to it
	if ($app_same_db != '1') $c = dbi_connect($app_host, $app_login, $app_pass, $app_db);


	//error_log("\nuser-wordpress user_get_users-:" . " publicOnly=".$publicOnly,3,bre_debug_file);


	$ret = array ();
	if ( $PUBLIC_ACCESS == 'Y' )
	$ret[] = array (
       'cal_login' => '__public__',
       'cal_lastname' => '',
       'cal_firstname' => '',
       'cal_is_admin' => 'N',
       'cal_email' => '',
       'cal_password' => '',
       'cal_fullname' => $PUBLIC_ACCESS_FULLNAME );
	if ( $publicOnly ) {
		if ($app_same_db != '1') $c = dbi_connect($db_host, $db_login, $db_password, $db_database);

		return $ret;
	}

	$order1 = 'user_nicename, display_name,' ;
	$res = dbi_execute ( 'SELECT user_login, user_nicename, display_name, ' .
    ' id, user_email, user_pass FROM wp_users ' .
    "ORDER BY $order1 user_login" );
	if ( $res ) {
		while ( $row = dbi_fetch_row ( $res ) ) {
			if ( strlen ( $row[1] ) && strlen ( $row[2] ) )
			$fullname = ( $order1 == 'user_nicename, display_name,' ?
           "$row[1] $row[2]" : "$row[2] $row[1]" );
			else
			$fullname = $row[0];
			$id=$row[3];
			$sql ="SELECT meta_key, meta_value FROM wp_usermeta WHERE user_id = ?";
			$metavalues = dbi_get_cached_rows ( $sql, array ( $id ) );
			$is_admin='N';
			if ( $metavalues ) {
				foreach ( $metavalues as $meta ) {
					list($meta_key, $meta_value) = $meta;
					$value = maybe_unserialize($meta_value);
					//$user->{$meta->meta_key} = $value;
					if(  $meta_key == 'wp_user_level' ){
						if(  $value >= 7 ) {
							$is_admin = 'Y';
						}  else  {
							$is_admin= 'N';
						}
						//error_log("\n user-wordpress user_get_users: id=".$id. " ". $meta_key."=".$value." is_admin=".$is_admin,3,bre_debug_file);
					}else{
						//error_log("\n user-wordpress user_get_users: id=".$id. " ". $meta_key."=".$value,3,bre_debug_file);
					}
				}
			}
			$ret[] = array (
        'cal_login' => $row[0],
        'cal_lastname' => $row[1],
        'cal_firstname' => $row[2],
        'cal_is_admin' => $is_admin,
        'cal_email' => empty ( $row[4] ) ? '' : $row[4],
        'cal_password' => $row[5],
        'cal_fullname' => $fullname
			);
		}
		dbi_free_result ( $res );
	}

	//no need to call sort_users () as the sql can sort for us
	// if wordpress is in a separate db, we have to connect back to the webcal db
	if ($app_same_db != '1') $c = dbi_connect($db_host, $db_login, $db_password, $db_database);

	return $ret;
}
?>
