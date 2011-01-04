<?php
/* Lists a user's reports or displays a specific report.
 *
 * Input Parameters:
 * - <var>report_id</var> (optional) - specified report id in webcal_report table
 * - <var>offset</var> (optional) - specifies how many days/weeks/months +/- to
 *   display. For example, if the report type is 1 (today) with offset=5, then
 *   the report will display 5 days from now. Should only be specified if
 *   report_id is specified. Will be ignored if specified report does not have
 *   the webcal_report.cal_allow_nav field set to 'Y'.
 * - <var>user</var> (optional) - specifies which user's calendar to use for
 *   the report. This will be ignored if the chosen report is tied to a
 *   specific user.
 *
 * @author Craig Knudsen <cknudsen@cknudsen.com>
 * @copyright Craig Knudsen, <cknudsen@cknudsen.com>, http://www.k5n.us/cknudsen
 * @license http://www.gnu.org/licenses/gpl.html GNU GPL
 * @version $Id: report.php,v 1.77.2.4 2008/03/31 19:28:37 umcesrjones Exp $
 * @package WebCalendar
 * @subpackage Reports
 */

/* Security:
 * If system setting $REPORTS_ENABLED is set to anything other than 'Y',
 * then don't allow access to this page.
 * If webcal_report.cal_is_global is set to:
 *   'Y', any user can view the report.
 *   'N', only the creator (set in webcal_report.cal_login) can view the report.
 * If webcal_report.cal_allow_nav is:
 *   'Y', then present Next and Previous links.
 *   'N', then no Next / Previous links and the offset parameter will be ignored.
 * Public user cannot edit/list reports.
 */
ob_start();
include_once 'includes/init.php';

/* Replaces all site_extras placeholders in a template with the actual data.
 *
 * All occurences of '${extra:ExtraName}' (where 'ExtraName' is the unique name
 * of a site_extra) will be replaced with that extra's data.
 *
 * @param string $template The template
 * @param array  $extras   The formatted site_extras as returned by
 *                         {@link format_site_extras ()}
 *
 * @return string The template with site_extras replaced
 */
function priv_argecal_taglib($param1,$param2,$str,$opt){
	FB::log("param1=".$param1);
	FB::log("param2=".$param2);
	$ret="";
	switch($param2)
	{
		case ",":
			break;
		case " <br/>":
			break;
		case " <br />":
			break;
		case " <br>":
			break;
		case " <br >":
			break;
		default:
			$ret=$param1.': '.$param2;
			break;
	}
	if($ret<>""){
		switch($param1)
		{
			case "Nummer":
				$ret=$param2;
				break;
			case "PLZ":
				$ret=$param2;
				break;
			default:
				$ret=$param1.': '.$param2;
				break;
		}
	}
	//$ret="XXX";
	FB::log("ret=".$ret);
	return $ret;
}
function replace_site_extras_in_template ( $template, $extras ) {
	$extra_names = get_site_extras_names ();

	$ret = $template;
	//FB::log("replace_site_extras_in_template start=".$ret);
	foreach ( $extra_names as $extra_name ) {

		FB::log($extra_name.':',$extras[$extra_name]['data']);
		//error_log("",)
		switch($extra_name)
		{
			case "":
				break;
			default:
				$ret = str_replace ( '${extra:' . $extra_name . '}',
				( empty ( $extras[$extra_name] ) ? '' : $extras[$extra_name]['data'] ), $ret );
				break;
		}


	}

	$address=$extras['street']['data'].' '.$extras['number']['data'];
	if(strlen($address) > 2 )$address='<div class="street-address">'.$address.'</div>';

	if(strlen($extras['PLZ']['data']) > 2 )$address.='<span class="postal-code">'.$extras['PLZ']['data'].'</span> ';
	$address.='<span class="locality">'.$extras['city']['data'].'</span>';

	if(strlen($address) > 2 )
	{
		$ret = str_replace ( '${address}',
						'Adresse: '.'<div class="adr">'.$address.'</div>'."<br/>" , 
		$ret
		);
	}else{
		$ret = str_replace ( '${address}',
						'', 
		$ret
		);
	}
	if(1){
		$the_content=$ret;
		preg_match_all('/\[(?<name>\w+):([^\])]+)/', $the_content, $matches, PREG_SET_ORDER);
		FB::log('matches:',print_r($matches,1));
		foreach($matches as $match) {
			$count=0;
			//FB::log('match:',print_r($match,1));
			//FB::log('match[1]:',"<".$match[1].">");
			//FB::log('match[2]:',"<".$match[2].">");
			//FB::log('replace',"<".priv_argecal_taglib($match[1], $match[2],$the_content,1).$count.">");

			/**/
			$the_content = preg_replace(
				"/\[(?<name>\w+):([^\])]+)\]/", 
			priv_argecal_taglib($match[1], $match[2],"",1),
			$the_content,1,
			$count);

		}
		$ret=$the_content;
	}
	return $ret;
}


