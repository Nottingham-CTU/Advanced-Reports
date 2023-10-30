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


// Get the data for inclusion in the PDF.
$listResults = [];
foreach ( $reportData['forms'] as $queryForm )
{
	// Get the form name and alias (use form name for alias if not defined).
	$form = $queryForm['form'];
	$alias = $queryForm['alias'] == '' ? $form : $queryForm['alias'];
	// Get the fields for the form and retrieve the values and value labels for each record.
	$fields = array_unique( array_merge( [ \REDCap::getRecordIdField() ],
	                                     \REDCap::getFieldNames( $form ) ) );
	$fieldMetadata = \REDCap::getDataDictionary( 'array', false, $fields );
	$formValues = getInstrumentData( $fields, false );
	$formLabels = getInstrumentData( $fields, true );
	$dateFields = [];
	foreach ( $fieldMetadata as $fieldName => $fieldParams )
	{
		if ( $fieldParams['field_type'] == 'text' &&
		     ( substr( $fieldParams[TVALIDSTR], 0, 5 ) == 'date_' ||
		       substr( $fieldParams[TVALIDSTR], 0, 9 ) == 'datetime_' ||
		       in_array( $fieldParams[TVALIDSTR], [ 'time', 'time_hh_mm_ss' ] ) ) )
		{
			$dateFields[] = $fieldName;
		}
	}
}



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
