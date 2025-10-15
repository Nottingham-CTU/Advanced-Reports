<?php
/**
 *	Instrument Query Reports view page.
 */

namespace Nottingham\AdvancedReports;
const TVALIDSTR = 'text_validation_type_or_show_slider_number';



// Verify the report exists, and is a system query.
// Redirect to main reports page if not.
$reportID = $_GET['report_id'];
$listReports = $module->getReportList();
if ( ! isset( $listReports[$reportID] ) || $listReports[$reportID]['type'] != 'system' )
{
	header( 'Location: ' . $module->getUrl( 'reports.php' ) );
	exit;
}


// Determine the request type (normal/csv/api).
$disableAccessControl = isset( $disableAccessControl ) ? $disableAccessControl : false;
$isApiRequest = isset( $isApiRequest ) ? $isApiRequest : false;
$isInternalRequest = isset( $isInternalRequest ) ? $isInternalRequest : false;
$isCsvDownload = ( ! $isApiRequest && isset( $_GET['download'] ) &&
                   ( $isInternalRequest || $module->isReportDownloadable( $reportID ) ) );
$requestType = ( ( $isApiRequest && ! $isInternalRequest )
                 ? 'api' : ( $isCsvDownload ? 'download' : false ) );


// Check user can view this report, redirect to main reports page if not.
if ( ! $disableAccessControl && ! $isApiRequest && ! $module->isReportAccessible( $reportID ) )
{
	header( 'Location: ' . $module->getUrl( 'reports.php' ) );
	exit;
}


// Get the report data.
$reportConfig = $listReports[$reportID];
$reportData = $module->getReportData( $reportID );
$resultParams = [ 'return_format' => 'json-array', 'combine_checkbox_values' => true,
                  'exportDataAccessGroups' => true, 'exportSurveyFields' => true,
                  'returnBlankForGrayFormStatus' => true,
                  'removeMissingDataCodes' => $reportData['nomissingdatacodes'] ];
$redcapFields = [ 'redcap_event_name', 'redcap_repeat_instance', 'redcap_data_access_group' ];
$recordIDField = \REDCap::getRecordIdField();

// Get the project IDs for each alias.
$listProjects = [];
foreach ( $reportData['projs'] as $infoProject )
{
	if ( $infoProject['lu'] == 'this' )
	{
		$listProjects[ $infoProject['alias'] ] = $module->getProjectId();
	}
	elseif ( $infoProject['lu'] == 'title-append' )
	{
		$queryProject = $module->query( "SELECT project_id FROM redcap_projects WHERE app_title =" .
		                                " concat((SELECT app_title FROM redcap_projects WHERE " .
		                                "project_id = ? LIMIT 1), ' ', ?) LIMIT 1",
		                                [ $module->getProjectId(), $infoProject['luv'] ] )
		                                ->fetch_assoc();
		if ( $queryProject !== null )
		{
			$listProjects[ $infoProject['alias'] ] = $queryProject['project_id'];
		}
	}
	elseif ( $infoProject['lu'] == 'title' )
	{
		$queryProject = $module->query( 'SELECT project_id FROM redcap_projects ' .
		                                'WHERE app_title = ? LIMIT 1',
		                                [ $infoProject['luv'] ] )->fetch_assoc();
		if ( $queryProject !== null )
		{
			$listProjects[ $infoProject['alias'] ] = $queryProject['project_id'];
		}
	}
	elseif ( $infoProject['lu'] == 'ptitle' )
	{
		$queryProject = $module->query( 'SELECT project_id FROM redcap_projects ' .
		                                'WHERE app_title LIKE ? LIMIT 1',
		                                [ '%' . str_replace( ['%', '_'], ['\\%', '\\_'],
		                                                     $infoProject['luv'] ) . '%' ] )
		                                ->fetch_assoc();
		if ( $queryProject !== null )
		{
			$listProjects[ $infoProject['alias'] ] = $queryProject['project_id'];
		}
	}
	elseif ( $infoProject['lu'] == 'notes' )
	{
		$queryProject = $module->query( 'SELECT project_id FROM redcap_projects ' .
		                                'WHERE project_note = ? LIMIT 1',
		                                [ $infoProject['luv'] ] )->fetch_assoc();
		if ( $queryProject !== null )
		{
			$listProjects[ $infoProject['alias'] ] = $queryProject['project_id'];
		}
	}
	elseif ( $infoProject['lu'] == 'pnotes' )
	{
		$queryProject = $module->query( 'SELECT project_id FROM redcap_projects ' .
		                                'WHERE project_note LIKE ? LIMIT 1',
		                                [ '%' . str_replace( ['%', '_'], ['\\%', '\\_'],
		                                                     $infoProject['luv'] ) . '%' ] )
		                                ->fetch_assoc();
		if ( $queryProject !== null )
		{
			$listProjects[ $infoProject['alias'] ] = $queryProject['project_id'];
		}
	}
}