/* Generates the HTML for one event for a report.
 *
 * @param Event  $event The event
 * @param string $date  The date for which we're printing (in YYYYMMDD format)
 *
 * @return string HTML for this event based on report template.
 */
function event_to_text ( $event, $date ) {
	global $ALLOW_HTML_DESCRIPTION, $event_template, $login, $report_id, $user;

	$allDayStr = translate ( 'All day event' );
	$confStr = translate ( 'This event is confidential.' );
	// translate ( 'Private' )
	$privStr = translate ( '(Private)' );

	$end_time_str = $start_time_str = $time_str = '';

	$tempAllDay = $event->isAllDay ();
	$tempDurStr = $event->getDuration ();

	if ( $tempAllDay )
	$time_str = $allDayStr;
	else
	if ( $event->isUntimed () )
	$time_str = translate ( 'Untimed event' );
	else {
		$start_time_str = $time_str = display_time ( $event->getDateTime () );
		$time_short = getShortTime ( $time_str );
		if ( $tempDurStr > 0 ) {
			if ( $tempAllDay )
			$time_str = $allDayStr;
			else {
				$tempEDT = $event->getEndDateTime ();
				$end_time_str = display_time ( $tempEDT );
				$time_str .= ' - ' . display_time ( $tempEDT );
			}
		}
	}

	$name = $event->getName ();
	$tempAcc = $event->getAccess ();
	$tempDesc = $event->getDescription ();
	$tempExtForID = $event->getExtForID ();
	$tempLog = $event->getLogin ();

	if ( $tempExtForID != '' ) {
		$id = $tempExtForID;
		// translate ( 'cont.' )
		$name .= ' ' . translate ( '(cont.)' );
	} else
	$id = $event->getID ();

	if ( $tempAcc == 'R' ) {
		if ( ( $login != $user && strlen ( $user ) ) ||
		( $login != $tempLog && strlen ( $tempLog ) ) ) {
			$description_str = $confStr;
			$name_str = $privStr;
		}
	} else {
		$name_str = htmlspecialchars ( $name );
		if ( ! empty ( $ALLOW_HTML_DESCRIPTION ) && $ALLOW_HTML_DESCRIPTION == 'Y' ) {
			$str = str_replace ( '&', '&amp;', $tempDesc );
			//fix any broken special characters
			$str =  preg_replace("/&amp;(#[0-9]+|[a-z]+);/i", "&$1;", $str);
			$description_str = str_replace ( '&amp;amp;', '&amp;', $str );

			if ( strstr ( $description_str, '<' ) && strstr ( $description_str, '>' ) ) {
				// Found some HTML.
			} else
			// No HTML found. Add line breaks.
			$description_str = nl2br ( $description_str );
		} else
		$description_str = nl2br (
		activate_urls ( htmlspecialchars ( $tempDesc ) ) );
	}

	$date_full_str = date_to_str ( $date );
	$date_str = date_to_str ( $date, '', false );

	$duration_str = ( $tempDurStr > 0
	? $tempDurStr . ' ' . translate ( 'minutes' ) : '' );

	$temp = $event->getPriority ();
	$pri_str = ( $temp > 6
	? translate ( 'Low' )
	: ( $temp < 4
	? translate ( 'High' ) : translate ( 'Medium' ) ) );

	$temp = $event->getStatus ();
	if ( $temp == 'A' )
	$status_str = translate ( 'Approved' );
	elseif ( $temp == 'D' )
	$status_str = translate ( 'Deleted' );
	elseif ( $temp == 'R' )
	$status_str = translate ( 'Rejected' );
	elseif ( $temp == 'W' )
	$status_str = translate ( 'Waiting for approval' );
	else
	$status_str = translate ( 'Unknown' );

	$location = $event->getLocation ();
	$url = $event->getUrl ();

	if(strlen($url)>0)	{
		$url_str="<a href=\"".$url."\">Link</a><br>";
	}else{
		$url_str="";
	}
	FB::log('user:',print_r($user,1));
	FB::log('login:',print_r($login,1));

	if(isset($login) && strlen ( $login )>2){
		/*
		 <a href="/webcal/${href_str}">Goto Event ${id}</a>
		 */
		$href_str = '<a href="/webcal/view_entry.php?id=' . $id.'">View</a>,';
		$href_str .= '<a href="/webcal/edit_entry.php?id=' . $id.'">Edit</a>';

	}else{
		$href_str="";
	}
	// Get user's fullname.
	user_load_variables ( $tempLog, 'report_' );
	$fullname = $GLOBALS['report_fullname'];

	// Replace all variables in the event template.
	$text = str_replace (
	array (
		    '${date}'
		    ,'${dateYmd}'
		    ,'${description}'
		    ,'${duration}'
		    ,'${endtime}'
		    ,'${fulldate}'
		    ,'${fullname}'
		    ,'${href}'
		    ,'${id}'
		    ,'${location}'
		    ,'${name}'
		    ,'${priority}'
		    ,'${report_id}'
		    ,'${starttime}'
		    ,'${time}'
		    ,'${url}'
		    ,'${user}'
		    ,'${href_str}'
		    ,'${url_str}'
		    ),
		    array (
		    $date_str
		    ,$date
		    ,$description_str
		    ,$duration_str
		    ,$end_time_str
		    ,$date_full_str
		    ,$fullname
		    ,$href_str
		    ,$id
		    ,$location
		    ,$name_str
		    ,$pri_str
		    ,$report_id
		    ,$start_time_str
		    ,$time_str
		    ,$url
		    ,$tempLog
		    ,$href_str
		    ,$url_str
		    ),
		    $event_template
		    );

		    $text = replace_site_extras_in_template ( $text,
		    format_site_extras ( get_site_extra_fields ( $id ), EXTRA_DISPLAY_REPORT ) );

		    return $text;
}

