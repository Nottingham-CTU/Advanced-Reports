<?php
/**
 *	Project Query Reports view page.
 */

namespace Nottingham\AdvancedReports;
const TVALIDSTR = 'text_validation_type_or_show_slider_number';



// Verify the report exists, is a project query, and is visible.
// Redirect to main reports page if not.
$reportID = $_GET['report_id'];
$listReports = $module->getReportList();
if ( ! isset( $listReports[$reportID] ) || $listReports[$reportID]['type'] != 'instrument' )
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

$isCsvDownload = ( isset( $_GET['download'] ) && $module->isReportDownloadable( $reportID ) );

// Get the report data.
$reportConfig = $listReports[$reportID];
$reportData = $module->getReportData( $reportID );
$resultParams = [ 'return_format' => 'json-array', 'combine_checkbox_values' => true,
                  'exportDataAccessGroups' => true, 'exportSurveyFields' => true,
                  'returnBlankForGrayFormStatus' => true,
                  'removeMissingDataCodes' => $reportData['nomissingdatacodes'] ];
$redcapFields = [ 'redcap_event_name', 'redcap_repeat_instance', 'redcap_data_access_group' ];

function getInstrumentData( $fields, $labels )
{
	if ( \REDCap::versionCompare( REDCAP_VERSION, '12.5.2', '<' ) )
	{
		$GLOBALS['resultParams']['return_format'] = 'json';
		return json_decode( \REDCap::getData( $GLOBALS['resultParams'] +
	                                 [ 'exportAsLabels' => $labels, 'fields' => $fields ] ), true );
	}
	return \REDCap::getData( $GLOBALS['resultParams'] +
	                         [ 'exportAsLabels' => $labels, 'fields' => $fields ] );
}

// Build the result table.
$resultTable = [[]];
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
	if ( ! $isCsvDownload )
	{
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
	$newResultTable = [];
	foreach ( $resultTable as $resultRow )
	{
		foreach ( $formValues as $i => $formValuesRow )
		{
			if ( $formValuesRow[ $form . '_complete' ] === '' )
			{
				continue;
			}
			$formLabelsRow = $formLabels[$i];
			foreach ( $formLabelsRow as $fieldName => $value )
			{
				if ( in_array( $fieldName, $dateFields ) )
				{
					$formLabelsRow[ $fieldName ] =
							\DateTimeRC::format_ts_from_ymd( $value, false, true );
				}
			}
			// Check if the row from this form should be joined with the result table row.
			$doJoin = true;
			if ( $queryForm['on'] != '' )
			{
				list( $joinFunction, $joinParamData ) =
					$module->parseLogic( $queryForm['on'], $isCsvDownload );
				$joinParams = [];
				foreach ( $joinParamData as $joinParamItem )
				{
					if ( $joinParamItem[0] == $alias )
					{
						if ( $joinParamItem[2] == 'label' )
						{
							$joinParams[] = $formLabelsRow[ $joinParamItem[1] ];
						}
						else
						{
							$joinParams[] = $formValuesRow[ $joinParamItem[1] ];
						}
					}
					else
					{
						$joinParams[] = $resultRow[ '[' . $joinParamItem[0] . '][' .
						                            $joinParamItem[1] . ']' ][
						                              $joinParamItem[2] == 'label'
						                              ? 'label' : 'value' ];
					}
				}
				$doJoin = $joinFunction( ...$joinParams );
			}
			// Join the rows if required.
			if ( $doJoin )
			{
				$newResultRow = $resultRow;
				$insertedRedcapFields = false;
				foreach ( $fields as $field )
				{
					$newResultRow[ '[' . $alias . '][' . $field . ']' ] =
						[ 'value' => $formValuesRow[$field], 'label' => $formLabelsRow[$field] ];
					if ( ! $insertedRedcapFields )
					{
						foreach ( $redcapFields as $field )
						{
							if ( isset( $formValuesRow[$field] ) )
							{
								$newResultRow[ '[' . $alias . '][' . $field . ']' ] =
										[ 'value' => $formValuesRow[$field],
										  'label' => $formLabelsRow[$field] ];
							}
						}
						$insertedRedcapFields = true;
					}
				}
				$newResultTable[] = $newResultRow;
			}
		}
	}
	$resultTable = &$newResultTable;
	unset( $newResultTable );
}

