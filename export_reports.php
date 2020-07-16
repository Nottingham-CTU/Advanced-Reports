<?php
/*
 *	Exports the advanced reports configuration as a JSON document.
 */


if ( ! $module->framework->getUser()->isSuperUser() )
{
	exit;
}

header( 'Content-Type: application/json' );
header( 'Content-Disposition: attachment; filename=' .
        trim( preg_replace( '/[^A-Za-z0-9-]+/', '_', \REDCap::getProjectTitle() ), '_-' ) .
        '_reports_' . gmdate( 'Ymd-His' ) . '.json' );

$listReports = json_decode( $module->getProjectSetting( 'report-list' ), true );
$data = [ 'report-list' => $listReports ];

foreach ( $listReports as $reportName )
{
	$data["report-config-$reportName"] =
		json_decode( $module->getProjectSetting("report-config-$reportName"), true );
	$data["report-data-$reportName"] =
		json_decode( $module->getProjectSetting("report-data-$reportName"), true );
}

foreach ( [ 'edit-if-design', 'edit-if-reports' ] as $extraVar )
{
	$data[$extraVar] = $module->getProjectSetting($extraVar);
}

echo json_encode( $data );