/***
 *
 *
 *
 *
 *
 *
 *
 *
 *
 */
$error = $list =/* List of reports when no id specified. */
$u_url = '';

if ( ! empty ( $user ) && $user != $login &&
( ( ! empty ( $ALLOW_VIEW_OTHER ) && $ALLOW_VIEW_OTHER == 'Y' ) || $is_admin ) ) {
	$report_user = $user;
	$u_url = '&amp;user=' . $user;
}

if ( empty ( $REPORTS_ENABLED ) || $REPORTS_ENABLED != 'Y' )
$error = print_not_auth (12);

$updating_public = false;
if ( $is_admin && ! empty ( $public ) && $PUBLIC_ACCESS == 'Y' ) {
	$report_user = '__public__';
	$updating_public = true;
}

$offset = getValue ( 'offset', '-?[0-9]+', true );
if ( empty ( $offset ) ){
	$offset = 0;
}
$report_id = getValue ( 'report_id', '-?[0-9]+', true );
// If no report id is specified,
// then generate a list of reports from which the user may choose.
if (( empty ( $error ) && empty ( $report_id ) && $login == '__public__' )){
	$error = print_not_auth (27);
}


$invalidID = translate ( 'Invalid report id.' );
if ( empty ( $error ) && empty ( $report_id ) ) {
	$list = '';
	$sql = 'SELECT cal_report_id, cal_report_name FROM webcal_report
    WHERE cal_login = ';
	$sql_params = array ();
	if ( $is_admin ) {
		if ( ! $updating_public ) {
			if ( $PUBLIC_ACCESS == 'Y' ) {
				// translate ( 'Click here' )
				// translate ( 'to manage reports for the Public Access calendar' )
				$clickStr =
				translate ( 'Click here to manage reports for the Public Access calendar.' );
				$list .= '
    <p><a title="' . $clickStr . '" href="report.php?public=1">'
    . $clickStr . '</a></p>';
			}
			$sql .= '? OR cal_is_global = \'Y\'';
			$sql_params[] = $login;
		} else
		$sql .= '\'__public__\'';
	} else {
		$sql_params[] = $login;
		$sql .= '?';
	}
	$res = dbi_execute ( $sql . ' ORDER BY cal_update_date DESC, cal_report_name',
	$sql_params );
	$list .= '
    <ul>';
	if ( $res ) {
		$addStr = translate ( 'Add new report' );
		$unnamesStr = translate ( 'Unnamed Report' );
		while ( $row = dbi_fetch_row ( $res ) ) {
			$rep_name = trim ( $row[1] );
			if ( empty ( $rep_name ) )
			$rep_name = $unnamesStr;

			$list .= '
      <li><a href="edit_report.php?report_id=' . $row[0] . '" class="nav">'
      . $rep_name . '</a></li>';
		}
		$list .= '
    </ul>';
		$addurl = 'edit_report.php' . ( $updating_public ? '?public=1' : '' );
		$list .= '
    <p><a title="' . $addStr . '" href="' . $addurl . '" class="nav">'
    . $addStr . '</a></p>';
    dbi_free_result ( $res );
	} else
	$error = $invalidID;
}
// Load the specified report.
$report_type="html-part-undefined";

