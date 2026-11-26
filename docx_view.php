<?php
/**
 *	Word Document Reports view page.
 */

namespace Nottingham\AdvancedReports;
const TVALIDSTR = 'text_validation_type_or_show_slider_number';



// Verify the report exists, and is a Word Document report.
// Redirect to main reports page if not.
$reportID = $_GET['report_id'];
$listReports = $module->getReportList();
if ( ! isset( $listReports[$reportID] ) || $listReports[$reportID]['type'] != 'docx' )
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
     ! ( $listReports[ $reportData['source'] ]['type'] == 'instrument' ||
         ( $allowSQLSource &&
           in_array( $listReports[ $reportData['source'] ]['type'], [ 'sql', 'system' ] ) ) ) )
{
	if ( $isInternalRequest )
	{
		return;
	}
	header( 'Location: ' . $module->getUrl( 'reports.php' ) );
	exit;
}


// Get the data for inclusion in the Word document.
$_POST = [];
$_GET['report_id'] = $reportData['source'];
$listResults = (function ( $module, $type )
{
	$isApiRequest = true;
	$isInternalRequest = true;
	return ( require $type . '_view.php' );
})( $module, $listReports[ $reportData['source'] ]['type'] );
$listVars = [];


// Get the HTML for the Word document.
$inputHTML = $reportData['docx'];


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


// Create the Word document.
$phpWord = new \PhpOffice\PhpWord\PhpWord();


// Set default font.
$phpWord->setDefaultFontName( 'Calibri' );
$phpWord->setDefaultFontSize( 11 );


// Add a section.
$section = $phpWord->addSection();


// Convert HTML to Word document elements.
// We'll use a simple approach: parse the HTML and convert basic tags to Word elements.
$html = $inputHTML;


// Create a DOM document to parse the HTML
$dom = new \DOMDocument();
$dom->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

// Process the DOM and add elements to the section
function processNode( $node, $section, $phpWord )
{
	if ( $node->nodeType === XML_TEXT_NODE )
	{
		$text = trim( $node->textContent );
		if ( $text !== '' )
		{
			$section->addText( $text );
		}
		return;
	}

	if ( $node->nodeType !== XML_ELEMENT_NODE )
	{
		return;
	}

	$tagName = strtolower( $node->nodeName );

	switch ( $tagName )
	{
		case 'p':
		case 'div':
			foreach ( $node->childNodes as $childNode )
			{
				processNode( $childNode, $section, $phpWord );
			}
			// Add a line break after block elements
			$section->addTextBreak( 1 );
			break;

		case 'br':
			$section->addTextBreak( 1 );
			break;

		case 'b':
		case 'strong':
			foreach ( $node->childNodes as $childNode )
			{
				if ( $childNode->nodeType === XML_TEXT_NODE )
				{
					$text = trim( $childNode->textContent );
					if ( $text !== '' )
					{
						$section->addText( $text, [ 'bold' => true ] );
					}
				}
				else
				{
					processNode( $childNode, $section, $phpWord );
				}
			}
			break;

		case 'i':
		case 'em':
			foreach ( $node->childNodes as $childNode )
			{
				if ( $childNode->nodeType === XML_TEXT_NODE )
				{
					$text = trim( $childNode->textContent );
					if ( $text !== '' )
					{
						$section->addText( $text, [ 'italic' => true ] );
					}
				}
				else
				{
					processNode( $childNode, $section, $phpWord );
				}
			}
			break;

		case 'u':
			foreach ( $node->childNodes as $childNode )
			{
				if ( $childNode->nodeType === XML_TEXT_NODE )
				{
					$text = trim( $childNode->textContent );
					if ( $text !== '' )
					{
						$section->addText( $text, [ 'underline' => true ] );
					}
				}
				else
				{
					processNode( $childNode, $section, $phpWord );
				}
			}
			break;

		case 'h1':
		case 'h2':
		case 'h3':
		case 'h4':
		case 'h5':
		case 'h6':
			foreach ( $node->childNodes as $childNode )
			{
				if ( $childNode->nodeType === XML_TEXT_NODE )
				{
					$text = trim( $childNode->textContent );
					if ( $text !== '' )
					{
						$headingStyle = [ 'bold' => true, 'size' => 16 - (int)$tagName[1] ];
						$section->addText( $text, $headingStyle );
					}
				}
				else
				{
					processNode( $childNode, $section, $phpWord );
				}
			}
			$section->addTextBreak( 1 );
			break;

		case 'img':
			$src = $node->getAttribute( 'src' );
			if ( $src !== '' )
			{
				// Handle data URIs (embedded images)
				if ( strpos( $src, 'data:' ) === 0 )
				{
					// Extract base64 data
					if ( preg_match( '/^data:image\/([^;]+);base64,(.+)$/', $src, $matches ) )
					{
						$imageData = base64_decode( $matches[2] );
						$tempFile = tempnam( sys_get_temp_dir(), 'advrep_img_' );
						file_put_contents( $tempFile, $imageData );
						$section->addImage( $tempFile );
						unlink( $tempFile );
					}
				}
				else
				{
					// External image URL
					$section->addImage( $src );
				}
			}
			break;

		case 'table':
			// Create a table
			$table = $section->addTable( [ 'borderSize' => 6, 'borderColor' => '000000', 'cellMargin' => 50 ] );
			foreach ( $node->childNodes as $childNode )
			{
				processTableNode( $childNode, $table, $phpWord );
			}
			break;

		case 'ul':
		case 'ol':
			foreach ( $node->childNodes as $childNode )
			{
				processListNode( $childNode, $section, $phpWord, $tagName === 'ol' );
			}
			break;

		default:
			// For other tags, process children
			foreach ( $node->childNodes as $childNode )
			{
				processNode( $childNode, $section, $phpWord );
			}
			break;
	}
}

