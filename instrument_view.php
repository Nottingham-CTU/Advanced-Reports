<?php
/**
 *	Instrument Query Reports view page.
 */

namespace Nottingham\AdvancedReports;
const TVALIDSTR = 'text_validation_type_or_show_slider_number';



// Verify the report exists, and is an instrument query, is visible.
// Redirect to main reports page if not.
$reportID = $_GET['report_id'];
$listReports = $module->getReportList();
if ( ! isset( $listReports[$reportID] ) || $listReports[$reportID]['type'] != 'instrument' )
{
	header( 'Location: ' . $module->getUrl( 'reports.php' ) );
	exit;
}


// Determine the request type (normal/csv/api).
$isApiRequest = isset( $isApiRequest ) ? $isApiRequest : false;
$isInternalRequest = isset( $isInternalRequest ) ? $isInternalRequest : false;
$isCsvDownload = ( ! $isApiRequest && isset( $_GET['download'] ) &&
                   $module->isReportDownloadable( $reportID ) );
$requestType = ( ( $isApiRequest && ! $isInternalRequest )
                 ? 'api' : ( $isCsvDownload ? 'download' : false ) );


// Check user can view this report, redirect to main reports page if not.
if ( ! $isApiRequest && ! $module->isReportAccessible( $reportID ) )
{
	header( 'Location: ' . $module->getUrl( 'reports.php' ) );
	exit;
}


// Define the FieldReference class used for generating editable fields.
class FieldReference
{
	private $record;
	private $fieldName;
	private $event;
	private $instance;
	private $value;
	private $label;

	public function __construct( $record, $fieldName, $event, $instance, $value, $label = '' )
	{
		$this->record = $record;
		$this->fieldName = $fieldName;
		$this->event = $event;
		$this->instance = $instance;
		$this->value = $value;
		$this->label = $label;
	}

	public function makeUpdateForm()
	{
		global $module;
		$fieldType = \REDCap::getFieldType( $this->fieldName );
		if ( ! in_array( $fieldType,
		                 [ 'text', 'notes', 'radio', 'dropdown', 'yesno', 'truefalse' ] ) )
		{
			return $module->escapeHTML( $this->value );
		}
		$output = '<form method="post" class="valedit"><input type="hidden" name="record" value="' .
		          $module->escapeHTML( $this->record ) .
		          '"><input type="hidden" name="field" value="' .
		          $module->escapeHTML( $this->fieldName ) . '">';
		if ( $this->event !== null )
		{
			$output .= '<input type="hidden" name="event" value="' .
			          $module->escapeHTML( $this->event ) . '">';
		}
		if ( $this->instance !== null )
		{
			$output .= '<input type="hidden" name="instance" value="' .
			          $module->escapeHTML( $this->instance ) . '">';
		}
		if ( $fieldType == 'text' )
		{
			$output .= '<input onchange="$(this.form).submit()" type="text" name="value" value="' .
			           $module->escapeHTML( $this->value ) . '">';
		}
		elseif ( $fieldType == 'notes' )
		{
			$output .= '<textarea onchange="$(this.form).submit()" name="value" rows="2">' .
			           $module->escapeHTML( $this->value ) . '</textarea>';
		}
		elseif ( in_array( $fieldType, [ 'radio', 'dropdown', 'yesno', 'truefalse' ] ) )
		{
			if ( $fieldType == 'yesno' )
			{
				$choices = [ '1' => $GLOBALS['lang']['design_100'],
				             '0' => $GLOBALS['lang']['design_99'] ];
			}
			elseif ( $fieldType == 'truefalse' )
			{
				$choices = [ '1' => $GLOBALS['lang']['design_186'],
				             '0' => $GLOBALS['lang']['design_187'] ];
			}
			else
			{
				$choices = $module->getChoiceLabels( $this->fieldName );
			}
			$output .= '<select onchange="$(this.form).submit()" name="value">' .
			           '<option value=""></option>';
			foreach ( $choices as $choiceCode => $choiceLabel )
			{
				$output .= '<option' . ( $choiceCode == $this->value ? ' selected' : '' ) .
				           ' value="' . $module->escapeHTML( $choiceCode ) . '">' .
				           $module->escapeHTML( $choiceLabel ) . '</option>';
			}
			$output .= '</select>';
		}
		$output .= '<input type="hidden" name="key" value="' .
		           self::makeKey( $_SESSION['redcap_csrf_token']
		                                   [array_key_last($_SESSION['redcap_csrf_token'])],
		                          $this->record, $this->fieldName, $this->event,
		                          $this->instance ) . '"></form>';
		return $output;
	}

	public function getLabel()
	{
		return $this->label;
	}

	public function __toString()
	{
		return $this->value;
	}

	public static function makeKey( $csrfToken, $record, $fieldName, $event, $instance )
	{
		return sha1( json_encode( [ $csrfToken, (string)$record, $fieldName, $event ?? '',
		                            (int)($instance ?? 1) ] ) );
	}
}