/* define variables before used */
$report_allow_nav=null;
$report_time_range=null;
if ( empty ( $error ) && empty ( $list ) ) {
	$sql= 'SELECT cal_login, cal_report_id, cal_is_global,
    cal_report_type, cal_include_header, cal_report_name, cal_time_range,
    cal_user, cal_allow_nav, cal_cat_id, cal_include_empty, cal_update_date
    FROM webcal_report WHERE cal_report_id = ?';

	$res = dbi_execute ( $sql, array ( $report_id ) );
	if ( $res ) {
		if ( $row = dbi_fetch_row ( $res ) ) {
			if ( $row[2] != 'Y' && $login != $row[0] )
			$error = print_not_auth (14);
			else {
				$i = 0;
				$report_login = $row[$i++];
				$report_id = $row[$i++];
				$report_is_global = $row[$i++];
				$report_type = $row[$i++];
				$report_include_header = $row[$i++];
				$report_name = $row[$i++];
				$report_time_range = $row[$i++];
				$test_report_user = $row[$i++];
				// If this report type specifies a specific user,
				// then we will use that user even if a user was passed in via URL.
				if ( ! empty ( $test_report_user ) )
				$report_user = $test_report_user;

				$report_allow_nav = $row[$i++];
				$report_cat_id = $row[$i++];
				$report_include_empty = $row[$i++];
				$report_update_date = $row[$i++];
			}
		} else{
			$error = $invalidID;
		}
		dbi_free_result ( $res );
	} else
	$error = db_error ();
}

$days_temp = getValue ( 'days', '-?[0-9]+', true );
if($days_temp)
{
	FB::info('days_temp:'.$days_temp);
	switch ( $days_temp ) {
		case 14:
			$report_time_range=50;
			break;
		case 30:
			$report_time_range=51;
			break;
		case 60:
			$report_time_range=52;
			break;
		case 90:
			$report_time_range=53;
			break;
		case 180:
			$report_time_range=54;
			break;
		case 365:
			$report_time_range=55;
			break;
		default:
			echo 'Invalid range of days setting for ?days=(14,30,60,90,180,365)';
			exit;
	}
}else{
}


if ( empty ( $report_user ) ){
	$report_user = $login;
}
// Set default templates (in case there are none in the database for this report.)
$day_str = $printerStr = '';
$day_template = '<dt><b>${date}</b></dt><dd><dl>${events}</dl></dd>';
$event_template = '<dt>${name}</dt>
<dd><b>' . translate ( 'Date' ) . ':</b> ${date}<br />
<b>' . translate ( 'Time' ) . ':</b> ${time}<br />
${description}</dd>';
$page_template = '<dl>${days}</dl>';

