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
			return $module->escape( $this->value );
		}
		$output = '<form method="post" class="valedit" style="white-space:nowrap">' .
		          '<input type="hidden" name="record" value="' . $module->escape( $this->record ) .
		          '"><input type="hidden" name="field" value="' .
		          $module->escape( $this->fieldName ) . '">';
		if ( $this->event !== null )
		{
			$output .= '<input type="hidden" name="event" value="' .
			          $module->escape( $this->event ) . '">';
		}
		if ( $this->instance !== null )
		{
			$output .= '<input type="hidden" name="instance" value="' .
			          $module->escape( $this->instance ) . '">';
		}
		if ( $fieldType == 'text' )
		{
			$output .= '<input onchange="$(this.form).find(\'button\').css(\'display\',\'\')" ' .
			           'type="text" name="value" value="' . $module->escape( $this->value ) . '">';
		}
		elseif ( $fieldType == 'notes' )
		{
			$output .= '<textarea onchange="$(this.form).find(\'button\').css(\'display\',\'\')" ' .
			           'name="value" rows="2">' . $module->escape( $this->value ) . '</textarea>';
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
				           ' value="' . $module->escape( $choiceCode ) . '">' .
				           $module->escape( $choiceLabel ) . '</option>';
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
$recordIDField = \REDCap::getRecordIdField();

// Get the list of surveys.
$listSurveys = [];
$querySurveys = $module->query( 'SELECT form_name FROM redcap_surveys WHERE project_id = ?',
                                [ $module->getProjectId() ] );
while ( $infoSurvey = $querySurveys->fetch_assoc() )
{
	$listSurveys[] = $infoSurvey['form_name'];
}



// Before getting any data for the report, check the instrument names for virtual tables such as
// 'redcap_users', and check all the logic fields for the variables which are used. Create a list of
// all the fields and their corresponding instruments which are used. Fields and virtual tables
// which are not used will be able to be disregarded later for efficiency.
$hasRCUsersTable = false;
$rcUsersTable = [];
$listAliasForms = [];
$listReferencedFields = [];
foreach ( $reportData['forms'] as $queryForm )
{
	// If the form is a virtual table, flag that we need it.
	if ( $queryForm['form'] == 'redcap_users' )
	{
		$hasRCUsersTable = true;
		continue;
	}
	// Map the form alias to the instrument name.
	$listAliasForms[ $queryForm['alias'] == '' ? $queryForm['form']
	                                           : $queryForm['alias'] ] = $queryForm['form'];
}
// If there are no fields to display specified, all fields will be returned, so no need to check
// them. Otherwise, proceed with checking which fields are used...
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



// For each instrument used in the query, get the virtual fields for the form created by/time, form
// updated by/time and last instance. The field values are associated with the instrument name
// rather than the alias and are skipped where they are not needed.
$listFormCreateUpdate = [];
$listFormLastInstance = [];
foreach ( $reportData['forms'] as $queryForm )
{
	if ( isset( $listFormCreateUpdate[ $queryForm['form'] ] ) ||
	     isset( $listFormLastInstance[ $queryForm['form'] ] ) )
	{
		// Skip the instrument if we have data for it already.
		continue;
	}
	// Get the created/updated by/time fields if required.
	if ( empty( $listReferencedFields ) ||
	     isset( $listReferencedFields[ $queryForm['form'] ]['redcap_created_by'] ) ||
	     isset( $listReferencedFields[ $queryForm['form'] ]['redcap_created_time'] ) ||
	     isset( $listReferencedFields[ $queryForm['form'] ]['redcap_updated_by'] ) ||
	     isset( $listReferencedFields[ $queryForm['form'] ]['redcap_updated_time'] ) )
	{
		$infoFormCreateUpdate = [];
		// This query will look up, for each record/event/instance combination, the user who first
		// saved data on the form and the time (created by/time) and the user who most recently
		// saved data on the form and the time (updated by/time). This is done by checking the log
		// table for the project for updates to the fields on the form. Updates which are solely
		// calculated field changes are excluded.
		$qFormDates =
				$module->query( "SELECT concat( substring(ts_user_c, 1, 4), '-', substring(" .
				                "ts_user_c, 5, 2), '-', substring(ts_user_c, 7, 2), ' ', " .
				                "substring(ts_user_c, 9, 2), ':', substring(ts_user_c, 11, 2), " .
				                "':', substring(ts_user_c, 13, 2) ) ts_c, substring(ts_user_c, " .
				                "15) user_c, ifnull( concat( uic.user_firstname, ' ', " .
				                "uic.user_lastname ), substring(ts_user_c, 15) ) name_c, " .
				                "concat( substring(ts_user_u, 1, 4), '-', substring(ts_user_u, " .
				                "5, 2), '-', substring(ts_user_u, 7, 2), ' ', substring(" .
				                "ts_user_u, 9, 2), ':', substring(ts_user_u, 11, 2), ':', " .
				                "substring(ts_user_u, 13, 2) ) ts_u, substring(ts_user_u, 15) " .
				                "user_u, ifnull( concat( uiu.user_firstname, ' ', " .
				                "uiu.user_lastname ), substring(ts_user_u, 15) ) name_u, pk, " .
				                "event_id, `instance` FROM ( SELECT min( concat( ts, user ) ) " .
				                "ts_user_c, max( concat( ts, user ) ) ts_user_u, pk, event_id, " .
				                "if( data_values NOT LIKE '[%', 1, regexp_substr( data_values, " .
				                "'[0-9]+' ) ) `instance` FROM $logEventTable WHERE " .
				                "project_id = ? AND object_type = 'redcap_data' AND description " .
				                "NOT LIKE '%(Auto calculation)%' AND data_values RLIKE concat(" .
				                "'(?m)^(',(SELECT group_concat( field_name SEPARATOR '|' ) " .
				                "FROM redcap_metadata WHERE project_id = ? AND field_order > 1 " .
				                "AND element_type <> 'descriptive' AND form_name = ?),')') " .
				                "GROUP BY pk, event_id, instance ) log LEFT JOIN " .
				                "redcap_user_information uic ON substring(ts_user_c, 15) = " .
				                "uic.username LEFT JOIN redcap_user_information uiu ON " .
				                "substring(ts_user_u, 15) = uiu.username",
				                [ $module->getProjectId(),
				                  $module->getProjectId(), $queryForm['form'] ] );
		while ( $infoFormDates = $qFormDates->fetch_assoc() )
		{
			// For each record/event/instance combination, add the user and time created/updated to
			// the list for this form.
			$formCreateUpdateEvent = ( $listEventNames === false )
			                         ? '' : $listEventNames[ $infoFormDates['event_id'] ];
			$infoFormCreateUpdate[ $infoFormDates['pk'] ][ $formCreateUpdateEvent ]
			                                             [ $infoFormDates['instance'] ] =
					[ 'created' => [ 'ts' => $infoFormDates['ts_c'],
					                 'user' => $infoFormDates['user_c'],
					                 'user_fullname' => $infoFormDates['name_c'] ],
					  'updated' => [ 'ts' => $infoFormDates['ts_u'],
					                 'user' => $infoFormDates['user_u'],
					                 'user_fullname' => $infoFormDates['name_u'] ] ];
		}
		// Add the list for this form to the main list of this data.
		$listFormCreateUpdate[ $queryForm['form'] ] = $infoFormCreateUpdate;
		unset( $infoFormCreateUpdate );
	}
	// Get the redcap_last_instance field data if required.
	if ( empty( $listReferencedFields ) ||
	     isset( $listReferencedFields[ $queryForm['form'] ]['redcap_last_instance'] ) )
	{
		// This query will look up, for each record/event combination, the highest instance number
		// for this form.
		$qFormInstance =
				$module->query( "SELECT d.record, d.event_id, if(exists(SELECT 1 FROM " .
				                "redcap_events_repeat WHERE event_id = d.event_id), " .
				                "max(ifnull(d.instance,1)), '') last_instance " .
				                "FROM $dataTable d JOIN redcap_metadata md ON d.field_name " .
				                "= md.field_name AND d.project_id = md.project_id " .
				                "WHERE d.project_id = ? AND element_type <> 'descriptive' " .
				                "AND form_name = ? GROUP BY d.record, d.event_id",
				                [ $module->getProjectId(), $queryForm['form'] ] );
		while ( $infoFormInstance = $qFormInstance->fetch_assoc() )
		{
			// For each record/event combination, add the highest instance number to the list.
			$formLastInstanceEvent = ( $listEventNames === false )
			                         ? '' : $listEventNames[ $infoFormInstance['event_id'] ];
			$listFormLastInstance[ $queryForm['form'] ][ $infoFormInstance['record'] ]
			                     [ $formLastInstanceEvent ] = $infoFormInstance['last_instance'];
		}
	}
}



// If the instrument query makes use of the redcap_users virtual table, query the database for this
// data and store in an array for use later.
if ( $hasRCUsersTable )
{
	// This query returns a table of the users for this project. It includes the username, first
	// name, last name, primary email address, role name, current DAG, date added to the project,
	// expiration date (from the project), first activity on the project, most recent activity on
	// the project.
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
	// Store the users table.
	while ( $infoRCUsersTable = $queryRCUsersTable->fetch_assoc() )
	{
		$rcUsersTable[] = $infoRCUsersTable;
	}
}



// Build the result table for the instrument query.
$resultTable = [[]];
// For each instrument in the query...
foreach ( $reportData['forms'] as $queryForm )
{
	// Get the form name and alias (use form name for alias if not defined).
	$form = $queryForm['form'];
	$alias = $queryForm['alias'] == '' ? $form : $queryForm['alias'];

	// Get the fields for the form and retrieve the values and value labels for each record.
	if ( $form == 'redcap_users' )
	{
		// If using the redcap_users virtual table, get the values saved earlier. Mark any datetime
		// fields as such so format transformations can be applied.
		$fields = empty( $rcUsersTable ) ? [] : array_keys( $rcUsersTable[0] );
		$fieldMetadata = [ 'added' => [ 'field_type' => 'text', TVALIDSTR => 'time' ],
		                   'expiration' => [ 'field_type' => 'text', TVALIDSTR => 'time' ],
		                   'first_activity' => [ 'field_type' => 'text', TVALIDSTR => 'time' ],
		                   'last_activity' => [ 'field_type' => 'text', TVALIDSTR => 'time' ] ];
		$formValues = $rcUsersTable;
		$formLabels = $rcUsersTable;
	}
	else // a normal instrument in the project
	{
		// Get a list of the fields for this instrument.
		$fields = array_unique( array_merge( [ $recordIDField ],
		                                     \REDCap::getFieldNames( $form ) ) );
		$fieldMetadata = \REDCap::getDataDictionary( 'array', false, $fields );
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
		                                [ 'exportAsLabels' => false, 'fields' => $fields ] );
		$formLabels = \REDCap::getData( $resultParams +
		                                [ 'exportAsLabels' => true, 'fields' => $fields ] );
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
			if ( $formValuesRow[ $form . '_complete' ] === '' )
			{
				continue;
			}
			$formLabelsRow = $formLabels[$i];
			// For redcap_users virtual table, replace DAG ID with name.
			if ( $form == 'redcap_users' )
			{
				if ( $formValuesRow['dag'] !== null )
				{
					$formValuesRow['dag'] = $listDAGUniqueNames[ $formValuesRow['dag'] ];
					$formLabelsRow['dag'] = $listDAGFullNames[ $formLabelsRow['dag'] ];
				}
			}
			// For project instruments, add the virtual fields where required.
			else
			{
				// Insert redcap_form_url virtual field.
				if ( empty( $listReferencedFields ) ||
				     isset( $listReferencedFields[ $form ]['redcap_form_url'] ) )
				{
					$formValuesRow['redcap_form_url'] =
						APP_PATH_WEBROOT_FULL . 'redcap_v' . REDCAP_VERSION .
						'/DataEntry/index.php?pid=' . $module->getProjectId() . '&page=' . $form .
						'&id=' . $formValuesRow[ $recordIDField ] .
						( isset( $formValuesRow['redcap_event_name'] )
						  ? '&event_id=' .
						    array_search( $formValuesRow['redcap_event_name'], $listEventNames )
						  : '' ) .
						( isset( $formValuesRow['redcap_repeat_instance'] ) &&
						  $formValuesRow['redcap_repeat_instance'] != ''
						  ? '&instance=' . $formValuesRow['redcap_repeat_instance'] : '' );
					$formLabelsRow['redcap_form_url'] = $formValuesRow['redcap_form_url'];
					$formHasRedcapFields['redcap_form_url'] = true;
				}
				// Insert redcap_survey_url virtual field.
				if ( empty( $listReferencedFields ) ||
				     isset( $listReferencedFields[ $form ]['redcap_survey_url'] ) )
				{
					$formValuesRow['redcap_survey_url'] =
						in_array( $form, $listSurveys )
						? $module->getSurveyLink( $formValuesRow[ $recordIDField ], $form,
						   ( isset( $formValuesRow['redcap_event_name'] )
						     ? \REDCap::getEventIdFromUniqueEvent(
						                                       $formValuesRow['redcap_event_name'] )
						     : null ),
						   $formValuesRow['redcap_repeat_instance'] ?? 1 )
						: '';
					$formLabelsRow['redcap_survey_url'] = $formValuesRow['redcap_survey_url'];
					$formHasRedcapFields['redcap_survey_url'] = true;
				}
				// Insert redcap_created_by virtual field.
				if ( empty( $listReferencedFields ) ||
				     isset( $listReferencedFields[ $form ]['redcap_created_by'] ) )
				{
					$formValuesRow['redcap_created_by'] =
						$listFormCreateUpdate[ $form ]
							[ $formValuesRow[ $recordIDField ] ]
							[ $formValuesRow['redcap_event_name'] != ''
							  ? $formValuesRow['redcap_event_name'] : '' ]
							[ $formValuesRow['redcap_repeat_instance'] != ''
							  ? $formValuesRow['redcap_repeat_instance'] : 1 ]['created']['user'];
					$formLabelsRow['redcap_created_by'] =
						$listFormCreateUpdate[ $form ]
							[ $formValuesRow[ $recordIDField ] ]
							[ $formValuesRow['redcap_event_name'] != ''
							  ? $formValuesRow['redcap_event_name'] : '' ]
							[ $formValuesRow['redcap_repeat_instance'] != ''
							  ? $formValuesRow['redcap_repeat_instance'] : 1 ]
							['created']['user_fullname'];
					$formHasRedcapFields['redcap_created_by'] = true;
				}
				// Insert redcap_created_time virtual field.
				if ( empty( $listReferencedFields ) ||
				     isset( $listReferencedFields[ $form ]['redcap_created_time'] ) )
				{
					$formValuesRow['redcap_created_time'] =
						$listFormCreateUpdate[ $form ]
							[ $formValuesRow[ $recordIDField ] ]
							[ $formValuesRow['redcap_event_name'] != ''
							  ? $formValuesRow['redcap_event_name'] : '' ]
							[ $formValuesRow['redcap_repeat_instance'] != ''
							  ? $formValuesRow['redcap_repeat_instance'] : 1 ]['created']['ts'];
					$formLabelsRow['redcap_created_time'] =
						\DateTimeRC::format_ts_from_ymd( $formValuesRow['redcap_created_time'],
						                                 false, true );
					$formHasRedcapFields['redcap_created_time'] = true;
				}
				// Insert redcap_updated_by virtual field.
				if ( empty( $listReferencedFields ) ||
				     isset( $listReferencedFields[ $form ]['redcap_updated by'] ) )
				{
					$formValuesRow['redcap_updated_by'] =
						$listFormCreateUpdate[ $form ]
							[ $formValuesRow[ $recordIDField ] ]
							[ $formValuesRow['redcap_event_name'] != ''
							  ? $formValuesRow['redcap_event_name'] : '' ]
							[ $formValuesRow['redcap_repeat_instance'] != ''
							  ? $formValuesRow['redcap_repeat_instance'] : 1 ]['updated']['user'];
					$formLabelsRow['redcap_updated_by'] =
						$listFormCreateUpdate[ $form ]
							[ $formValuesRow[ $recordIDField ] ]
							[ $formValuesRow['redcap_event_name'] != ''
							  ? $formValuesRow['redcap_event_name'] : '' ]
							[ $formValuesRow['redcap_repeat_instance'] != ''
							  ? $formValuesRow['redcap_repeat_instance'] : 1 ]
							['updated']['user_fullname'];
					$formHasRedcapFields['redcap_updated_by'] = true;
				}
				// Insert redcap_updated_time virtual field.
				if ( empty( $listReferencedFields ) ||
				     isset( $listReferencedFields[ $form ]['redcap_updated_time'] ) )
				{
					$formValuesRow['redcap_updated_time'] =
						$listFormCreateUpdate[ $form ]
							[ $formValuesRow[ $recordIDField ] ]
							[ $formValuesRow['redcap_event_name'] != ''
							  ? $formValuesRow['redcap_event_name'] : '' ]
							[ $formValuesRow['redcap_repeat_instance'] != ''
							  ? $formValuesRow['redcap_repeat_instance'] : 1 ]['updated']['ts'];
					$formLabelsRow['redcap_updated_time'] =
						\DateTimeRC::format_ts_from_ymd( $formValuesRow['redcap_updated_time'],
						                                 false, true );
					$formHasRedcapFields['redcap_updated_time'] = true;
				}
				// Insert redcap_last_instance virtual field.
				if ( empty( $listReferencedFields ) ||
				     isset( $listReferencedFields[ $form ]['redcap_last_instance'] ) )
				{
					$formValuesRow['redcap_last_instance'] =
						$listFormLastInstance[ $form ]
							[ $formValuesRow[ $recordIDField ] ]
							[ $formValuesRow['redcap_event_name'] != ''
							  ? $formValuesRow['redcap_event_name'] : '' ];
					$formLabelsRow['redcap_last_instance'] = $formValuesRow['redcap_last_instance'];
					$formHasRedcapFields['redcap_last_instance'] = true;
				}
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
						  'ref' => new FieldReference( $formValuesRow[$recordIDField],
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
						$insertedRedcapFields = true;
					}
				}
				foreach ( $redcapFields2 as $field )
				{
					if ( isset( $formValuesRow[$field] ) )
					{
						$formHasRedcapFields[ $field ] = true;
						$newResultRow[ '[' . $alias . '][' . $field . ']' ] =
								[ 'value' => $formValuesRow[$field],
								  'label' => $formLabelsRow[$field] ];
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
			foreach ( $redcapFields2 as $field )
			{
				if ( isset( $formHasRedcapFields[ $field ] ) )
				{
					$newResultRow[ '[' . $alias . '][' . $field . ']' ] =
							[ 'value' => '', 'label' => '' ];
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
$module->outputViewReportHeader( $reportConfig['label'], 'instrument',
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
$module->writePageFooter();