// Map the form alias to the table/instrument name.
$listAliasForms = [];
foreach ( $reportData['forms'] as $queryForm )
{
	$listAliasForms[ $queryForm['alias'] ] = $queryForm['form'];
}

// If there are no fields to display specified, all fields will be returned, so no need to check
// them. Otherwise, proceed with checking which fields are used...
$listReferencedFields = [];
if ( ! empty( $reportData['select'] ) )
{
	$refParams = [];
	foreach ( $reportData['forms'] as $queryForm )
	{
		// Always include the record ID and formname_complete fields.
		$listReferencedFields[ $queryForm['alias'] == ''
		                       ? $queryForm['form']
		                       : $queryForm['alias'] ][ $recordIDField ] = true;
		$listReferencedFields[ $queryForm['alias'] == ''
		                       ? $queryForm['form']
		                       : $queryForm['alias'] ][ $queryForm['form'] . '_complete' ] = true;
		// Check the form's join on logic for field references.
		if ( $queryForm['on'] != '' )
		{
			$refParams = array_merge( $refParams,
			                          $module->parseLogic( $queryForm['on'], $requestType )[1] );
		}
	}
	// Check the where logic for field references.
	if ( $reportData['where'] != '' )
	{
		$refParams = array_merge( $refParams,
								  $module->parseLogic( $reportData['where'], $requestType )[1] );
	}
	// Check the order by logic for field references.
	if ( $reportData['orderby'] != '' )
	{
		$refOrderby = $reportData['orderby'];
		if ( strtolower( substr( rtrim( $reportData['orderby'] ), -5 ) ) == ' desc' )
		{
			$refOrderby = substr( rtrim( $refOrderby ), 0, -5 );
		}
		$refParams = array_merge( $refParams, $module->parseLogic( $refOrderby, $requestType )[1] );
	}
	// Check each field to display for field references. Note that a field to display is not
	// necessarily a single field, it can contain logic.
	foreach ( $reportData['select'] as $selectField )
	{
		$refParams = array_merge( $refParams, $module->parseLogic( $selectField['field'],
		                                                           $requestType, true )[1] );
	}
	// Each returned $refParam has the instrument alias at index 0 and the field name at index 1.
	// Use this to build a list of the referenced fields grouped by instrument alias.
	foreach ( $refParams as $refParam )
	{
		if ( in_array( $refParam[1], $redcapFields2 ) )
		{
			// Virtual fields added by instrument queries are listed under the instrument name
			// rather than the alias.
			$refParam[0] = $listAliasForms[ $refParam[0] ];
		}
		$listReferencedFields[ $refParam[0] ][ $refParam[1] ] = true;
	}
}



// Get event names, DAG names, redcap_data table and redcap_log_event table for the project.
$listEventNames = \REDCap::getEventNames( true );
$listDAGUniqueNames = \REDCap::getGroupNames( true );
$listDAGFullNames = \REDCap::getGroupNames( false );
$dataTable = method_exists( '\REDCap', 'getDataTable' )
                ? \REDCap::getDataTable( $module->getProjectId() ) : ( 'redcap' . '_data' );
