<?php
/**
 *	Instrument Query Reports view page.
 */

namespace Nottingham\AdvancedReports;
const TVALIDSTR = 'text_validation_type_or_show_slider_number';



// Verify the report exists, and is an instrument query.
// Redirect to main reports page if not.
$reportID = $_GET['report_id'];
$listReports = $module->getReportList();
if ( ! isset( $listReports[$reportID] ) || $listReports[$reportID]['type'] != 'instrument' )
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


// Define the FieldReference class used for generating editable fields.
class FieldReference
{
	private $record;
	private $fieldName;
	private $event;
	private $instance;
	private $value;
	private $label;
	public static $module;
	public static $disableAccessControl;

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
		$module = self::$module;
		$fieldType = \REDCap::getFieldType( $this->fieldName );
		if ( self::$disableAccessControl ||
		     ! in_array( $fieldType,
		                 [ 'text', 'notes', 'radio', 'dropdown', 'yesno', 'truefalse' ] ) )
		{
			return $module->escapeHTML( $this->value );
		}
		$output = '<form method="post" class="valedit" style="white-space:nowrap">' .
		          '<input type="hidden" name="record" value="' .
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
			$output .= '<input onchange="$(this.form).find(\'button\').css(\'display\',\'\')" ' .
			           'type="text" name="value" value="' .
			           $module->escapeHTML( $this->value ) . '">';
		}
		elseif ( $fieldType == 'notes' )
		{
			$output .= '<textarea onchange="$(this.form).find(\'button\').css(\'display\',\'\')" ' .
			           'name="value" rows="2">' .
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
			$output .= '<select onchange="$(this.form).find(\'button\').css(\'display\',\'\')" ' .
			           'name="value"><option value=""></option>';
			foreach ( $choices as $choiceCode => $choiceLabel )
			{
				$output .= '<option' . ( $choiceCode == $this->value ? ' selected' : '' ) .
				           ' value="' . $module->escapeHTML( $choiceCode ) . '">' .
				           $module->escapeHTML( $choiceLabel ) . '</option>';
			}
			$output .= '</select>';
		}
		$output .= '<button type="submit" title="Save" style="display:none" onclick="$(this)' .
		           '.css(\'display\',\'none\')"><i class="fas fa-floppy-disk"></i></button>' .
		           '<input type="hidden" name="key" value="' .
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
FieldReference::$module = $module;
FieldReference::$disableAccessControl = $disableAccessControl;