function processTableNode( $node, $table, $phpWord )
{
	if ( $node->nodeType !== XML_ELEMENT_NODE )
	{
		return;
	}

	$tagName = strtolower( $node->nodeName );

	if ( $tagName === 'tr' )
	{
		$row = $table->addRow();
		foreach ( $node->childNodes as $cellNode )
		{
			if ( $cellNode->nodeType === XML_ELEMENT_NODE )
			{
				$cellTag = strtolower( $cellNode->nodeName );
				if ( in_array( $cellTag, [ 'td', 'th' ] ) )
				{
					$cell = $row->addCell( 2000 );
					foreach ( $cellNode->childNodes as $childNode )
					{
						processNode( $childNode, $cell, $phpWord );
					}
				}
			}
		}
	}
	elseif ( in_array( $tagName, [ 'thead', 'tbody', 'tfoot' ] ) )
	{
		foreach ( $node->childNodes as $childNode )
		{
			processTableNode( $childNode, $table, $phpWord );
		}
	}
}

// Process the DOM
foreach ( $dom->childNodes as $node )
{
	processNode( $node, $section, $phpWord );
}


// Generate the filename and output the Word document.
if ( $isInternalRequest )
{
	ob_start();
	$objWriter = \PhpOffice\PhpWord\IOFactory::createWriter( $phpWord, 'Word2007' );
	$objWriter->save( 'php://output' );
	$docxData = ob_get_clean();
	echo $docxData;
	return;
}

$queryDev = $module->query( 'SELECT value FROM redcap_config WHERE field_name = ?',
                            [ 'is_development_server' ] );
$isDev = $queryDev->fetch_row();
$isDev = $isDev[0] == '1';

header( 'Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document' );
header( 'Content-Disposition: attachment; filename="' .
        trim( preg_replace( '/[^A-Za-z0-9-]+/', '_', \REDCap::getProjectTitle() ), '_-' ) . '_' .
        preg_replace( '/[^A-Za-z0-9-]+/', '_', $reportID ) . '_' .
        gmdate( 'Ymd-His' ) . ( $isDev ? '_dev' : '' ) . '.docx"' );

$objWriter = \PhpOffice\PhpWord\IOFactory::createWriter( $phpWord, 'Word2007' );
$objWriter->save( 'php://output' );