$logEventTable = \REDCap::getLogEventTable( $module->getProjectId() );



// Build the result table for the system query.
$resultTable = [[]];
// For each instrument in the query...
foreach ( $reportData['forms'] as $queryForm )
{
	// Get the form name and alias (use form name for alias if not defined).
	$form = $queryForm['form'];
	$alias = $queryForm['alias'];

	// Check a valid project is referenced.
	if ( ! isset( $listProjects[ $queryForm['proj'] ] ) )
	{
		continue;
	}
	$projectID = $listProjects[ $queryForm['proj'] ];

	// Get the fields for the form and retrieve the values and value labels for each record.
	if ( substr( $form, 0, 1 ) == '_' ) // a database table
	{
		// Get the date/datetime fields for the database table and mark them as such so format
		// transformations can be applied.
		$fieldMetadata = [];
		$queryFieldMetadata = $module->query( "SELECT COLUMN_NAME " .
		                                      "FROM INFORMATION_SCHEMA.COLUMNS " .
		                                      "WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? " .
		                                      "AND DATA_TYPE IN('date','datetime')",
		                                      [ $GLOBALS['db'], 'redcap' . $form ] );
		while ( $infoFieldMetadata = $queryFieldMetadata->fetch_assoc() )
		{
			$fieldMetadata[ $infoFieldMetadata['COLUMN_NAME'] ] =
					[ 'field_type' => 'text', TVALIDSTR => 'time' ];
		}
		// Get the data from the database table for the project.
		$recordIDField = null;
		$fields = [];
		$formValues = [];
		$formLabels = [];
		if ( preg_match( '/[^a-z0-9_]/', $form ) === 0 )
		{
			if ( $form == '_log_event' )
			{
				$form = preg_replace( '/^redcap/', '', \REDCap::getLogEventTable( $projectID ) );
			}
			$queryDBTable = $module->query( "SELECT * FROM redcap" . $form .
			                                " WHERE project_id = ?", [ $projectID ] );
			while ( $infoDBTable = $queryDBTable->fetch_assoc() )
			{
				unset( $infoDBTable['project_id'] );
				if ( ! empty( $listReferencedFields ) )
				{
					foreach ( array_keys( $infoDBTable ) as $fieldName )
					{
						if ( ! isset( $listReferencedFields[ $alias ][ $fieldName ] ) )
						{
							unset( $infoDBTable[ $fieldName ] );
						}
					}
				}
				if ( empty( $fields ) )
				{
					$fields = array_keys( $infoDBTable );
				}
				$formValues[] = $infoDBTable;
				$formLabels[] = $infoDBTable;
			}
		}
	}
	else // a normal instrument in the project
	{
		// Get a list of the fields for this instrument.
		$recordIDField = $module->getRecordIdField( $projectID );
		$fields = array_unique( array_merge( [ $recordIDField ],
		                                     $module->getFieldNames( $form, $projectID ) ) );
		$fieldMetadata = \REDCap::getDataDictionary( $projectID, 'array', false, $fields );
		foreach ( $fieldMetadata as $fieldName => $fieldParams )
		{
			// Exclude any descriptive fields. If we have pre-checked which fields are referenced
			// by this query for this form alias, exclude any which are not referenced.
			if ( $fieldParams['field_type'] == 'descriptive' ||
			     ( ! empty( $listReferencedFields ) &&
			       ! isset( $listReferencedFields[ $alias ][ $fieldName ] ) ) )
			{
				unset( $fields[ array_search( $fieldName, $fields ) ] );
			}
		}
		// Run \REDCap::getData() twice, once to get the raw values and once to get the
		// corresponding value labels (i.e. for multiple choice fields).
		$formValues = \REDCap::getData( $resultParams +
		                                [ 'project_id' => $projectID,
		                                  'exportAsLabels' => false, 'fields' => $fields ] );
		$formLabels = \REDCap::getData( $resultParams +
		                                [ 'project_id' => $projectID,
		                                  'exportAsLabels' => true, 'fields' => $fields ] );
	}

	// If this isn't a CSV download, identify the date fields. The 'label' of any date fields will
	// be set to the date transformed into the user's preferred format.
	$dateFields = [];
	if ( ! $isCsvDownload && $reportData['dateformat'] ?? '' == '' )
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
	if ( ! empty( $dateFields ) )
	{
		foreach ( $formLabels as $i => $formLabelsRow )
		{
			foreach ( $formLabelsRow as $fieldName => $value )
			{
				if ( in_array( $fieldName, $dateFields ) )
				{
					$formLabels[ $i ][ $fieldName ] = $module->formatDate( $value, 'upf' );
				}
			}
		}
	}

	// Parse the join on logic. If blank, a full cross join is performed.
	if ( $queryForm['on'] != '' )
	{
		list( $joinFunction, $joinParamData ) =
			$module->parseLogic( $queryForm['on'], $requestType );
	}
	// Initialise the new result table to be populated with the existing result joined with this
	// instrument.
	$newResultTable = [];
	// For each row of the existing result...
	foreach ( $resultTable as $resultRow )
	{
		$joinCount = 0;
		$formHasRedcapFields = [];
		// For each row of the instrument being processed...
		foreach ( $formValues as $i => $formValuesRow )
		{
			// Treat a blank formname_complete field as though there is no data on this form for
			// this record and thus skip it.
			if ( substr( $form, 0, 1 ) != '_' && $formValuesRow[ $form . '_complete' ] === '' )
			{
				continue;
			}
			$formLabelsRow = $formLabels[$i];
			// Check if the row from this form should be joined with the result table row.
			$doJoin = true;
			if ( $queryForm['on'] != '' )
			{
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
				$formEvent = ( isset( $formValuesRow['redcap_event_name'] )
				               ? $formValuesRow['redcap_event_name'] : null );
				$formInstance = ( isset( $formValuesRow['redcap_repeat_instance'] )
				               ? $formValuesRow['redcap_repeat_instance'] : null );
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
								$formHasRedcapFields[ $field ] = true;
								$newResultRow[ '[' . $alias . '][' . $field . ']' ] =
										[ 'value' => $formValuesRow[$field],
										  'label' => $formLabelsRow[$field] ];
							}
						}
						$insertedRedcapFields = true;
					}
				}
				$newResultTable[] = $newResultRow;
				$joinCount++;
			}
		}
		// For left joins, always include the row from the left side of the join.
		if ( $joinCount == 0 && isset( $queryForm['join'] ) && $queryForm['join'] == 'left' )
		{
			$newResultRow = $resultRow;
			$insertedRedcapFields = false;
			foreach ( $fields as $field )
			{
				$newResultRow[ '[' . $alias . '][' . $field . ']' ] =
						[ 'value' => '', 'label' => '' ];
				if ( ! $insertedRedcapFields )
				{
					foreach ( $redcapFields as $field )
					{
						if ( isset( $formHasRedcapFields[ $field ] ) )
						{
							$newResultRow[ '[' . $alias . '][' . $field . ']' ] =
									[ 'value' => '', 'label' => '' ];
						}
					}
					$insertedRedcapFields = true;
				}
			}
			$newResultTable[] = $newResultRow;
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
		$module->parseLogic( $reportData['where'], $requestType );
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
		$module->parseLogic( $reportData['orderby'], $requestType );
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
$selectFields = [];
$hasGrouping = false;
$groupingFields = [];
if ( ! empty( $reportData['select'] ) )
{
	$newResultTable = [];
	foreach ( $reportData['select'] as $selectField )
	{
		// If no alias specified, use the field name/logic.
		if ( $selectField['alias'] == '' )
		{
			$selectField['alias'] = $selectField['field'];
		}
		// Determine if grouping is used.
		if ( isset( $selectField['grouping'] ) && $selectField['grouping'] != '' )
		{
			$hasGrouping = true;
			if ( $selectField['grouping'] == 'this' )
			{
				$groupingFields[] = $selectField['alias'];
			}
		}
		// Add to the list of select fields.
		$selectFields[] = [ 'field' => $selectField['field'], 'alias' => $selectField['alias'],
		                    'grouping' => $selectField['grouping'] ?? '',
		                    'function' => $module->parseLogic( $selectField['field'],
		                                                       $requestType, true ) ];
	}
	// Apply the select fields to each result row.
	foreach ( $resultTable as $resultRow )
	{
		$newResultRow = [];
		foreach ( $selectFields as $selectField )
		{
			// Select the field reference if the field is to be editable.
			if ( ! $hasGrouping && preg_match( '/^\\[[a-z0-9_]+\\]\\[[a-z0-9_]+\\]:edit$/i',
			                                   $selectField['field'] ) )
			{
				$selectParamItem = $selectField['function'][1][0];
				$newResultRow[ $selectField['alias'] ] = $resultRow[ '[' . $selectParamItem[0] .
				                                         '][' . $selectParamItem[1] . ']' ]['ref'];
				continue;
			}
			// Get data label or logic expression result.
			$selectParams = [];
			foreach ( $selectField['function'][1] as $selectParamItem )
			{
				// Default to selecting the label if the field is to be used by itself, or the value
				// if it is part of a logic expression.
				$defaultValOrLbl = [ 'value', 'label' ];
				if ( preg_match( '/^\\[[a-z0-9_]+\\]\\[[a-z0-9_]+\\]$/i', $selectField['field'] ) )
				{
					$defaultValOrLbl = [ 'label', 'value' ];
				}
				$selectParams[] = $resultRow[ '[' . $selectParamItem[0] . '][' .
			                                  $selectParamItem[1] . ']' ][
			                                    $selectParamItem[2] == $defaultValOrLbl[1]
			                                    ? $defaultValOrLbl[1] : $defaultValOrLbl[0] ];
			}
			$newResultRow[ $selectField['alias'] ] =
					$selectField['function'][0]( ...$selectParams );
		}
		$newResultTable[] = $newResultRow;
	}
	// Update the result table.
	$resultTable = &$newResultTable;
	unset( $newResultTable );
}



// Perform any grouping.
if ( $hasGrouping )
{
	$newResultTable = [];
	foreach ( $resultTable as $resultRow )
	{
		// Determine the grouping key based on the values of the fields to group by.
		$groupKey = json_encode( array_reduce( $groupingFields,
		                                       function ( $c, $i ) use ( $resultRow )
		                                       {
		                                           $c[] = $resultRow[ $i ];
		                                           return $c;
		                                       }, [] ) );
		// Get the result row for the group (create new rows as required).
		$newResultRow = isset( $newResultTable[ $groupKey ] ) ? $newResultTable[ $groupKey ] : [];
		foreach ( $selectFields as $selectField )
		{
			// For a new row, initialise the field.
			if ( ! isset( $newResultRow[ $selectField['alias'] ] ) )
			{
				if ( $selectField['grouping'] == 'this' )
				{
					$newResultRow[ $selectField['alias'] ] = $resultRow[ $selectField['alias'] ];
				}
				else
				{
					$newResultRow[ $selectField['alias'] ] = [];
				}
			}
			// Append the value to the group.
			if ( $selectField['grouping'] != 'this' )
			{
				$newResultRow[ $selectField['alias'] ][] = $resultRow[ $selectField['alias'] ];
			}
		}
		// Update the row in the group table.
		$newResultTable[ $groupKey ] = $newResultRow;
	}
	// Perform the grouping function(s) on the grouped values in the group table.
	foreach ( $newResultTable as $groupKey => $newResultRow )
	{
		foreach ( $selectFields as $selectField )
		{
			if ( is_array( $newResultRow[ $selectField['alias'] ] ) )
			{
				$newResultRow[ $selectField['alias'] ] =
						$module->performGrouping( $newResultRow[ $selectField['alias'] ],
						                          $selectField['grouping'] );
			}
		}
		$newResultTable[ $groupKey ] = $newResultRow;
	}
	// Update the result table.
	$newResultTable = array_values( $newResultTable );
	$resultTable = &$newResultTable;
	unset( $newResultTable );
}



// Return the result table for API requests.
if ( $isApiRequest )
{
	foreach ( $resultTable as $resultIndex => $resultRow )
	{
		foreach ( $resultRow as $fieldName => $value )
		{
			$value = is_array( $value ) ? $value['value'] : (string)$value;
			if ( $isInternalRequest )
			{
				$value = $module->formatDate( $value, $reportData['dateformat'] ?? '' );
			}
			$resultTable[ $resultIndex ][ $fieldName ] = $value;
		}
	}
	return $resultTable;
}



// Handle report download.
if ( $isCsvDownload )
{
	if ( ! $isInternalRequest )
	{
		$module->writeCSVDownloadHeaders( $reportID );
	}
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
				$value = $value['value'];
			}
			echo $firstField ? '' : ',';
			$firstField = false;
			echo '"', str_replace( '"', '""', $module->parseHTML( (string)$value, true ) ), '"';
		}
	}
	if ( $isInternalRequest )
	{
		return;
	}
	exit;
}