// If a value to be changed has been submitted, check the submission is valid and update the value.
if ( ! $disableAccessControl && isset( $_POST['key'] ) &&
     $_POST['key'] == FieldReference::makeKey( $_POST['csrf_token'], $_POST['record'],
                                               $_POST['field'], $_POST['event'],
                                               $_POST['instance'] ) )
{
	$data = [ 'record' => $_POST['record'], 'field_name' => $_POST['field'],
	          'value' => $_POST['value'] ];
	if ( isset( $_POST['event'] ) )
	{
		$data[ 'redcap_event_name' ] = $_POST['event'];
		$eventID = \REDCap::getEventIdFromUniqueEvent( $_POST['event'] );
	}
	if ( isset( $_POST['instance'] ) )
	{
		$instrument = $module->getFormForField( $_POST['field'] );
		$data[ 'redcap_repeat_instance' ] = $_POST['instance'];
		if ( ! isset( $data[ 'redcap_event_name' ] ) ||
		     in_array( $instrument, $module->getRepeatingForms( $eventID ) ) )
		{
			$data[ 'redcap_repeat_instrument' ] = $instrument;
		}
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
$redcapFields2 = [ 'redcap_form_url', 'redcap_survey_url',
                   'redcap_created_by', 'redcap_created_time',
                   'redcap_updated_by', 'redcap_updated_time', 'redcap_last_instance' ];

// Get instrument created/updated users/dates and last instance.
$listEventNames = \REDCap::getEventNames( true );
$listDAGUniqueNames = \REDCap::getGroupNames( true );
$listDAGFullNames = \REDCap::getGroupNames( false );
$dataTable = method_exists( '\REDCap', 'getDataTable' )
                ? \REDCap::getDataTable( $module->getProjectId() ) : ( 'redcap' . '_data' );
$logEventTable = \REDCap::getLogEventTable( $module->getProjectId() );
$listFormCreateUpdate = [];
$listFormLastInstance = [];
foreach ( $reportData['forms'] as $queryForm )
{
	$infoFormCreateUpdate = [];
	$qFormDates = $module->query( "WITH logevent AS ( SELECT concat( substring(le.ts, 1, 4), " .
	                              "'-', substring(le.ts, 5, 2), '-', substring(le.ts, 7, 2), " .
	                              "' ', substring(le.ts, 9, 2), ':', substring(le.ts, 11, 2), " .
	                              "':', substring(le.ts, 13, 2) ) ts, le.user, " .
	                              "ifnull( concat( ui.user_firstname, ' ', ui.user_lastname ), " .
	                              "le.user ) user_fullname, le.pk, le.event_id, " .
	                              "if( le.data_values NOT LIKE '[%', 1, regexp_substr( " .
	                              "le.data_values, '[0-9]+', 1, 1 ) ) `instance` FROM " .
	                              "$logEventTable le LEFT JOIN redcap_user_information ui ON " .
	                              "le.user = ui.username WHERE le.project_id = ? AND " .
	                              "object_type = 'redcap" . "_data' AND le.description NOT LIKE " .
	                              "'%(Auto calculation)%' AND regexp_like( le.data_values, " .
	                              "concat('^(',(SELECT group_concat( field_name SEPARATOR '|' ) " .
	                              "FROM redcap_metadata WHERE project_id = le.project_id AND " .
	                              "field_order > 1 AND element_type <> 'descriptive' AND " .
	                              "form_name = ?),')'), 'cm' ) ) " .
	                              "SELECT * FROM logevent WHERE ts IN ( SELECT min(ts) FROM " .
	                              "logevent GROUP BY pk, event_id, instance ) OR ts IN ( SELECT " .
	                              "max(ts) FROM logevent GROUP BY pk, event_id, instance ) " .
	                              "ORDER BY ts", [ $module->getProjectId(), $queryForm['form'] ] );
	while ( $infoFormDates = $qFormDates->fetch_assoc() )
	{
		$formCreateUpdateEvent = ( $listEventNames === false )
		                         ? '' : $listEventNames[ $infoFormDates['event_id'] ];
		if ( isset( $infoFormCreateUpdate[ $infoFormDates['pk'] ][ $formCreateUpdateEvent ]
		                                                          [ $infoFormDates['instance'] ] ) )
		{
			$infoFormCreateUpdate[ $infoFormDates['pk'] ][ $formCreateUpdateEvent ]
			                                             [ $infoFormDates['instance'] ]['updated'] =
					               [ 'ts' => $infoFormDates['ts'], 'user' => $infoFormDates['user'],
					                 'user_fullname' => $infoFormDates['user_fullname'] ];
		}
		else
		{
			$infoFormCreateUpdate[ $infoFormDates['pk'] ][ $formCreateUpdateEvent ]
			                                             [ $infoFormDates['instance'] ] =
					[ 'created' => [ 'ts' => $infoFormDates['ts'], 'user' => $infoFormDates['user'],
					                 'user_fullname' => $infoFormDates['user_fullname'] ],
					  'updated' => [ 'ts' => $infoFormDates['ts'], 'user' => $infoFormDates['user'],
					                 'user_fullname' => $infoFormDates['user_fullname'] ] ];
		}
	}
	$listFormCreateUpdate[ $queryForm['form'] ] = $infoFormCreateUpdate;
	unset( $infoFormCreateUpdate );
	$qFormInstance = $module->query( "SELECT d.record, d.event_id, if(exists(SELECT 1 FROM " .
	                                 "redcap_events_repeat WHERE event_id = d.event_id), " .
	                                 "max(ifnull(d.instance,1)), '') last_instance " .
	                                 "FROM $dataTable d JOIN redcap_metadata md ON d.field_name " .
	                                 "= md.field_name AND d.project_id = md.project_id " .
	                                 "WHERE d.project_id = ? AND element_type <> 'descriptive' " .
	                                 "AND form_name = ? GROUP BY d.record, d.event_id",
	                                 [ $module->getProjectId(), $queryForm['form'] ] );
	while ( $infoFormInstance = $qFormInstance->fetch_assoc() )
	{
		$formLastInstanceEvent = ( $listEventNames === false )
		                         ? '' : $listEventNames[ $infoFormInstance['event_id'] ];
		$listFormLastInstance[ $queryForm['form'] ][ $infoFormInstance['record'] ]
		                     [ $formLastInstanceEvent ] = $infoFormInstance['last_instance'];
	}
}

// Get redcap_users virtual table if required.
$hasRCUsersTable = false;
$rcUsersTable = [];
foreach ( $reportData['forms'] as $queryForm )
{
	if ( $queryForm['form'] == 'redcap_users' )
	{
		$hasRCUsersTable = true;
		break;
	}
}
if ( $hasRCUsersTable )
{
	$queryRCUsersTable =
		$module->query( 'SELECT ui.username, ui.user_firstname firstname, ui.user_lastname ' .
		                'lastname, ui.user_email email, uro.role_name, uri.group_id dag, concat( ' .
		                'substring(le1.added,1,4), \'-\', substring(le1.added,5,2), \'-\', ' .
		                'substring(le1.added,7,2), \' \', substring(le1.added,9,2), \':\', ' .
		                'substring(le1.added,11,2), \':\', substring(le1.added,13,2) ) added, ' .
		                'uri.expiration, concat( substring(le2.min,1,4), \'-\', substring(' .
		                'le2.min,5,2), \'-\', substring(le2.min,7,2), \' \', substring(le2.min,' .
		                '9,2), \':\', substring(le2.min,11,2), \':\', substring(le2.min,13,2) ) ' .
		                'first_activity, concat( substring(le2.max,1,4), \'-\', substring(' .
		                'le2.max,5,2), \'-\', substring(le2.max,7,2), \' \', substring(le2.max,' .
		                '9,2), \':\', substring(le2.max,11,2), \':\', substring(le2.max,13,2) ) ' .
		                'last_activity ' .
		                'FROM redcap_user_information ui LEFT JOIN redcap_user_rights uri ' .
		                'ON ui.username = uri.username AND uri.project_id = ? ' .
		                'LEFT JOIN redcap_user_roles uro ON uri.role_id = uro.role_id ' .
		                'JOIN ( SELECT pk username, min(ts) added FROM ' . $logEventTable . ' ' .
		                'WHERE project_id = ? AND object_type = \'redcap_user_rights\' AND ' .
		                'data_values LIKE \'%user = %\' GROUP BY pk ) le1 ON ui.username = ' .
		                'le1.username LEFT JOIN ( SELECT `user` username, min(ts) min, max(ts) ' .
		                'max FROM ' . $logEventTable . ' WHERE project_id = ? AND `user` <> \'' .
		                '[survey respondent]\' GROUP BY `user` ) le2 ON ui.username = le2.username',
		                [ $module->getProjectId(), $module->getProjectId(),
		                  $module->getProjectId() ] );
	while ( $infoRCUsersTable = $queryRCUsersTable->fetch_assoc() )
	{
		$rcUsersTable[] = $infoRCUsersTable;
	}
}

// Build the result table.
$resultTable = [[]];
foreach ( $reportData['forms'] as $queryForm )
{
	// Get the form name and alias (use form name for alias if not defined).
	$form = $queryForm['form'];
	$alias = $queryForm['alias'] == '' ? $form : $queryForm['alias'];
	// Get the fields for the form and retrieve the values and value labels for each record.
	if ( $form == 'redcap_users' )
	{
		$fields = empty( $rcUsersTable ) ? [] : array_keys( $rcUsersTable[0] );
		$fieldMetadata = [ 'added' => [ 'field_type' => 'text', TVALIDSTR => 'time' ],
		                   'expiration' => [ 'field_type' => 'text', TVALIDSTR => 'time' ],
		                   'first_activity' => [ 'field_type' => 'text', TVALIDSTR => 'time' ],
		                   'last_activity' => [ 'field_type' => 'text', TVALIDSTR => 'time' ] ];
		$formValues = $rcUsersTable;
		$formLabels = $rcUsersTable;
	}
	else
	{
		$fields = array_unique( array_merge( [ \REDCap::getRecordIdField() ],
		                                     \REDCap::getFieldNames( $form ) ) );
		$fieldMetadata = \REDCap::getDataDictionary( 'array', false, $fields );
		$formValues = \REDCap::getData( $resultParams +
		                                [ 'exportAsLabels' => false, 'fields' => $fields ] );
		$formLabels = \REDCap::getData( $resultParams +
		                                [ 'exportAsLabels' => true, 'fields' => $fields ] );
	}
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
	if ( ! empty( $dateFields ) )
	{
		foreach ( $formLabels as $i => $formLabelsRow )
		{
			foreach ( $formLabelsRow as $fieldName => $value )
			{
				if ( in_array( $fieldName, $dateFields ) )
				{
					$formLabels[ $i ][ $fieldName ] =
							\DateTimeRC::format_ts_from_ymd( $value, false, true );
				}
			}
		}
	}
	if ( $queryForm['on'] != '' )
	{
		list( $joinFunction, $joinParamData ) =
			$module->parseLogic( $queryForm['on'], $requestType );
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
			// For redcap_users virtual table, replace DAG ID with name.
			if ( $form == 'redcap_users' && $formValuesRow['dag'] !== null )
			{
				$formValuesRow['dag'] = $listDAGUniqueNames[ $formValuesRow['dag'] ];
				$formLabelsRow['dag'] = $listDAGFullNames[ $formLabelsRow['dag'] ];
			}
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
							// Insert redcap_form_url virtual field.
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
							$formHasRedcapFields['redcap_form_url'] = true;
							// Insert redcap_survey_url virtual field.
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
							$formHasRedcapFields['redcap_survey_url'] = true;
							// Insert redcap_created_by virtual field.
							$newResultRow[ '[' . $alias . '][redcap_created_by]' ] =
								[ 'value' => $listFormCreateUpdate[ $form ]
								               [ $formValuesRow[ \REDCap::getRecordIdField() ] ]
								               [ $formValuesRow['redcap_event_name'] != '' ?
								                 $formValuesRow['redcap_event_name'] : '' ]
								               [ $formValuesRow['redcap_repeat_instance'] != '' ?
								                 $formValuesRow['redcap_repeat_instance'] : 1 ]
								               ['created']['user'],
								  'label' => $listFormCreateUpdate[ $form ]
								               [ $formValuesRow[ \REDCap::getRecordIdField() ] ]
								               [ $formValuesRow['redcap_event_name'] != '' ?
								                 $formValuesRow['redcap_event_name'] : '' ]
								               [ $formValuesRow['redcap_repeat_instance'] != '' ?
								                 $formValuesRow['redcap_repeat_instance'] : 1 ]
								               ['created']['user_fullname'] ];
							$formHasRedcapFields['redcap_created_by'] = true;
							// Insert redcap_created_time virtual field.
							$newResultRow[ '[' . $alias . '][redcap_created_time]' ] =
								[ 'value' => $listFormCreateUpdate[ $form ]
								               [ $formValuesRow[ \REDCap::getRecordIdField() ] ]
								               [ $formValuesRow['redcap_event_name'] != '' ?
								                 $formValuesRow['redcap_event_name'] : '' ]
								               [ $formValuesRow['redcap_repeat_instance'] != '' ?
								                 $formValuesRow['redcap_repeat_instance'] : 1 ]
								               ['created']['ts'] ];
							$newResultRow[ '[' . $alias . '][redcap_created_time]' ]['label'] =
								\DateTimeRC::format_ts_from_ymd(
								  $newResultRow[ '[' . $alias . '][redcap_created_time]' ]['value'],
								                                 false, true );
							$formHasRedcapFields['redcap_created_time'] = true;
							// Insert redcap_updated_by virtual field.
							$newResultRow[ '[' . $alias . '][redcap_updated_by]' ] =
								[ 'value' => $listFormCreateUpdate[ $form ]
								               [ $formValuesRow[ \REDCap::getRecordIdField() ] ]
								               [ $formValuesRow['redcap_event_name'] != '' ?
								                 $formValuesRow['redcap_event_name'] : '' ]
								               [ $formValuesRow['redcap_repeat_instance'] != '' ?
								                 $formValuesRow['redcap_repeat_instance'] : 1 ]
								               ['updated']['user'],
								  'label' => $listFormCreateUpdate[ $form ]
								               [ $formValuesRow[ \REDCap::getRecordIdField() ] ]
								               [ $formValuesRow['redcap_event_name'] != '' ?
								                 $formValuesRow['redcap_event_name'] : '' ]
								               [ $formValuesRow['redcap_repeat_instance'] != '' ?
								                 $formValuesRow['redcap_repeat_instance'] : 1 ]
								               ['updated']['user_fullname'] ];
							$formHasRedcapFields['redcap_updated_by'] = true;
							// Insert redcap_updated_time virtual field.
							$newResultRow[ '[' . $alias . '][redcap_updated_time]' ] =
								[ 'value' => $listFormCreateUpdate[ $form ]
								               [ $formValuesRow[ \REDCap::getRecordIdField() ] ]
								               [ $formValuesRow['redcap_event_name'] != '' ?
								                 $formValuesRow['redcap_event_name'] : '' ]
								               [ $formValuesRow['redcap_repeat_instance'] != '' ?
								                 $formValuesRow['redcap_repeat_instance'] : 1 ]
								               ['updated']['ts'] ];
							$newResultRow[ '[' . $alias . '][redcap_updated_time]' ]['label'] =
								\DateTimeRC::format_ts_from_ymd(
								  $newResultRow[ '[' . $alias . '][redcap_updated_time]' ]['value'],
								                                 false, true );
							$formHasRedcapFields['redcap_updated_time'] = true;
							// Insert redcap_last_instance virtual field.
							$newResultRow[ '[' . $alias . '][redcap_last_instance]' ] =
								[ 'value' => $listFormLastInstance[ $form ]
								               [ $formValuesRow[ \REDCap::getRecordIdField() ] ]
								               [ $formValuesRow['redcap_event_name'] != '' ?
								                 $formValuesRow['redcap_event_name'] : '' ] ];
							$newResultRow[ '[' . $alias . '][redcap_last_instance]' ]['label'] =
								$newResultRow[ '[' . $alias . '][redcap_last_instance]' ]['value'];
							$formHasRedcapFields['redcap_last_instance'] = true;

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
					foreach ( array_merge( $redcapFields, $redcapFields2 ) as $field )
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
			$resultTable[ $resultIndex ][ $fieldName ] = is_array( $value ) ? $value['value']
			                                                                : (string)$value;
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
	if ( $isInternalRequest )
	{
		return;
	}
	exit;
}



// Display the header and report navigation links.
if ( $disableAccessControl ) ($htmlPage = new \HtmlPage)->PrintHeader( false );
else require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
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
  var vSubmitBtn = $(this).find('button[type="submit"]')
  vSubmitBtn.prop('disabled',true).css('display','')
  $(this).find('[name="redcap_csrf_token"]').attr('name','csrf_token')
  $.post( '', $(this).serialize() ).always( function( data )
  {
    if ( data !== true )
    {
      alert( 'An error occurred, the data could not be submitted.' )
    }
    else
    {
      vSubmitBtn.css('display','none')
    }
    vSubmitBtn.prop('disabled',false)
  } )
})
</script>
<?php

// Display the footer
if ( $disableAccessControl ) $htmlPage->PrintFooter();
else require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
