<?php
/**
 *	PDF Reports view page.
 */

namespace Nottingham\AdvancedReports;
const TVALIDSTR = 'text_validation_type_or_show_slider_number';



// Verify the report exists, is a PDF report, and is visible.
// Redirect to main reports page if not.
$reportID = $_GET['report_id'];
$listReports = $module->getReportList();
if ( ! isset( $listReports[$reportID] ) || $listReports[$reportID]['type'] != 'pdf' )
{
	header( 'Location: ' . $module->getUrl( 'reports.php' ) );
	exit;
}


// Check user can view this report, redirect to main reports page if not.
if ( ! $module->isReportAccessible( $reportID ) )
{
	header( 'Location: ' . $module->getUrl( 'reports.php' ) );
	exit;
}


// Get the report data.
$reportConfig = $listReports[$reportID];
$reportData = $module->getReportData( $reportID );


// Check a valid source is specified, redirect to main reports page if not.
if ( ! isset( $reportData['source'] ) || ! isset( $listReports[ $reportData['source'] ] ) ||
     $listReports[ $reportData['source'] ]['type'] != 'instrument' )
{
	header( 'Location: ' . $module->getUrl( 'reports.php' ) );
	exit;
}


// Get the data for inclusion in the PDF.
$_POST = [];
$_GET['report_id'] = $reportData['source'];
$listResults = (function ( $module, $type )
{
	$isApiRequest = true;
	$isInternalRequest = true;
	return ( require $type . '_view.php' );
})( $module, $listReports[ $reportData['source'] ]['type'] );


// Get the HTML for the PDF.
$inputHTML = $reportData['pdf'];


// Load dependencies.
require __DIR__ . '/vendor/autoload.php';

// Set up the PDF.
$pdf = new \Dompdf\Dompdf();
$pdf->getOptions()->set( [
                           'allowedProtocols' => [],
                           'isPhpEnabled' => false
                         ] );
$pdf->setPaper( $reportData['pdf_size'], $reportData['pdf_orientation'] );

// Load the HTML.
$pdf->loadHtml( $reportData['pdf'] );

// Generate the filename, render the PDF and output.
$queryDev = $module->query( 'SELECT value FROM redcap_config WHERE field_name = ?',
                            [ 'is_development_server' ] );
$isDev = $queryDev->fetch_row();
$isDev = $isDev[0] == '1';
$pdf->render();
$pdf->stream( trim( preg_replace( '/[^A-Za-z0-9-]+/', '_', \REDCap::getProjectTitle() ), '_-' ) .
		      '_' . preg_replace( '/[^A-Za-z0-9-]+/', '_', $reportID ) . '_' .
		      gmdate( 'Ymd-His' ) . ( $isDev ? '_dev' : '' ),
              [ 'compress' => 1, 'Attachment' => isset( $_GET['download'] ) ] );