// If a value to be changed has been submitted, check the submission is valid and update the value.
if ( isset( $_POST['key'] ) &&
     $_POST['key'] == FieldReference::makeKey( $_POST['csrf_token'], $_POST['record'],
                                               $_POST['field'], $_POST['event'],
                                               $_POST['instance'] ) )
{
	$data = [ 'record' => $_POST['record'], 'field_name' => $_POST['field'],
	          'value' => $_POST['value'] ];
	if ( isset( $_POST['event'] ) )
	{
		$data[ 'redcap_event_name' ] = $_POST['event'];
	}
	if ( isset( $_POST['instance'] ) )
	{
		$instrument = $module->getFormForField( $_POST['field'] );
		$data[ 'redcap_repeat_instance' ] = $_POST['instance'];
		$data[ 'redcap_repeat_instrument' ] = $instrument;
	}
	$result = \REDCap::saveData( [ 'dataFormat' => 'json-array',
	                               'type' => 'eav', 'data' => [ $data ] ] );
	header( 'Content-Type: application/json' );
	if ( ! empty( $result['errors'] ) )
	{
		http_response_code(400);
		echo json_encode( $result['errors'] );
		exit;
	}
	echo 'true';
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
	$formValues = \REDCap::getData( $resultParams +
	                                [ 'exportAsLabels' => false, 'fields' => $fields ] );
	$formLabels = \REDCap::getData( $resultParams +
	                                [ 'exportAsLabels' => true, 'fields' => $fields ] );
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
		$joinCount = 0;
		$formHasRedcapFields = [];
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
					$module->parseLogic( $queryForm['on'], $requestType );
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
						[ 'value' => $formValuesRow[$field], 'label' => $formLabelsRow[$field],
						  'ref' => new FieldReference( $formValuesRow[\REDCap::getRecordIdField()],
						                               $field, $formEvent, $formInstance,
						                               $formValuesRow[$field],
						                               $formLabelsRow[$field] ) ];
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
						if ( ! empty( $reportData['select'] ) )
						{
							$newResultRow[ '[' . $alias . '][redcap_form_url]' ] =
								[ 'value' => APP_PATH_WEBROOT_FULL . 'redcap_v' . REDCAP_VERSION .
								             '/DataEntry/index.php?pid=' . $module->getProjectId() .
								             '&page=' . $form . '&id=' .
								             $formValuesRow[ \REDCap::getRecordIdField() ] .
								             ( isset( $formValuesRow['redcap_event_name'] )
								               ? '&event_id=' .
								                 \REDCap::getEventIdFromUniqueEvent(
								                      $formValuesRow['redcap_event_name'] ) : '' ) .
								             ( isset( $formValuesRow['redcap_repeat_instance'] )
								               ? '&instance=' .
								                 $formValuesRow['redcap_repeat_instance'] : '' ) ];
							$newResultRow[ '[' . $alias . '][redcap_form_url]' ]['label'] =
								$newResultRow[ '[' . $alias . '][redcap_form_url]' ]['value'];
							$newResultRow[ '[' . $alias . '][redcap_survey_url]' ] =
								[ 'value' => \REDCap::getSurveyLink(
								                $formValuesRow[ \REDCap::getRecordIdField() ],
								                $form,
								                ( isset( $formValuesRow['redcap_event_name'] )
								                  ? \REDCap::getEventIdFromUniqueEvent(
								                     $formValuesRow['redcap_event_name'] ) : null ),
								                $formValuesRow['redcap_repeat_instance'] ?? 1 ) ];
							$newResultRow[ '[' . $alias . '][redcap_survey_url]' ]['label'] =
								$newResultRow[ '[' . $alias . '][redcap_survey_url]' ]['value'];
							$formHasRedcapFields['redcap_form_url'] = true;
							$formHasRedcapFields['redcap_survey_url'] = true;
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
					foreach ( [ 'redcap_form_url', 'redcap_survey_url' ] as $field )
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
		                                           return $c[] = $resultRow[ $i ];
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
			$resultTable[ $resultIndex ][ $fieldName ] = is_array( $value ) ? $value['value']
			                                                                : (string)$value;
		}
	}
	return $resultTable;
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
				$value = $value['value'];
			}
			echo $firstField ? '' : ',';
			$firstField = false;
			echo '"', str_replace( '"', '""', $module->parseHTML( (string)$value, true ) ), '"';
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
			if ( is_object( $resultRow[$columnName] ) )
			{
				$resultRow[$columnName] = $resultRow[$columnName]->getLabel();
			}
			elseif ( is_array( $resultRow[$columnName] ) )
			{
				$resultRow[$columnName] = $resultRow[$columnName]['label'];
			}
			$imgParsedData = isset( $resultRow[$columnName] )
			                    ? $module->parseHTML( (string)$resultRow[$columnName], true ) : '';
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
			if ( is_object( $resultRow[$columnName] ) )
			{
				$resultRow[$columnName] = $resultRow[$columnName]->getLabel();
			}
			elseif ( is_array( $resultRow[$columnName] ) )
			{
				$resultRow[$columnName] = $resultRow[$columnName]['label'];
			}
			$imgParsedData = isset( $resultRow[$columnName] )
			                    ? $module->parseHTML( (string)$resultRow[$columnName], true ) : '';
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
	$colNum = 0;
	foreach ( $resultTable[0] as $fieldName => $value )
	{
?>
   <th class="sorting" data-colnum="<?php echo $colNum; ?>">
    <?php echo $module->escapeHTML( $fieldName ), "\n"; ?>
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

?>
<script type="text/javascript">
$('#mod-advrep-table .valedit').submit( function(e)
{
  e.preventDefault()
  $(this).find('[name="redcap_csrf_token"]').attr('name','csrf_token')
  $.post( '', $(this).serialize() ).always( function( data )
  {
    if ( data !== true )
    {
      alert( 'An error occurred, the data could not be submitted.' )
    }
  } )
})
</script>
<?php

// Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';