// Handle retrieve report as image.
if ( isset( $_GET['as_image'] ) && $reportConfig['as_image'] )
{
	if ( ! $isInternalRequest )
	{
		header( 'Content-Type: image/png' );
	}
	$img = $module->reportImageCreate();
	$columns = [];
	if ( ! empty( $resultTable ) )
	{
		foreach ( $resultTable[0] as $fieldName => $value )
		{
			$columns[] = $fieldName;
		}
	}
	foreach ( [ 'reportImageRowPrepare', 'reportImageRowWrite' ] as $imageRowFunc )
	{
		// Prepare/draw the header row.
		$module->$imageRowFunc( $img, $columns );
		// Prepare/draw each row of data.
		foreach ( $resultTable as $resultRow )
		{
			$imgRow = [];
			foreach ( $columns as $columnName )
			{
				if ( is_object( $resultRow[$columnName] ) )
				{
					$resultRow[$columnName] = $resultRow[$columnName]->getLabel();
				}
				elseif ( is_array( $resultRow[$columnName] ) )
				{
					$resultRow[$columnName] = $resultRow[$columnName]['label'];
				}
				$resultRow[$columnName] = $module->formatDate( (string)$resultRow[$columnName],
				                                               $reportData['dateformat'] ?? '' );
				$imgRow[] = isset( $resultRow[$columnName] )
				            ? $module->parseHTML( $resultRow[$columnName], true ) : '';
			}
			$module->$imageRowFunc( $img, $imgRow );
		}
	}
	// Output the image as a PNG and exit.
	$module->reportImageOutput( $img );
	if ( $isInternalRequest )
	{
		return;
	}
	exit;
}



// Display the header and report navigation links.
$module->writePageHeader( $disableAccessControl );
$module->outputViewReportHeader( $reportConfig['label'], 'system',
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
	$colNum = 0;
	foreach ( $resultTable[0] as $fieldName => $value )
	{
?>
   <th class="sorting" data-colnum="<?php echo $colNum; ?>">
    <?php echo $module->escape( $fieldName ), "\n"; ?>
   </th>
<?php
		$colNum++;
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
		if ( is_object( $value ) )
		{
			echo '<td>' . $value->makeUpdateForm() . "</td>\n";
			continue;
		}
		if ( is_array( $value ) )
		{
			$value = $value['label'];
		}
		$value = $module->formatDate( $value, $reportData['dateformat'] ?? '' );
?>
   <td><?php echo $module->parseHTML( $value ); ?></td>
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


// Display the footer
$module->writePageFooter();