// Load templates for this report.
if ( empty ( $error ) && empty ( $list ) ) {
	$res = dbi_execute ( 'SELECT cal_template_type, cal_template_text
    FROM webcal_report_template WHERE cal_report_id = ?', array ( $report_id ) );
	if ( $res ) {
		while ( $row = dbi_fetch_row ( $res ) ) {
			if ( $row[0] == 'D' )
			$day_template = $row[1];
			elseif ( $row[0] == 'E' )
			$event_template = $row[1];
			elseif ( $row[0] == 'P' )
			$page_template = $row[1];
			else {
				// This shouldn't happen under normal circumstances, so no need translate.
				echo 'Invalid template type: ' . $row[0];
				exit;
			}
		}
		dbi_free_result ( $res );
	} else
	$error = db_error ();
}

$include_header =
( ! empty ( $report_include_header ) && $report_include_header == 'Y' );

if ( $include_header || ! empty ( $list ) || ! empty ( $error ) ) {
	$printerStr = ( empty ( $report_id )
	? '' : generate_printer_friendly ( 'report.php' ) );
	print_header ();
}

if ( empty ( $offset ) || empty ( $report_allow_nav ) || $report_allow_nav != 'Y' )
$offset = 0;

// Set time range based on cal_time_range field.
$dated = date ( 'd' );
$datem = date ( 'm' );
$dateY = date ( 'Y' );
$DISPLAY_WEEKENDS = 'Y';
$next = $offset + 1;
$prev = $offset - 1;
$wkstart = get_weekday_before ( $dateY, $datem, $dated + 1 );
$today = mktime ( 0, 0, 0, $datem, $dated, $dateY );
$end_date = $start_date = $today;
if ( ! isset ( $report_time_range ) ) {
	// Manage reports.
} else
if ( $report_time_range >= 0 && $report_time_range < 10 ) {
	$today = mktime ( 0, 0, 0, $datem, $dated, $dateY );
	$days_offset = 1 - $report_time_range + $offset;
	$end_date = $start_date = $today + ( $days_offset * 86400 );
} else
if ( $report_time_range > 9 && $report_time_range < 20 ) {
	$week_offset = 11 - $report_time_range + $offset;
	$start_date = $wkstart + ( $week_offset * 604800 );
	$end_date = $start_date + ( 86400 * 6 );
} else
if ( $report_time_range > 19 && $report_time_range < 30 ) {
	$week_offset = 21 - $report_time_range + $offset;
	$start_date = $wkstart + ( $week_offset * 604800 );
	$end_date = $start_date + ( 86400 * 13 );
} else
if ( $report_time_range > 29 && $report_time_range < 40 ) {
	$thismonth = $datem;
	$month_offset = 31 - $report_time_range + $offset;
	$start_date = mktime ( 0, 0, 0, $thismonth + $month_offset, 1, $dateY );
	$end_date = mktime ( 23, 59, 59, $thismonth + $month_offset + 1, 0, $dateY );
} else
if ( $report_time_range > 39 && $report_time_range < 50 ) {
	$thisyear = $dateY;
	$year_offset = 41 - $report_time_range + $offset;
	$start_date = mktime ( 0, 0, 0, 1, 1, $thisyear + $year_offset );
	$end_date = mktime ( 23, 59, 59, 12, 31, $thisyear + $year_offset );
} else
if ( $report_time_range > 49 && $report_time_range < 60 ) {
	// This series of reports is today + N days
	switch ( $report_time_range ) {
		case 50:
			$x = 14;
			break;
		case 51:
			$x = 30;
			break;
		case 52:
			$x = 60;
			break;
		case 53:
			$x = 90;
			break;
		case 54:
			$x = 180;
			break;
		case 55:
			$x = 365;
			break;
		default:
			echo 'Invalid cal_time_range setting for report id ' . $report_id;
			exit;
	}
	$today = mktime ( 0, 0, 0, $datem, $dated, $dateY );
	$start_date = $today + ( 86400 * $offset * $x );
	$end_date = $start_date + ( 86400 * $x );
} else {
	// Programmer's bug (no translation needed).
	echo 'Invalid cal_time_range setting for report id ' . $report_id;
	exit;
}

$start_date_full_str=date_to_str(date("Ymd",$start_date));
$end_date_full_str =date_to_str(date("Ymd",$end_date));

//echo "start_date=".$start_date."<br>\n";
//echo "start_date_full_str=".$start_date_full_str."<br>\n";

// The read_repeated_events calculates all event repeat dates for
// some time period after the values of $thismonth and $thisyear.
if ( ! empty ( $end_date ) ) {
	$thismonth = date ( 'm', $end_date );
	$thisyear = date ( 'Y', $end_date );
}

/**
TODO: find a solution not to fake user here
*/
if(isset($_GET["report_user"])){
	FB::info("read_repeated_events _GET report_user".$_GET["report_user"]);
	$report_user=$_GET["report_user"];
}else{
	FB::info("read_repeated_events  NO report_user".$report_user);
}
if ( empty ( $error ) && empty ( $list ) ) {
	$cat_id = empty ( $report_cat_id ) ? '' : $report_cat_id;

	$repeated_events = read_repeated_events ( $report_user, $start_date,
	$end_date, $cat_id );
	$events = read_events ( $report_user, $start_date, $end_date, $cat_id );

	$get_unapproved = ( $DISPLAY_UNAPPROVED == 'Y' );
	// Loop through each day.
	// Get events for each day (both normal and repeating).
	// (Most of this code was copied from week.php.)
	for ( $cur_time = $start_date; $cur_time <= $end_date; $cur_time += 86400 ) {
		$event_str = '';
		$dateYmd = date ( 'Ymd', $cur_time );
		$ev = combine_and_sort_events (
		get_entries ( $dateYmd ),
		get_repeating_entries ( $report_user, $dateYmd ) );
		$cnt = count ( $ev );
		if( $cnt > 0 )
		{
			$cnt = $cnt;
		}
		for ( $i = 0;  $i < $cnt; $i++ ) {
			if ( $get_unapproved || $ev[$i]->getStatus () == 'A' )
			$event_str .= event_to_text ( $ev[$i], $dateYmd );
		}

		if ( ! empty ( $event_str ) || $report_include_empty == 'Y' || $report_time_range < 10 ) {
			$date_str = date_to_str ( $dateYmd, '', false );
			$date_full_str = date_to_str ( $dateYmd );

			$day_str .= str_replace (
			array (
        	'${date}',
        	'${events}', 
        	'${fulldate}', 
        	'${report_id}',
			'${start_date_full_str}' ,
			'${end_date_full_str}' 
			),
			array (
			$date_str,
			$event_str,
			$date_full_str,
			$report_id,
			$start_date_full_str,
			$end_date_full_str
			),
			$day_template
			);
		}
	}
}
if ( ! empty ( $error ) ) {
	echo print_error ( $error ) . print_trailer ();
	exit;
}

$adminLinkStr = $manageStr = $nextLinkStr = $prevLinkStr = $textStr = '';
$nextStr = translate ( 'Next' );
$prevStr = translate ( 'Previous' );
$reportNameStr = ( $include_header ? '
    <h2>' . $report_name . '</h2>' : '' );

if ( ! empty ( $report_allow_nav ) && $report_allow_nav == 'Y' ) {
	$temp = '" href="report.php?report_id=' . $report_id . $u_url . '&amp;offset=';

	$nextLinkStr = $prevLinkStr = '
    <a class="nav" title="';
	$nextLinkStr .= $nextStr . $temp . $next . '">' . $nextStr . '</a>';
	$prevLinkStr .= $prevStr . $temp . $prev . '">' . $prevStr . '</a>&nbsp;&nbsp;';
}

if ( empty ( $list ) ) {
	$textStr .= str_replace (
	array (
        	'${days}',
	//'${date}',
        	'${events}', 
	//'${fulldate}',
        	'${report_id}',
			'${start_date_full_str}' ,
			'${end_date_full_str}' 
			),
			array (
			$day_str,
			//$date_str,
			$event_str,
			//$date_full_str,
			$report_id,
			$start_date_full_str,
			$end_date_full_str
			),
			$page_template
			);
			switch($report_type)
			{
				case "RSS":
					$trailerStr=""; // we will be used as html-part elsewhere
					break;
				case "html-part":
					$trailerStr=""; // we will be used as html-part elsewhere
					break;
				case "html":
				default:
					$trailerStr = print_trailer ( $include_header );
					break;
			}

} else {
	$adminLinkStr = display_admin_link ();
	$manageStr = '
    <h2>'
    . ( $updating_public ? translate ( $PUBLIC_ACCESS_FULLNAME ) . ' ' : '' )
    . translate ( 'Manage Reports' ) . '</h2>';
    $trailerStr = print_trailer ();
}



switch($report_type)
{
	case "RSS":
		$trailerStr=""; // we will be used as html-part elsewhere
		break;
	case "html-part":
		$trailerStr=""; // we will be used as html-part elsewhere
		echo $reportNameStr.$manageStr.$adminLinkStr.$list.$textStr.$printerStr.$trailerStr	;
		break;
	case "html":
	default:
		echo $reportNameStr.$manageStr.$adminLinkStr.$list.$textStr.$printerStr.$trailerStr	;
		break;
		break;
}

?>