// Run any where condition.
if ( $reportData['where'] != '' )
{
	$newResultTable = [];
	list( $whereFunction, $whereParamData ) =
		$module->parseLogic( $reportData['where'], $isCsvDownload );
	foreach ( $resultTable as $resultRow )
	{
		$whereParams = [];
		foreach ( $whereParamData as $whereParamItem )
		{
			$whereParams[] = $resultRow[ '[' . $whereParamItem[0] . '][' .
			                             $whereParamItem[1] . ']' ][
			                               $whereParamItem[2] == 'label'
			                               ? 'label' : 'value' ];
		}
		if ( $whereFunction( ...$whereParams ) )
		{
			$newResultTable[] = $resultRow;
		}
	}
	$resultTable = &$newResultTable;
	unset( $newResultTable );
}

// Perform any sorting.
if ( $reportData['orderby'] != '' )
{
	$sortDirection = 1;
	if ( strtolower( substr( rtrim( $reportData['orderby'] ), -5 ) ) == ' desc' )
	{
		$sortDirection = -1;
		$reportData['orderby'] = substr( rtrim( $reportData['orderby'] ), 0, -5 );
	}
	list( $sortFunction, $sortParamData ) =
		$module->parseLogic( $reportData['orderby'], $isCsvDownload );
	usort( $resultTable, function ( $resultRow1, $resultRow2 )
	                     use ( $sortFunction, $sortParamData, $sortDirection )
	{
		$sortParams1 = [];
		$sortParams2 = [];
		foreach ( $sortParamData as $sortParamItem )
		{
			$sortParams1[] = $resultRow1[ '[' . $sortParamItem[0] . '][' . $sortParamItem[1] . ']' ]
			                            [ $sortParamItem[2] == 'label' ? 'label' : 'value' ];
			$sortParams2[] = $resultRow2[ '[' . $sortParamItem[0] . '][' . $sortParamItem[1] . ']' ]
			                            [ $sortParamItem[2] == 'label' ? 'label' : 'value' ];
		}
		$sortValue1 = $sortFunction( ...$sortParams1 );
		$sortValue2 = $sortFunction( ...$sortParams2 );
		if ( $sortValue1 == $sortValue2 )
		{
			return 0;
		}
		return ( $sortValue1 < $sortValue2 ? -1 : 1 ) * $sortDirection;
	} );
}

// If fields to select specified, select them.
if ( ! empty( $reportData['select'] ) )
{
	$newResultTable = [];
	$selectFields = [];
	foreach ( $reportData['select'] as $selectField )
	{
		if ( $selectField['alias'] == '' )
		{
			$selectField['alias'] = $selectField['field'];
		}
		$selectFields[] = [ 'field' => $selectField['field'], 'alias' => $selectField['alias'],
		                    'function' => $module->parseLogic( $selectField['field'],
		                                                       $isCsvDownload ) ];
	}
	foreach ( $resultTable as $resultRow )
	{
		$newResultRow = [];
		foreach ( $selectFields as $selectField )
		{
			$selectParams = [];
			foreach ( $selectField['function'][1] as $selectParamItem )
			{
				$selectParams[] = $resultRow[ '[' . $selectParamItem[0] . '][' .
			                                  $selectParamItem[1] . ']' ][
			                                    $selectParamItem[2] == 'value'
			                                    ? 'value' : 'label' ];
			}
			$newResultRow[ $selectField['alias'] ] =
					$selectField['function'][0]( ...$selectParams );
		}
		$newResultTable[] = $newResultRow;
	}
	$resultTable = &$newResultTable;
	unset( $newResultTable );
}



// Handle report download.
if ( $isCsvDownload )
{
	$module->writeCSVDownloadHeaders( $reportID );
	$firstRow = true;
	foreach ( $resultTable as $resultRow )
	{
		if ( $firstRow )
		{
			$firstRow = false;
			$firstField = true;
			foreach ( $resultRow as $fieldName => $value )
			{
				echo $firstField ? '' : ',';
				$firstField = false;
				echo '"';
				$module->echoText( str_replace( '"', '""', $fieldName ) );
				echo '"';
			}
		}
		echo "\n";
		$firstField = true;
		foreach ( $resultRow as $value )
		{
			if ( is_array( $value ) )
			{
				$value = $value['label'];
			}
			echo $firstField ? '' : ',';
			$firstField = false;
			echo '"', str_replace( '"', '""', $module->parseHTML( $value, true ) ), '"';
		}
	}
	exit;
}



