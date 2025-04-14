<?php
/**
 *	Record Table Reports view page.
 */

namespace Nottingham\AdvancedReports;
const TVALIDSTR = 'text_validation_type_or_show_slider_number';



// Verify the report exists, is a project query, and is visible.
// Redirect to main reports page if not.
$reportID = $_GET['report_id'];
$listReports = $module->getReportList();
if ( ! isset( $listReports[$reportID] ) || $listReports[$reportID]['type'] != 'recordtbl' )
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

$userRights = $module->getUser()->getRights();
$exportRight = $userRights['data_export_tool'];

$listEvents = \REDCap::isLongitudinal() ? \REDCap::getEventNames( true ) : [];
$listForms = ( empty( $reportData['forms'] ) ? array_keys( \REDCap::getInstrumentNames() )
                                             : $reportData['forms'] );

$recordIDField = \REDCap::getRecordIdField();

$displayFields = [ $recordIDField, 'redcap_data_access_group' ];
$listDateFields = [];
$listOptionFields = [];
foreach ( $listForms as $formName )
{
	$listFormFields = \REDCap::getDataDictionary( 'array', false, null, $formName );
	$formExportRight = $exportRight;
	if ( isset( $userRights['data_export_instruments'] ) )
	{
		$formExportRight = strpos( $userRights['data_export_instruments'], '[' . $formName . ',' );
		if ( $formExportRight === false )
		{
			$formExportRight = $exportRight;
		}
		else
		{
			$formExportRight = substr( $userRights['data_export_instruments'],
			                           $formExportRight + strlen( $formName ) + 2, 1 );
		}
	}
	if ( $formExportRight == '1' ) // full data set
	{
		$displayFields = array_merge( $displayFields, \REDCap::getFieldNames( $formName ) );
		if ( ! $isCsvDownload )
		{
			foreach ( $listFormFields as $fieldName => $infoField )
			{
				if ( $infoField['field_type'] == 'text' &&
				     ( substr( $infoField[TVALIDSTR], 0, 5 ) == 'date_' ||
				       substr( $infoField[TVALIDSTR], 0, 9 ) == 'datetime_' ||
				       in_array( $infoField[TVALIDSTR], [ 'time', 'time_hh_mm_ss' ] ) ) )
				{
					$listDateFields[] = $fieldName;
				}
				elseif ( in_array( $infoField['field_type'], [ 'dropdown', 'radio' ] ) )
				{
					$listOptionFields[ $fieldName ] = $module->getChoiceLabels( $fieldName );
				}
			}
		}
	}
	elseif ( $formExportRight == '2' || $formExportRight == '3' ) // de-identified / remove id's
	{
		foreach ( $listFormFields as $fieldName => $infoField )
		{
			if ( $infoField['identifier'] == 'y' || $infoField['field_type'] == 'descriptive' )
			{
				continue;
			}
			if ( $formExportRight == '3' ||
			     in_array( $infoField['field_type'],
			               [ 'dropdown', 'radio', 'checkbox', 'calc', 'sql', 'slider',
			                 'yesno', 'truefalse' ] ) ||
			     ( $infoField['field_type'] == 'text' &&
			       in_array( str_replace( '_comma_decimal', '', $infoField[TVALIDSTR] ),
			                 [ 'integer', 'number', 'number_1dp', 'number_2dp',
			                   'number_3dp', 'number_4dp' ] ) ) )
			{
				$displayFields[] = $fieldName;
				if ( ! $isCsvDownload && $infoField['field_type'] == 'text' &&
				     ( substr( $infoField[TVALIDSTR], 0, 5 ) == 'date_' ||
				       substr( $infoField[TVALIDSTR], 0, 9 ) == 'datetime_' ||
				       in_array( $infoField[TVALIDSTR], [ 'time', 'time_hh_mm_ss' ] ) ) )
				{
					$listDateFields[] = $fieldName;
				}
				elseif ( ! $isCsvDownload &&
				         in_array( $infoField['field_type'], [ 'dropdown', 'radio' ] ) )
				{
					$listOptionFields[ $fieldName ] = $module->getChoiceLabels( $fieldName );
				}
			}
		}
		$displayFields[] = $formName . '_complete';
	}
}

$displayEvents = empty( $reportData['events'] ) ? null : $reportData['events'];

$displayGroup = $userRights['group_id'] == '' ? null : $userRights['group_id'];

$listData = \REDCap::getData( [ 'return_format' => 'array', 'combine_checkbox_values' => false,
                                'exportDataAccessGroups' => true, 'exportSurveyFields' => true,
                                'fields' => $displayFields, 'events' => $displayEvents,
                                'groups' => $displayGroup, 'returnBlankForGrayFormStatus' => true,
                                'removeMissingDataCodes' => $reportData['nomissingdatacodes'] ] );

$listColumns = [];
$resultTable = [];
$showEventNames = ( count( $listEvents ) > 1 );

