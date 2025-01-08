<?php
/**
 *	Public report endpoint.
 */

namespace Nottingham\AdvancedReports;


// Verify the report exists and is accessible publicly.
$reportID = $_GET['report_id'];
$listReports = $module->getReportList();
if ( ! isset( $listReports[$reportID] ) || ! isset( $listReports[$reportID]['as_public'] ) ||
     ! $listReports[$reportID]['as_public'] )
{
	http_response_code( 400 );
	echo 'false';
	exit;
}


// Get the report config and check the report type.
$reportConfig = $listReports[$reportID];
if ( ! in_array( $reportConfig['type'], AdvancedReports::PUBLIC_TYPES ) )
{
	http_response_code( 400 );
	echo 'false';
	exit;
}


// Set the variable to deactivate report access control, clear POST data and load the report.
$disableAccessControl = true;
$_POST = [];
require $reportConfig['type'] . '_view.php';
