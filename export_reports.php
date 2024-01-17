<?php
/*
 *	Exports the advanced reports configuration as a JSON document.
 */

namespace Nottingham\AdvancedReports;


if ( ! $module->framework->getUser()->isSuperUser() )
{
	exit;
}

header( 'Content-Type: application/json' );
header( 'Content-Disposition: attachment; filename=' .
        trim( preg_replace( '/[^A-Za-z0-9-]+/', '_', \REDCap::getProjectTitle() ), '_-' ) .
        '_reports_' . gmdate( 'Ymd-His' ) . '.json' );

$projectID = $module->getProjectID();
$listReports = json_decode( $module->getSystemSetting( "p$projectID-report-list" ), true );
$data = [ 'report-list' => $listReports ];

foreach ( $listReports as $reportName )
{
	$reportConfig = json_decode( $module->getSystemSetting("p$projectID-report-config-$reportName"),
	                             true );
	unset( $reportConfig['lastupdated_user'], $reportConfig['lastupdated_time'] );
	$data["report-config-$reportName"] = $reportConfig;
	$data["report-data-$reportName"] =
		json_decode( $module->getSystemSetting("p$projectID-report-data-$reportName"), true );
}

echo json_encode( $data );