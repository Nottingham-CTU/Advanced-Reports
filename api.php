<?php
/**
 *	Report API endpoint.
 */

namespace Nottingham\AdvancedReports;

header( 'Content-Type: application/json' );


// Verify the report exists and is accessible through the API.
$reportID = $_GET['report_id'];
$listReports = $module->getReportList();
if ( ! isset( $listReports[$reportID] ) || ! isset( $listReports[$reportID]['as_api'] ) ||
     ! $listReports[$reportID]['as_api'] )
{
	http_response_code( 400 );
	echo 'false';
	exit;
}


// Get the report config, validate the API key, and check the report type.
$reportConfig = $listReports[$reportID];
if ( $reportConfig['api_key'] == '' || ! isset( $_POST['api_key'] ) ||
     $_POST['api_key'] != $reportConfig['api_key'] ||
     ! in_array( $reportConfig['type'], [ 'instrument', 'sql' ] ) )
{
	http_response_code( 400 );
	echo 'false';
	exit;
}


// Log API access.
\REDCap::logEvent( 'Advanced Reports API', $reportID );


// Set the API access variable, clear POST data and load the report.
$isApiRequest = true;
$_POST = [];
$apiResult = ( require $reportConfig['type'] . '_view.php' );
echo json_encode( $apiResult );