// Handle retrieve report as image.
if ( isset( $_GET['as_image']) && $reportConfig['as_image'] )
{
	header( 'Content-Type: image/png' );
	// Determine the fonts and character sizes for the report.
	$imgHeaderFont = 5;
	$imgDataFont = 4;
	$imgHeaderCharW = imagefontwidth( $imgHeaderFont );
	$imgHeaderCharH = imagefontheight( $imgHeaderFont );
	$imgDataCharW = imagefontwidth( $imgDataFont );
	$imgDataCharH = imagefontheight( $imgDataFont );
	$imgHeaderH = $imgHeaderCharH + 2;
	$imgDataH = $imgDataCharH + 2;
	// Get all the column names.
	$columns = [];
	if ( ! empty( $resultTable ) )
	{
		foreach ( $resultTable[0] as $fieldName => $value )
		{
			$columns[] = $fieldName;
		}
	}
	// Calculate column widths based on column name string lengths.
	$imgColumnWidths = [];
	foreach ( $columns as $columnName )
	{
		$imgColumnWidths[ $columnName ] = ( strlen( $columnName ) * $imgHeaderCharW ) + 5;
	}
	// Check the data in each column for each record, increase the column widths if necessary.
	foreach ( $resultTable as $resultRow )
	{
		foreach ( $columns as $columnName )
		{
			$imgParsedData = isset( $resultRow[$columnName] )
			                    ? $module->parseHTML( $resultRow[$columnName], true ) : '';
			$thisWidth = ( strlen( $imgParsedData ) * $imgDataCharW ) + 5;
			if ( $imgColumnWidths[$columnName] < $thisWidth )
			{
				$imgColumnWidths[$columnName] = $thisWidth;
			}
		}
	}
	// Calculate the image dimensions, create the image, and set the colours (black/white).
	$imgWidth = array_sum( $imgColumnWidths ) + 1;
	$imgHeight = $imgHeaderH + ( count( $resultTable ) * ( $imgDataH ) ) + 1;
	$img = imagecreate( $imgWidth, $imgHeight );
	imagecolorallocate( $img, 255, 255, 255 );
	$imgBlack = imagecolorallocate( $img, 0, 0, 0 );
	// Draw the column headers.
	$posW = 0;
	$posH = 0;
	foreach ( $columns as $columnName )
	{
		$thisWidth = $imgColumnWidths[$columnName];
		imagerectangle( $img, $posW, $posH, $posW + $thisWidth, $posH + $imgHeaderH, $imgBlack );
		imagestring( $img, $imgHeaderFont, $posW + 2, $posH + 1, $columnName, $imgBlack );
		$posW += $thisWidth;
	}
	// Draw each row of data.
	$posW = 0;
	$posH += $imgHeaderH;
	foreach ( $resultTable as $resultRow )
	{
		foreach ( $columns as $columnName )
		{
			$imgParsedData = isset( $resultRow[$columnName] )
			                    ? $module->parseHTML( $resultRow[$columnName], true ) : '';
			$thisWidth = $imgColumnWidths[$columnName];
			imagerectangle( $img, $posW, $posH, $posW + $thisWidth, $posH + $imgDataH, $imgBlack );
			imagestring( $img, $imgDataFont, $posW + 2, $posH + 1, $imgParsedData, $imgBlack );
			$posW += $thisWidth;
		}
		$posW = 0;
		$posH += $imgDataH;
	}
	// Output the image as a PNG and exit.
	imagepng( $img );
	exit;
}



// Display the project header and report navigation links.

require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$module->outputViewReportHeader( $reportConfig['label'], 'instrument', true );

// Initialise the row counter.
$rowCount = 0;


// If a description is provided, output it here.
if ( isset( $reportData['desc'] ) && $reportData['desc'] != '' )
{
?>
<p class="mod-advrep-description"><?php
	echo $module->parseDescription( $reportData['desc'] ); ?></p>
<?php
}


?>
<table id="mod-advrep-table" class="mod-advrep-datatable dataTable">
 <thead>
  <tr>
<?php
if ( count( $resultTable ) > 0 )
{
	foreach ( $resultTable[0] as $fieldName => $value )
	{
?>
   <th class="sorting"><?php echo $module->escapeHTML( $fieldName ); ?></th>
<?php
	}
}
?>
  </tr>
 </thead>
 <tbody>
<?php
foreach ( $resultTable as $resultRow )
{
	$rowCount++;
?>
  <tr>
<?php
	foreach ( $resultRow as $value )
	{
		if ( is_array( $value ) )
		{
			$value = $value['label'];
		}
?>
   <td><?php echo $module->parseHTML( $value ); ?></td>
<?php
	}
	if ( $rowCount == 0 )
	{
?>
  <tr><td>No rows returned</td></tr>
<?php
	}
?>
  </tr>
<?php
}
?>
 </tbody>
</table>
<?php

if ( $rowCount > 0 )
{
?>
<p>Total rows returned: <span id="filtercount"></span><?php echo $rowCount; ?></p>
<?php
}


$module->outputViewReportJS();


// Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';