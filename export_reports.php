<?php
/*
 *	Exports the advanced reports configuration as a JSON document.
 */

namespace Nottingham\AdvancedReports;


// Check user can edit reports.
if ( ! $module->isReportEditable() )
{
	exit;
}

$projectID = $module->getProjectID();
$listReports = json_decode( $module->getSystemSetting( "p$projectID-report-list" ), true );
sort( $listReports, SORT_NATURAL );
$singleReport = false;
// If a single report is being exported, the report list is just the report ID as a string.
if ( isset( $_GET['report_id'] ) && in_array( $_GET['report_id'], $listReports ) )
{
	$data = [ [ 'report-list' => $_GET['report_id'] ] ];
	$singleReport = true;
}
// Otherwise the report list is the full list of reports. Include a list of skipped reports which
// is used if a report cannot be included because the user doesn't have edit rights for it.
else
{
	$data = [ [ 'report-list' => $listReports, 'report-skip' => [] ] ];
}

foreach ( $listReports as $reportName )
{
	// A single report is being exported so only export that one.
	if ( $singleReport && $data[0]['report-list'] != $reportName )
	{
		continue;
	}

	// Get the report configuration.
	$reportConfig = json_decode( $module->getSystemSetting("p$projectID-report-config-$reportName"),
	                             true );

	// Check the report can be exported.
	if ( ! $module->isReportEditable( $reportConfig['type'] ) )
	{
		// If exporting a single report, but we can't export it, just exit here.
		// (The UI shouldn't allow the user to get to this point.)
		if ( $singleReport )
		{
			exit;
		}
		// The report cannot be exported, so mark it as skipped and move on.
		$data[0]['report-skip'][] = $reportName;
		unset( $data[0]['report-list'][ array_search( $reportName, $data[0]['report-list'] ) ] );
		$data[0]['report-list'] = array_values( $data[0]['report-list'] );
		continue;
	}

	// Update timestamps and API keys should not be included in exports.
	unset( $reportConfig['lastupdated_user'], $reportConfig['lastupdated_time'] );
	if ( isset( $reportConfig['api_key'] ) )
	{
		$reportConfig['api_key'] = '';
	}

	// Prepare the report config and data for export.
	$data[] = [ "report-config-$reportName" => $reportConfig,
	            "report-data-$reportName" =>
	                  json_decode( $module->getSystemSetting("p$projectID-report-data-$reportName"),
	                               true ) ];
}

header( 'Content-Type: application/json' );
header( 'Content-Disposition: attachment; filename=' .
        trim( preg_replace( '/[^A-Za-z0-9-]+/', '_', \REDCap::getProjectTitle() ), '_-' ) .
        '_report' . ( $singleReport ? ( '_' . $data[0]['report-list'] )
                                    : ( 's_' . gmdate( 'Ymd-His' ) ) ) . '.json' );

foreach ( $data as $item )
{
	echo isset( $item['report-list'] ) ? '{' : ',';
	$module->echoText( substr( json_encode( $item, JSON_UNESCAPED_SLASHES ), 1, -1 ) );
	echo "\n";
}
echo "}\n";