foreach ( $listData as $infoRecord )
{
	$resultRow = [];
	foreach ( $infoRecord as $eventID => $listFields )
	{
		if ( $eventID == 'repeat_instances' )
		{
			$listRepeat = $listFields;
			foreach ( $listRepeat as $eventID => $listRepeatForm )
			{
				foreach ( $listRepeatForm as $listRepeatInstance )
				{
					foreach ( $listRepeatInstance as $instanceID => $listFields )
					{
						foreach ( $listFields as $fieldName => $fieldValue )
						{
							if ( $fieldName == $recordIDField ||
							     $fieldName == 'redcap_data_access_group' )
							{
								$columnName = $fieldName;
								$listColumns[ $columnName ] = true;
								$resultRow[ $columnName ] = $fieldValue;
							}
							elseif ( is_array( $fieldValue ) )
							{
								$fieldOptions = $fieldValue;
								foreach ( $fieldOptions as $fieldOption => $fieldValue )
								{
									$columnName = ( $showEventNames ? ( $eventName . '__' ) : '' ) .
									              $fieldName . '__' . $instanceID . '___' .
									              $fieldOption;
									$listColumns[ $columnName ] = true;
									$resultRow[ $columnName ] = $fieldValue;
								}
							}
							else
							{
								$columnName = ( $showEventNames ? ( $eventName . '__' ) : '' ) .
								              $fieldName . '__' . $instanceID;
								$listColumns[ $columnName ] = true;
								if ( $fieldValue != '' )
								{
									if ( in_array( $fieldName, $listDateFields ) )
									{
										$resultRow[ $columnName ] =
											\DateTimeRC::format_ts_from_ymd( $fieldValue,
											                                 false, true );
									}
									elseif ( isset( $listOptionFields[ $fieldName ] ) )
									{
										$resultRow[ $columnName ] =
											$listOptionFields[ $fieldName ][ $fieldValue ] .
											' (' . $fieldValue . ')';
									}
									else
									{
										$resultRow[ $columnName ] = $fieldValue;
									}
								}
							}
						}
					}
				}
			}
			continue;
		}
		$eventName = $listEvents[ $eventID ];
		foreach ( $listFields as $fieldName => $fieldValue )
		{
			if ( $fieldName == $recordIDField || $fieldName == 'redcap_data_access_group' )
			{
				$columnName = $fieldName;
				$listColumns[ $columnName ] = true;
				$resultRow[ $columnName ] = $fieldValue;
			}
			elseif ( is_array( $fieldValue ) )
			{
				$fieldOptions = $fieldValue;
				foreach ( $fieldOptions as $fieldOption => $fieldValue )
				{
					$columnName = ( $showEventNames ? ( $eventName . '__' ) : '' ) .
					              $fieldName . '__1___' . $fieldOption;
					$listColumns[ $columnName ] = true;
					$resultRow[ $columnName ] = $fieldValue;
				}
			}
			else
			{
				$columnName = ( $showEventNames ? ( $eventName . '__' ) : '' ) . $fieldName . '__1';
				$listColumns[ $columnName ] = true;
				if ( $fieldValue != '' )
				{
					if ( in_array( $fieldName, $listDateFields ) )
					{
						$resultRow[ $columnName ] =
							\DateTimeRC::format_ts_from_ymd( $fieldValue, false, true );
					}
					elseif ( isset( $listOptionFields[ $fieldName ] ) )
					{
						$resultRow[ $columnName ] = $listOptionFields[ $fieldName ][ $fieldValue ] .
						                            ' (' . $fieldValue . ')';
					}
					else
					{
						$resultRow[ $columnName ] = $fieldValue;
					}
				}
			}
		}
	}
	$resultTable[] = $resultRow;
}
$listColumns = array_keys( $listColumns );



// Handle report download.
if ( $isCsvDownload )
{
	$module->writeCSVDownloadHeaders( $reportID );
	$firstField = true;
	foreach ( $listColumns as $fieldName )
	{
		echo $firstField ? '' : ',';
		$firstField = false;
		echo '"';
		$module->echoText( str_replace( '"', '""', $fieldName ) );
		echo '"';
	}
	foreach ( $resultTable as $resultRow )
	{
		echo "\n";
		$firstField = true;
		foreach ( $listColumns as $fieldName )
		{
			echo $firstField ? '' : ',';
			$firstField = false;
			echo '"', str_replace( '"', '""',
			                       $module->escapeHTML( $resultRow[ $fieldName ] ?? '' ) ), '"';
		}
	}
	exit;
}



// Handle retrieve report as image.
if ( isset( $_GET['as_image']) && $reportConfig['as_image'] )
{
	header( 'Content-Type: image/png' );
	$img = $module->reportImageCreate();
	foreach ( [ 'reportImageRowPrepare', 'reportImageRowWrite' ] as $imageRowFunc )
	{
		// Prepare/draw the header row.
		$module->$imageRowFunc( $img, $listColumns );
		// Prepare/draw each row of data.
		foreach ( $resultTable as $resultRow )
		{
			$imgRow = [];
			foreach ( $listColumns as $columnName )
			{
				$imgRow[] = isset( $resultRow[$columnName] )
				            ? $module->escapeHTML( $resultRow[$columnName] ) : '';
			}
			$module->$imageRowFunc( $img, $imgRow );
		}
	}
	// Output the image as a PNG and exit.
	$module->reportImageOutput( $img );
	exit;
}



// Display the project header and report navigation links.
$module->writePageHeader();
$module->outputViewReportHeader( $reportConfig['label'], 'recordtbl',
                                 [ 'canReset' => true, 'asImage' => $reportConfig['as_image'] ] );

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
	foreach ( $listColumns as $fieldName )
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
	foreach ( $listColumns as $fieldName )
	{
?>
   <td><?php echo $module->escapeHTML( $resultRow[ $fieldName ] ); ?></td>
<?php
	}
?>
  </tr>
<?php
}
if ( $rowCount == 0 )
{
?>
  <tr><td>No rows returned</td></tr>
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
$module->writePageFooter();
