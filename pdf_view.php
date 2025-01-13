<?php
/**
 *	PDF Reports view page.
 */

namespace Nottingham\AdvancedReports;
const TVALIDSTR = 'text_validation_type_or_show_slider_number';



// Verify the report exists, and is a PDF report.
// Redirect to main reports page if not.
$reportID = $_GET['report_id'];
$listReports = $module->getReportList();
if ( ! isset( $listReports[$reportID] ) || $listReports[$reportID]['type'] != 'pdf' )
{
	header( 'Location: ' . $module->getUrl( 'reports.php' ) );
	exit;
}


// Determine the request type.
$disableAccessControl = isset( $disableAccessControl ) ? $disableAccessControl : false;
$isInternalRequest = isset( $isInternalRequest ) ? $isInternalRequest : false;


// Check user can view this report, redirect to main reports page if not.
if ( ! $disableAccessControl && ! $module->isReportAccessible( $reportID ) )
{
	header( 'Location: ' . $module->getUrl( 'reports.php' ) );
	exit;
}


// Get the report data.
$reportConfig = $listReports[$reportID];
$reportData = $module->getReportData( $reportID );
$allowSQLSource = $module->getSystemSetting( 'allow-sql-source' );


// Check a valid source is specified, redirect to main reports page if not.
if ( ! isset( $reportData['source'] ) || ! isset( $listReports[ $reportData['source'] ] ) ||
     ( $listReports[ $reportData['source'] ]['type'] != 'instrument' &&
       ( ! $allowSQLSource || $listReports[ $reportData['source'] ]['type'] != 'sql' ) ) )
{
	if ( $isInternalRequest )
	{
		return;
	}
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
$listVars = [];


// Get the HTML for the PDF.
$inputHTML = $reportData['pdf'];


// Parse conditional and loop instructions.
$inputHTML = preg_split( '/(<\\?(?:end|loop|(?:if|setvar\\[([^\\[\\]]+)\\])' .
                         '(\\((?:[^)(\'"]+|\'[^\']*\'|"[^"]*"|(?3))*+\\)))>)/',
                         $inputHTML, -1, PREG_SPLIT_DELIM_CAPTURE );
// - Function to replace the field placeholders with the corresponding values.
//   This has to be done here, not using the module's parseLogic function, because that won't accept
//   field names which are not valid REDCap field names (which can be the case here because the
//   source report's column names are used).
function fieldReplace( $input, $record, $forCalc, $module, $listVars )
{
	$fieldNames = array_map( function ( $i ) { return '[' . $i . ']'; },
	                         array_merge( array_keys( $listVars ), array_keys( $record ) ) );
	if ( $forCalc )
	{
		$values = array_map( function ( $i ) { return '"' . addslashes( $i ) . '"'; },
		                     array_merge( array_values( $listVars ), array_values( $record ) ) );
	}
	else
	{
		$values = array_map( function ( $i ) use ( $module ) { return $module->parseHTML( $i ); },
		                     array_merge( array_values( $listVars ), array_values( $record ) ) );
	}
	return str_replace( $fieldNames, $values, $input );
}
// - Function to recursively process the input, parsing any loop/conditional instructions.
function parseParts( &$input, $resultItem, $module, $listResults, &$listVars )
{
	$output = '';
	while ( !empty( $input ) )
	{
		$inputItem = array_shift( $input );
		if ( $inputItem == '<?end>' )
		{
			// Section end, so return to the calling function.
			return $output;
		}
		elseif ( $inputItem == '<?loop>' )
		{
			// Call the function repeatedly for each result from the source data.
			// The input array is copied each time so the same loop section is re-run.
			foreach ( $listResults as $infoResult )
			{
				$loopInput = $input;
				$output .= parseParts( $loopInput, $infoResult, $module, $listResults, $listVars );
			}
			// Call the function again (discarding output) without copying the input array, so the
			// array items are now removed ready for the next step.
			parseParts( $input, $resultItem, $module, $listResults, $listVars );
		}
		elseif ( substr( $inputItem, 0, 5 ) == '<?if(' )
		{
			// Get the condition from the input array.
			// Dummy var is required to skip the position used by setvar but not if.
			$dummy = array_shift( $input );
			$condition = array_shift( $input );
			if ( strpos( $inputItem, $condition ) === false )
			{
				// When splitting the input, the full condition instruction and the logic sub-part
				// are each included on the input array, so if the input item does not contain the
				// condition, it is not a valid condition instruction, so the 'condition' item
				// should be returned to the input array and processed in the next step.
				array_unshift( $input, $condition );
				array_unshift( $input, $dummy );
			}
			else
			{
				// Perform the field replace on the condition prior to parsing.
				$condition = fieldReplace( $condition, $resultItem, true, $module, $listVars );
				// Parse and run the logic. Anything which resembles a project variable at this
				// point will be replaced with the empty string.
				list( $condFunction, $condParamData ) = $module->parseLogic( $condition );
				$condParams = array_map( function ( $i ) { return ''; }, $condParamData );
				$condition = $condFunction( ...$condParams );
				// Always call the function to parse the condition block (to ensure the input array
				// items are all removed as necessary), but only include the output if the condition
				// matches.
				$condOutput = parseParts( $input, $resultItem, $module, $listResults, $listVars );
				if ( $condition )
				{
					$output .= $condOutput;
				}
			}
		}
		elseif ( substr( $inputItem, 0, 9 ) == '<?setvar[' )
		{
			// Get the varname and logic from the input array.
			$varname = array_shift( $input );
			$logic = array_shift( $input );
			if ( strpos( $inputItem, $varname ) === false ||
			     strpos( $inputItem, $logic ) === false )
			{
				// When splitting the input, the full setvar instruction and the varname/logic
				// sub-parts are each included on the input array, so if the input item does not
				// contain the subparts, it is not a valid setvar instruction, so the subparts
				// should be returned to the input array and processed in the next step.
				array_unshift( $input, $logic );
				array_unshift( $input, $varname );
			}
			else
			{
				// Perform the field replace on the logic prior to parsing.
				$logic = fieldReplace( $logic, $resultItem, true, $module, $listVars );
				// Parse and run the logic. Anything which resembles a project variable at this
				// point will be replaced with the empty string.
				list( $condFunction, $condParamData ) = $module->parseLogic( $logic );
				$condParams = array_map( function ( $i ) { return ''; }, $condParamData );
				$logic = $condFunction( ...$condParams );
				// Set the variable to the result of the logic.
				$listVars[ $varname ] = $logic;
			}
		}
		else
		{
			// Not a loop/condition instruction, so just output the data.
			$output .= fieldReplace( $inputItem, $resultItem, false, $module, $listVars );
		}
	}
	return $output;
}
$inputHTML = parseParts( $inputHTML, $listResults[0] ?? [], $module, $listResults, $listVars );



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
$pdf->loadHtml( $inputHTML );

// Render the PDF.
$pdf->render();

// Generate the filename and output the PDF.
if ( $isInternalRequest )
{
	echo $pdf->output( [ 'compress' => 1 ] );
	return;
}
$queryDev = $module->query( 'SELECT value FROM redcap_config WHERE field_name = ?',
                            [ 'is_development_server' ] );
$isDev = $queryDev->fetch_row();
$isDev = $isDev[0] == '1';
$pdf->stream( trim( preg_replace( '/[^A-Za-z0-9-]+/', '_', \REDCap::getProjectTitle() ), '_-' ) .
              '_' . preg_replace( '/[^A-Za-z0-9-]+/', '_', $reportID ) . '_' .
              gmdate( 'Ymd-His' ) . ( $isDev ? '_dev' : '' ),
              [ 'compress' => 1, 'Attachment' => isset( $_GET['download'] ) ] );
