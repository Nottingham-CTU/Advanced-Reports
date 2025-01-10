<?php

namespace Nottingham\AdvancedReports;

class AdvancedReports extends \ExternalModules\AbstractExternalModule
{

	const API_TYPES = [ 'instrument', 'sql' ];
	const PUBLIC_TYPES = [ 'instrument', 'pdf', 'sql' ];
	const SAVEABLE_TYPES = [ 'instrument', 'pdf', 'sql' ];

	// Show the advanced reports link based on whether the user is able to view or edit any
	// reports. If the user has no access, hide the link.
	function redcap_module_link_check_display( $project_id, $link )
	{
		if ( $this->isReportEditable() )
		{
			return $link;
		}
		$listReports = $this->getReportList();
		foreach ( $listReports as $reportID => $data )
		{
			if ( $this->isReportAccessible( $reportID ) )
			{
				return $link;
			}
		}
		return null;
	}



	// As the REDCap built-in module configuration only contains options for administrators, hide
	// this configuration from all non-administrators.
	function redcap_module_configure_button_display()
	{
		$projectID = $this->getProjectID();
		if ( $projectID == null )
		{
			return true;
		}
		return ( $this->getUser()->isSuperUser() &&
		         ( $this->getProjectSetting( 'edit-if-design' ) ||
		           $this->getProjectSetting( 'edit-if-reports' ) ) ) ? true : null;
	}



	// Supply data to the REDCap UI Tweaker module for use on simplified views.
	function redcap_every_page_before_render( $project_id )
	{
		if ( $this->isModuleEnabled('redcap_ui_tweaker') )
		{
			$UITweaker = \ExternalModules\ExternalModules::getModuleInstance('redcap_ui_tweaker');
			// Supply report data to the reports simplified view.
			if ( method_exists( $UITweaker, 'areCustomReportsExpected' ) &&
			     $UITweaker->areCustomReportsExpected() &&
			     method_exists( $UITweaker, 'customReportsEscapeHTML' ) )
			{
				$listReports = $this->getReportList();
				$reportTypes = $this->getReportTypes();
				foreach ( $listReports as $reportID => $reportConfig )
				{
					$reportData = $this->getReportData( $reportID );
					$description = $reportConfig['annotation'] == ''
					               ? '' : ( $reportConfig['annotation'] . "\n\n" );
					$definition = '';
					$options = '';
					// Get report permissions.
					if ( $reportConfig['roles_access'] == '*' )
					{
						$reportConfig['roles_access'] = 'ALL';
					}
					if ( $reportConfig['roles_download'] == '*' )
					{
						$reportConfig['roles_download'] = $reportConfig['roles_access'];
					}
					$permissions = "<b>View Access:</b>\n" .
					               $UITweaker->customReportsEscapeHTML(
					                                                $reportConfig['roles_access'] );
					if ( array_key_exists('as_image', $reportConfig) && $reportConfig['as_image'] )
					{
						$permissions .= "\n<i>Can retrieve as image</i>";
					}
					if ( $reportConfig['download'] )
					{
						$permissions .= "\n<b>Download:</b>\n" .
						                $UITweaker->customReportsEscapeHTML(
						                                          $reportConfig['roles_download'] );
					}
					if ( ! $reportConfig['visible'] )
					{
						$permissions = "<i>(hidden)</i>\n$permissions";
					}
					// For SQL reports...
					if ( $reportConfig['type'] == 'sql' )
					{
						// Populate description and definition with the report description and SQL.
						$description .= $reportData['sql_desc'];
						$definition .=
								$UITweaker->customReportsEscapeHTML( $reportData['sql_query'] );
						// Note if the report is EAV format.
						if ( in_array( $reportData['sql_type'], [ 'eav', 'eav-id' ] ) )
						{
							$options .= 'EAV';
							if ( $reportData['sql_type'] == 'eav-id' )
							{
								$options .= ' (+Row ID)';
							}
							if ( $reportData['sql_cols'] != '' )
							{
								$options .= ', columns: ' .
								     $UITweaker->customReportsEscapeHTML( $reportData['sql_cols'] );
							}
						}
					}
					// For Instrument Queries...
					elseif ( $reportConfig['type'] == 'instrument' )
					{
						$description .= $reportData['desc'];
						$definition = '<b>Instruments:</b>';
						foreach ( $reportData['forms'] as $queryForm )
						{
							$definition .= "\n";
							if ( $definition != "<b>Instruments:</b>\n" )
							{
								if ( isset( $queryForm['join'] ) )
								{
									$definition .= strtoupper( $queryForm['join'] );
								}
								else
								{
									$definition .= 'INNER';
								}
								$definition .= ' JOIN ';
							}
							$definition .= $UITweaker->customReportsEscapeHTML( $queryForm['form'] );
							if ( $queryForm['alias'] != '' )
							{
								$definition .= ' AS `' .
								   $UITweaker->customReportsEscapeHTML( $queryForm['alias'] ) . '`';
							}
							if ( $queryForm['on'] != '' )
							{
								$definition .= ' ON ' .
								            $UITweaker->customReportsEscapeHTML( $queryForm['on'] );
							}
						}
						if ( $reportData['where'] != '' )
						{
							$definition .= "\n<b>Condition:</b>\n" .
							            $UITweaker->customReportsEscapeHTML( $reportData['where'] );
						}
						if ( $reportData['orderby'] != '' )
						{
							$definition .= "\n<b>Sorting:</b>" .
							          $UITweaker->customReportsEscapeHTML( $reportData['orderby'] );
						}
						if ( ! empty( $reportData['select'] ) )
						{
							$definition .= "\n<b>Fields to display:</b>";
							foreach ( $reportData['select'] as $queryField )
							{
								$definition .= "\n- " .
								        $UITweaker->customReportsEscapeHTML( $queryField['field'] );
								if ( $queryField['alias'] != '' )
								{
									$definition .= ' AS `' .
									   $UITweaker->customReportsEscapeHTML( $queryField['alias'] ) .
									   '`';
								}
								if ( isset( $queryField['grouping'] ) &&
								     $queryField['grouping'] != '' )
								{
									$definition .= ' GROUPING ' .
									  $UITweaker->customReportsEscapeHTML( $queryField['grouping'] );
								}
							}
						}
						if ( $reportData['nomissingdatacodes'] )
						{
							$options .= 'Hide missing data codes';
						}
					}
					// For Record Tables...
					elseif ( $reportConfig['type'] == 'recordtbl' )
					{
						$description .= $reportData['desc'];
						$definition = '<b>Instruments:</b>';
						if ( empty( $reportData['forms'] ) )
						{
							$definition .= ' ALL';
						}
						else
						{
							foreach ( $reportData['forms'] as $formName )
							{
								$definition .= "\n- ";
								$definition .= $UITweaker->customReportsEscapeHTML( $formName );
							}
						}
						$definition .= "\n<b>Events:</b>";
						if ( empty( $reportData['events'] ) )
						{
							$definition .= ' ALL';
						}
						else
						{
							foreach ( $reportData['events'] as $eventName )
							{
								$definition .= "\n- ";
								$definition .= $UITweaker->customReportsEscapeHTML( $eventName );
							}
						}
						if ( $reportData['nomissingdatacodes'] )
						{
							$options .= 'Hide missing data codes';
						}
					}
					// For Gantt charts...
					elseif ( $reportConfig['type'] == 'gantt' )
					{
						$definition = '<b>Labels:</b>';
						foreach ( $reportData['labels'] as $infoLabel )
						{
							$definition .= "\n- " .
							      $UITweaker->customReportsEscapeHTML( $infoLabel['name'] ) . ': [';
							if ( $infoLabel['event'] != '' )
							{
								$definition .= $UITweaker->customReportsEscapeHTML(
								                                       $infoLabel['event'] ) . '][';
							}
							$definition .=
							       $UITweaker->customReportsEscapeHTML( $infoLabel['field'] ) . ']';
						}
						$definition .= "\n<b>Categories:</b>";
						foreach ( $reportData['chart_categories'] as $infoCategory )
						{
							$definition .= "\n- " . $UITweaker->customReportsEscapeHTML(
							                                        $infoCategory['name'] ) . ': [';
							if ( $infoCategory['start_event'] != '' )
							{
								$definition .= $UITweaker->customReportsEscapeHTML(
								                              $infoCategory['start_event'] ) . '][';
							}
							$definition .= $UITweaker->customReportsEscapeHTML(
							                               $infoCategory['start_field'] ) . '] - [';
							if ( $infoCategory['end_event'] != '' )
							{
								$definition .= $UITweaker->customReportsEscapeHTML(
								                                $infoCategory['end_event'] ) . '][';
							}
							$definition .= $UITweaker->customReportsEscapeHTML(
							                                     $infoCategory['end_field'] ) . ']';
						}
					}
					// For PDF reports...
					elseif ( $reportConfig['type'] == 'pdf' )
					{
						$definition = '<b>Source Report:</b> ';
						$definition .= $UITweaker->customReportsEscapeHTML( $reportData['source'] );
						$definition .= "\n<b>Paper Size:</b> ";
						$definition .= $UITweaker->customReportsEscapeHTML(
						                 ucfirst( $reportData['pdf_size'] ) . ' ' .
						                 ucfirst( $reportData['pdf_orientation'] ) );
						$definition .= "\n<b>HTML Source:</b>\n";
						$definition .= $UITweaker->customReportsEscapeHTML(
						                str_replace( [ "\r\n", "\r" ], "\n", $reportData['pdf'] ) );
					}
					// Add the report to the simplified view.
					$UITweaker->addCustomReport( [ 'title' => $reportConfig['label'],
					                               'type' => $reportTypes[ $reportConfig['type'] ],
					                               'description' => trim( $description ),
					                               'permissions' => $permissions,
					                               'definition' => $definition,
					                               'options' => $options ], true );
				}
			}
			// Remove module settings from the external modules simplified view (report data will
			// be displayed on the reports simplified view so is not required here).
			if ( method_exists( $UITweaker, 'areExtModFuncExpected' ) &&
			     $UITweaker->areExtModFuncExpected() )
			{
				$UITweaker->addExtModFunc( 'advanced_reports', function( $data )
				{
					if ( $data['value'] == 'false' ||
					     substr( $data['setting'], 0, 7 ) == 'report-' )
					{
						return false;
					}
					return true;
				});
			}
		}
	}



	// Upon record save, handle the @ADVANCED-REPORT-SAVE action tag if present.
	function redcap_save_record( $projectID, $record, $instrument, $eventID, $groupID = null,
	                             $survey_hash = null, $response_id = null, $instance = 1 )
	{
		// Check each field on the instrument.
		$listFields = \REDCap::getDataDictionary( $projectID, 'array', false, null, $instrument );
		foreach ( $listFields as $fieldName => $infoField )
		{
			// Ignore fields which are not file upload fields.
			if ( $infoField['field_type'] != 'file' ||
			     $infoField['text_validation_type_or_show_slider_number'] == 'signature' )
			{
				continue;
			}
			// Taking into account @IF action tags, look for the @ADVANCED-REPORT-SAVE action tag
			// and extract the report name and any parameters.
			$annotation = \Form::replaceIfActionTag( $infoField['field_annotation'], $projectID,
			                                         $record, $eventID, $instrument, $instance );
			$reportSaveParams =
					\Form::getValueInParenthesesActionTag( $annotation, '@ADVANCED-REPORT-SAVE' );
			if ( trim( $reportSaveParams ) == '' ||
			     ! preg_match( '/^([\'"]?)([a-z0-9_]+)\g{1}' .
			                   '(?: *, *([\'"])((?(?=\g{3})|.)*?)\g{3})?$/',
			                   $reportSaveParams, $reportSaveRegexResult, PREG_UNMATCHED_AS_NULL ) )
			{
				continue;
			}
			$reportName = $reportSaveRegexResult[2];
			$reportParamString = $reportSaveRegexResult[4];
			$listParams = [];
			if ( $reportParamString !== null )
			{
				preg_match_all( '/(?:^|&)([^=&]+)(?:=([^&]+))?/', $reportParamString,
				                $listTemp, PREG_SET_ORDER );
				for ( $i = 0; $i < count( $listTemp ); $i++ )
				{
					$listTemp[$i][1] =
						\Piping::replaceVariablesInLabel( $listTemp[$i][1], $record, $eventID,
						                                  $instance, [], false, $projectID,
						                                  false, '', 1, false, false, $instrument );
					$listTemp[$i][2] =
						\Piping::replaceVariablesInLabel( $listTemp[$i][2], $record, $eventID,
						                                  $instance, [], false, $projectID,
						                                  false, '', 1, false, false, $instrument );
					$listParams[ $listTemp[$i][1] ] = $listTemp[$i][2];
				}
			}
			// Get the report and save to file field.
			$fnGetReport = function( $reportID, $listParams, $module )
			{
				$listReports = $module->getReportList();
				if ( ! isset( $listReports[ $reportID ] ) ||
				     ! isset( $listReports[ $reportID ]['saveable'] ) ||
				     ! $listReports[ $reportID ]['saveable'] ||
				     ! in_array( $listReports[ $reportID ]['type'], self::SAVEABLE_TYPES ) )
				{
					return null;
				}
				$disableAccessControl = true;
				$isInternalRequest = true;
				$oldGet = $_GET;
				$oldPost = $_POST;
				$_GET = $listParams;
				$_GET['pid'] = $oldGet['pid'];
				$_GET['report_id'] = $reportID;
				$_GET['download'] = '1';
				if ( isset( $listReports[ $reportID ]['as_image'] ) &&
				     $listReports[ $reportID ]['as_image'] && isset( $_GET['as_image'] ) )
				{
					unset( $_GET['download'] );
				}
				$_POST = [];
				ob_start();
				require $listReports[ $reportID ]['type'] . '_view.php';
				$data = ob_get_clean();
				$fileName = $reportID . '_' . date( 'Ymd_His' );
				if ( $listReports[ $reportID ]['type'] == 'pdf' )
				{
					$fileName .= '.pdf';
				}
				else
				{
					$fileName .= isset( $_GET['download'] ) ? '.csv' : '.png';
				}
				$_GET = $oldGet;
				$_POST = $oldPost;
				return [ $fileName, $data ];
			};
			list( $fileName, $reportData ) = $fnGetReport( $reportName, $listParams, $this );
			if ( $reportData === null )
			{
				continue;
			}
			$tempFile = $this->createTempFile();
			file_put_contents( $tempFile, $reportData );
			$docID = \REDCap::storeFile( $tempFile, $projectID, $fileName );
			if ( $docID == 0 )
			{
				continue;
			}
			\REDCap::addFileToField( $docID, $projectID, $record, $fieldName, $eventID, $instance );
		}
	}



	// Check if the specified report is accessible by the current user,
	// as determined by the specified access roles.
	function isReportAccessible( $reportID )
	{
		// Load the report config.
		$reportConfig = $this->getReportConfig( $reportID );
		// Always grant access to reports to users who can edit them.
		if ( $this->isReportEditable( $reportConfig['type'] ) )
		{
			return true;
		}
		// Check each allowed role and allow access if the user has the role.
		foreach ( explode( "\n", $reportConfig['roles_access'] ) as $role )
		{
			$role = trim( $role );
			if ( $role === '*' || $role === $this->getUserRole() )
			{
				return true;
			}
		}
		// Don't allow access for remaining users.
		return false;
	}



	// Check if the specified report can be downloaded by the current user,
	// as determined by the download setting and download roles.
	function isReportDownloadable( $reportID )
	{
		// Load the report config.
		$reportConfig = $this->getReportConfig( $reportID );
		// Don't allow downloads if they are deactivated.
		if ( ! isset( $reportConfig['download'] ) || ! $reportConfig['download'] )
		{
			return false;
		}
		// Otherwise, if downloads are activated...
		// Always allow downloads by users who can edit the report.
		if ( $this->isReportEditable( $reportConfig['type'] ) )
		{
			return true;
		}
		// Don't allow downloads by users who cannot access the report.
		if ( ! $this->isReportAccessible( $reportID ) )
		{
			return false;
		}
		// Check each allowed role and allow downloads if the user has the role.
		foreach ( explode( "\n", $reportConfig['roles_download'] ) as $role )
		{
			$role = trim( $role );
			if ( $role === '*' || $role === $this->getUserRole() )
			{
				return true;
			}
		}
		// Don't allow downloads for remaining users.
		return false;
	}



	// Check if the specified report type can be edited by the current user.
	function isReportEditable( $reportType = null )
	{
		// Administrators can edit all reports.
		$isSuperUser = $this->getUser()->isSuperUser();
		$userRights = $this->getUser()->getRights();
		if ( $isSuperUser )
		{
			return true;
		}

		// Don't allow editing by non-administrators without user rights.
		// (in practice, such users probably cannot access the project)
		// SQL reports are never editable by non-administrators.
		if ( $userRights === null || $reportType == 'sql' )
		{
			return false;
		}

		// Allow editing if enabled for the user's role (deprecated).
		if ( ( $this->getProjectSetting( 'edit-if-design' ) && $userRights[ 'design' ] == '1' ) ||
			 ( $this->getProjectSetting( 'edit-if-reports' ) && $userRights[ 'reports' ] == '1' ) )
		{
			return true;
		}

		// If module specific rights are enabled, use this to determine whether editing is allowed.
		if ( $this->getSystemSetting( 'config-require-user-permission' ) == 'true' )
		{
			return is_array( $userRights['external_module_config'] ) &&
			       in_array( 'advanced_reports', $userRights['external_module_config'] );
		}

		// Otherwise don't allow editing.
		return false;
	}



	// Add a new report, with the specified ID (unique name), report type, and label.
	function addReport( $reportID, $reportType, $reportLabel )
	{
		$projectID = $this->getProjectID();
		// Set the report configuration.
		$config = [ 'type' => $reportType, 'label' => $reportLabel, 'visible' => false,
		            'lastupdated_user' => USERID, 'lastupdated_time' => time() ];
		$this->setSystemSetting( "p$projectID-report-config-$reportID", json_encode( $config ) );
		// Add the report to the list of reports.
		$listIDs = $this->getSystemSetting( "p$projectID-report-list" );
		if ( $listIDs === null )
		{
			$listIDs = [];
		}
		else
		{
			$listIDs = array_values( json_decode( $listIDs, true ) );
		}
		$listIDs[] = $reportID;
		$this->setSystemSetting( "p$projectID-report-list", json_encode( $listIDs ) );
	}



	// Delete the specified report.
	function deleteReport( $reportID )
	{
		$projectID = $this->getProjectID();
		// Remove the report configuration and data.
		$this->removeSystemSetting( "p$projectID-report-config-$reportID" );
		$this->removeSystemSetting( "p$projectID-report-data-$reportID" );
		// Remove the report from the list of reports.
		$listIDs = $this->getSystemSetting( "p$projectID-report-list" );
		if ( $listIDs === null )
		{
			return;
		}
		$listIDs = json_decode( $listIDs, true );
		if ( $listIDs === null )
		{
			return;
		}
		if ( ( $k = array_search( $reportID, $listIDs ) ) !== false )
		{
			unset( $listIDs[$k] );
		}
		$listIDs = array_values( $listIDs );
		$this->setSystemSetting( "p$projectID-report-list", json_encode( $listIDs ) );
	}



	// Echo plain text to output (without Psalm taints).
	// Use only for e.g. JSON or CSV output.
	function echoText( $text )
	{
		$text = htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_XHTML );
		$chars = [ '&amp;' => 38, '&quot;' => 34, '&apos;' => 39, '&lt;' => 60, '&gt;' => 62 ];
		$text = preg_split( '/(&(?>amp|quot|apos|lt|gt);)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE );
		foreach ( $text as $part )
		{
			echo isset( $chars[ $part ] ) ? chr( $chars[ $part ] ) : $part;
		}
	}



	// Escapes text for inclusion in HTML.
	function escapeHTML( $text )
	{
		return htmlspecialchars( $text, ENT_QUOTES );
	}



	// Provide the report data for settings exports.
	function exportProjectSettings()
	{
		$projectID = $this->getProjectID();
		//$listSettings = $this->getProjectSettings();
		//unset( $listSettings['enabled'] );
		$listReportIDs = json_decode( $this->getSystemSetting( "p$projectID-report-list" ) ?? '[]',
		                              true ) ?? [];
		sort( $listReportIDs );
		$listSettings[] = [ 'key' => 'report-list', 'value' => $listReportIDs ];
		foreach ( $listReportIDs as $reportID )
		{
			$reportConfig = $this->getSystemSetting( "p$projectID-report-config-$reportID" );
			$reportConfig = json_decode( $reportConfig, true );
			unset( $reportConfig['lastupdated_user'], $reportConfig['lastupdated_time'],
			       $reportConfig['api_key'] );
			$reportConfig = json_encode( $reportConfig );
			$listSettings[] =
				[ 'key' => "report-config-$reportID", 'type' => 'json', 'value' => $reportConfig ];
			$listSettings[] =
				[ 'key' => "report-data-$reportID", 'type' => 'json',
				  'value' => $this->getSystemSetting( "p$projectID-report-data-$reportID" ) ];
		}
		return $listSettings;
	}



	// Returns a list of events for the project.
	function getEventList()
	{
		$listEventNames = \REDCap::getEventNames( false, true );
		$listUniqueNames = \REDCap::getEventNames( true );
		$listEvents = [];
		foreach ( $listEventNames as $eventID => $eventName )
		{
			$uniqueName = $listUniqueNames[ $eventID ];
			$listEvents[ $uniqueName ] = $eventName;
		}
		return $listEvents;
	}



	// Returns a list of fields for the project.
	function getFieldList( $fieldTypes = '*' )
	{
		$listTypes = explode( ',', $fieldTypes );
		$listFields = [];
		foreach ( \REDCap::getDataDictionary( 'array' ) as $infoField )
		{
			if ( $fieldTypes == '*' || in_array( $infoField['field_type'], $listTypes ) ||
			     ( in_array( 'date', $listTypes ) && $infoField['field_type'] == 'text' &&
			       substr( $infoField['text_validation_type_or_show_slider_number'],
			               0, 4 ) == 'date' ) ||
			     ( in_array( 'datetime', $listTypes ) && $infoField['field_type'] == 'text' &&
			       substr( $infoField['text_validation_type_or_show_slider_number'],
			               0, 8 ) == 'datetime' ) )
			{
				$fieldLabel = trim( $infoField['field_label'] );
				if ( strlen( $fieldLabel ) > 45 )
				{
					$fieldLabel = explode( "\n", $fieldLabel );
					$fieldLabel = trim( $fieldLabel[0] );
					if ( strlen( $fieldLabel ) > 45 )
					{
						$fieldLabel =
							substr( $fieldLabel, 0, 35 ) . ' ... ' . substr( $fieldLabel, -5 );
					}
				}
				else
				{
					$fieldLabel = str_replace( [ "\r", "\n" ], ' ', $fieldLabel );
				}
				$listFields[ $infoField['field_name'] ] =
					$infoField['field_name'] . ' - ' . $fieldLabel;
			}
		}
		return $listFields;
	}



	// Returns a list of instruments for the project.
	function getInstrumentList()
	{
		$listInstruments = [];
		foreach ( \REDCap::getInstrumentNames() as $instrumentID => $instrumentName )
		{
			$listInstruments[ $instrumentID ] = $instrumentID . ' - ' . $instrumentName;
		}
		return $listInstruments;
	}



	// Get the configuration for the specified report.
	// Optionally specify the configuration option name, otherwise all options are returned.
	function getReportConfig( $reportID, $configName = null )
	{
		$projectID = $this->getProjectID();
		$config = $this->getSystemSetting( "p$projectID-report-config-$reportID" );
		if ( $config !== null )
		{
			$config = json_decode( $config, true );
			if ( $config !== null && $configName !== null )
			{
				if ( array_key_exists( $configName, $config ) )
				{
					$config = $config[ $configName ];
				}
				else
				{
					$config = null;
				}
			}
		}
		return $config;
	}



	// Get the report definition data for the specified report.
	function getReportData( $reportID )
	{
		$projectID = $this->getProjectID();
		$data = $this->getSystemSetting( "p$projectID-report-data-$reportID" );
		if ( $data !== null )
		{
			$data = json_decode( $data, true );
		}
		return $data;
	}



	// Gets the list of reports, with the configuration data for each report.
	function getReportList()
	{
		$projectID = $this->getProjectId();
		if ( $projectID === null )
		{
			return [];
		}
		$listIDs = $this->getSystemSetting( "p$projectID-report-list" );
		if ( $listIDs === null )
		{
			return [];
		}
		$listIDs = json_decode( $listIDs, true );
		$listReports = [];
		foreach ( $listIDs as $id )
		{
			$infoReport = [];
			$config = $this->getReportConfig( $id );
			if ( $config !== null )
			{
				$infoReport = $config;
			}
			$listReports[ $id ] = $infoReport;
		}
		return $listReports;
	}



	// Get the list of report types.
	function getReportTypes()
	{
		return [ 'accumulation' => 'Accumulation',
		         'gantt' => 'Gantt',
		         'instrument' => 'Instrument Query',
		         'pdf' => 'PDF',
		         'recordtbl' => 'Record Table',
		         'sql' => 'SQL' ];
	}



	// Get the role name of the current user.
	function getUserRole()
	{
		$userRights = $this->getUser()->getRights();
		if ( $userRights === null )
		{
			return null;
		}
		if ( $userRights[ 'role_id' ] === null )
		{
			return null;
		}
		return $userRights[ 'role_name' ];
	}



	// Create a link for the current page with a modified query string variable.
	function makeQueryLink( $label, $variable, $value = '' )
	{
		if ( $_GET[ $variable ] == $value )
		{
			return '<em>' . htmlspecialchars( $label ) . '</em>';
		}
		return '<a href="' . htmlspecialchars( $this->makeQueryURL( $variable, $value ) ) .
		       '">' . htmlspecialchars( $label ) . '</a>';
	}



	// Create a URL for the current page with a modified query string variable.
	function makeQueryURL( $variable, $value = '' )
	{
		$url = $_SERVER[ 'REQUEST_URI' ];
		$queryStart = strpos( $url, '?' );
		$urlVariable = rawurlencode( $variable );
		if ( $queryStart === false )
		{
			$urlBase = $url;
			$urlQuery = '';
		}
		else
		{
			$urlBase = substr( $url, 0, $queryStart );
			$urlQuery = substr( $url, $queryStart + 1 );
			$urlQuery = explode( '&', $urlQuery );
			foreach ( $urlQuery as $index => $item )
			{
				if ( substr( $item, 0, strlen( $urlVariable ) + 1 ) == "$urlVariable=" )
				{
					unset( $urlQuery[ $index ] );
				}
			}
			$urlQuery = implode( '&', $urlQuery );
		}
		$url = $urlBase . ( $urlQuery == '' ? '' : ( '?' . $urlQuery ) );
		if ( $value != '' )
		{
			$url .= ( $urlQuery == '' ? '?' : '&' );
			$url .= $urlVariable . '=' . rawurlencode( $value );
		}
		return $url;
	}



	// Outputs JavaScript to create text/combo-boxes.
	function outputComboboxJS()
	{

?>
  <script type="text/javascript">
  $( function() {
    $.widget( "advrep.combobox",
    {
      _create: function()
      {
        this.wrapper = $( '<span style="display:flex">' )
        this.wrapper.addClass( 'advrep-combobox' ).insertAfter( this.element )
        this.element.appendTo( this.wrapper )
        this.element
          .addClass( 'advrep-combobox-input ui-widget ui-widget-content ' +
                     'ui-state-default ui-corner-left' )
          .autocomplete( { delay: 0, minLength: 0, source: this._source.bind( this ) } )
          .tooltip( { classes: { 'ui-tooltip': 'ui-state-highlight' } } )
        this._createShowAllButton()
      },
      _createShowAllButton: function()
      {
        var input = this.element, wasOpen = false;
        $( '<a>' )
          .attr( 'tabIndex', -1 )
          .appendTo( this.wrapper )
          .button( { icons: { primary: 'ui-icon-triangle-1-s' }, text: false } )
          .removeClass( 'ui-corner-all' )
          .addClass( 'advrep-combobox-toggle ui-corner-right' )
          .on( 'mousedown', function()
          {
            wasOpen = input.autocomplete( "widget" ).is( ":visible" )
          } )
          .on( 'click', function()
          {
            input.trigger( 'focus' )
            if ( wasOpen ) { return }
            input.autocomplete( 'search', '' );
          })
      },
      _source: function( request, response )
      {
        var input = this.element
        var matcher = new RegExp( $.ui.autocomplete.escapeRegex(request.term), "i" );
        response( $('#'+$(input).data('list')).children( "option" ).map(function() {
          var text = $( this ).text();
          if ( this.value && ( !request.term || matcher.test(text) ) )
            return {
              label: text,
              value: text,
              option: this
            };
        }) );
      },
      _destroy: function()
      {
        this.element.insertBefore( this.wrapper )
        this.wrapper.remove();
      }
    } )
  } )
  </script>
<?php

	}



	// Output a drop-down list of events for the project.
	function outputEventDropdown( $dropDownName, $value )
	{
		echo '<select name="', htmlspecialchars( $dropDownName ), '">';
		echo '<option value=""', ( $value == '' ? ' selected' : '' ), '></option>';
		foreach ( $this->getEventList() as $optValue => $optLabel )
		{
			echo '<option value="', htmlspecialchars( $optValue ), '"',
			     ( $value == $optValue ? ' selected' : '' ), '>',
			     htmlspecialchars( $optLabel ), '</option>';
		}
		echo '</select>';
	}



	// Output a drop-down list of fields for the project.
	function outputFieldDropdown( $dropDownName, $value, $fieldType = '*' )
	{
		echo '<select name="', htmlspecialchars( $dropDownName ), '">';
		echo '<option value=""', ( $value == '' ? ' selected' : '' ), '></option>';
		foreach ( $this->getFieldList( $fieldType ) as $optValue => $optLabel )
		{
			echo '<option value="', htmlspecialchars( $optValue ), '"',
			     ( $value == $optValue ? ' selected' : '' ), '>',
			     htmlspecialchars( $optLabel ), '</option>';
		}
		echo '</select>';
	}



	// Output a drop-down list of instruments for the project.
	function outputInstrumentDropdown( $dropDownName, $value )
	{
		echo '<select name="', htmlspecialchars( $dropDownName ), '">';
		echo '<option value=""', ( $value == '' ? ' selected' : '' ), '></option>';
		foreach ( $this->getInstrumentList() as $optValue => $optLabel )
		{
			echo '<option value="', htmlspecialchars( $optValue ), '"',
			     ( $value == $optValue ? ' selected' : '' ), '>',
			     htmlspecialchars( $optLabel ), '</option>';
		}
		echo '</select>';
	}



	// Output the form controls to set the report configuration on the edit report page.
	// These are the settings which are the same for all reports.
	function outputReportConfigOptions( $reportConfig,
	                                    $includeDownload = true, $includeAdditional = [] )
	{
		if ( ! is_array( $includeAdditional ) )
		{
			if ( is_string( $includeAdditional ) )
			{
				$includeAdditional = [ $includeAdditional ];
			}
			else
			{
				$includeAdditional = [];
			}
		}

?>
  <tr><th colspan="2">Report Label and Category</th></tr>
  <tr>
   <td>Report Label</td>
   <td>
    <input type="text" name="report_label" required
           value="<?php echo $this->escapeHTML( $reportConfig['label'] ); ?>">
   </td>
  </tr>
  <tr>
   <td>Report Category</td>
   <td>
    <input type="text" name="report_category"
           value="<?php echo $this->escapeHTML( $reportConfig['category'] ); ?>">
   </td>
  </tr>
  <tr>
   <td>Report Annotation</td>
   <td>
    <textarea name="report_annotation"><?php
		echo $this->escapeHTML( $reportConfig['annotation'] ?? '' ); ?></textarea>
    <br>
    <span class="field-desc">
     The report annotation will not be shown on the report.
    </span>
   </td>
  </tr>
  <tr><th colspan="2">Access Permissions</th></tr>
  <tr>
   <td>Report is visible</td>
   <td>
    <label>
     <input type="radio" name="report_visible" value="Y" required<?php
		echo $reportConfig['visible'] ? ' checked' : ''; ?>> Yes
    </label>
    <br>
    <label>
     <input type="radio" name="report_visible" value="N" required<?php
		echo $reportConfig['visible'] ? '' : ' checked'; ?>> No
    </label>
    <br>
    <span class="field-desc">
     If a report is visible, it will be listed on the Advanced Reports page.
     <br>
     Reports which are not visible can still be viewed by users with access, if they have the URL.
    </span>
   </td>
  </tr>
  <tr>
   <td>Grant access to roles</td>
   <td>
    <textarea name="report_roles_access"><?php
		echo $this->escapeHTML( $reportConfig['roles_access'] ); ?></textarea>
    <br>
    <span class="field-desc">
     Enter each role name on a separate line.
     <br>
     If left blank, the report will be accessible to users with edit access.
     <br>
     Enter * to grant access to all users.
    </span>
   </td>
  </tr>
<?php
		if ( $includeDownload )
		{
?>
  <tr>
   <td>Allow downloads</td>
   <td>
    <label>
     <input type="radio" name="report_download" value="Y" required<?php
			echo $reportConfig['download'] ? ' checked' : ''; ?>> Yes
    </label>
    <br>
    <label>
     <input type="radio" name="report_download" value="N" required<?php
			echo $reportConfig['download'] ? '' : ' checked'; ?>> No
    </label>
   </td>
  </tr>
  <tr>
   <td>Grant downloads to roles</td>
   <td>
    <textarea name="report_roles_download"><?php
			echo $this->escapeHTML( $reportConfig['roles_download'] ); ?></textarea>
    <br>
    <span class="field-desc">
     Enter each role name on a separate line. Reports can only be downloaded by users with access.
     <br>
     If left blank, the report can be downloaded by users with edit access.
     <br>
     Enter * to allow downloads by all users with access.
    </span>
   </td>
  </tr>
<?php
		}

		if ( in_array( 'saveable', $includeAdditional ) )
		{
?>
  <tr>
   <td>Report can be saved to a field</td>
   <td>
    <label>
     <input type="radio" name="report_saveable" value="Y" required<?php
			echo $reportConfig['saveable'] ? ' checked' : ''; ?>> Yes
    </label>
    <br>
    <label>
     <input type="radio" name="report_saveable" value="N" required<?php
			echo $reportConfig['saveable'] ? '' : ' checked'; ?>> No
    </label>
    <br>
    <span class="field-desc">
     If set to <i>yes</i>, this report can be saved to a file field using the @ADVANCED-REPORT-SAVE
     action tag.
     <br>
     Once a report is saved to a field, it will be accessible by anyone with access to the record
     and instrument.
    </span>
   </td>
  </tr>
<?php
		}

		if ( in_array( 'image', $includeAdditional ) )
		{
?>
  <tr>
   <td>Allow retrieval as image</td>
   <td>
    <label>
     <input type="radio" name="report_as_image" value="Y" required<?php
			echo $reportConfig['as_image'] ? ' checked' : ''; ?>> Yes
    </label>
    <br>
    <label>
     <input type="radio" name="report_as_image" value="N" required<?php
			echo $reportConfig['as_image'] ? '' : ' checked'; ?>> No
    </label>
    <br>
    <span class="field-desc">
     If enabled, the report can be retrieved as an image by all users with access.
    </span>
   </td>
  </tr>
<?php
		}

		if ( in_array( 'api', $includeAdditional ) )
		{
?>
  <tr>
   <td>Allow API access</td>
   <td>
    <label>
     <input type="radio" name="report_as_api" value="Y" required<?php
			echo $reportConfig['as_api'] ? ' checked' : ''; ?>> Yes
    </label>
    <br>
    <label>
     <input type="radio" name="report_as_api" value="N" required<?php
			echo $reportConfig['as_api'] ? '' : ' checked'; ?>> No
    </label>
    <br>
    <span class="field-desc">
     If enabled, the report can be accessed using the API at the following URL:<br>
     <?php echo $this->getUrl( 'api.php?report_id=' . $_GET['report_id'], true, true ), "\n"; ?>
    </span>
    <br>
    API Key: <input type="password" name="report_api_key" style="width:320px;font-size:0.9em"
                    onmouseover="$(this).attr('type','text')"
                    onmouseout="$(this).attr('type','password')"
                    value="<?php echo $reportConfig['api_key'] ?? ''; ?>" readonly>
    <a href="#" style="margin:left:10px;font-size:0.9em"
       onclick="if(confirm('Generate new API key?\nThis will replace the current API key.'))
                {$('[name=report_api_key]').val('<?php echo sha1( random_bytes(100) ) ?>');
                $(this).css('display','none')};return false">Generate new API key</a>
   </td>
  </tr>
<?php
		}

		if ( in_array( 'public', $includeAdditional ) )
		{
?>
  <tr>
   <td>Allow public access</td>
   <td>
    <label>
     <input type="radio" name="report_as_public" value="Y" required<?php
			echo $reportConfig['as_public'] ? ' checked' : ''; ?>> Yes
    </label>
    <br>
    <label>
     <input type="radio" name="report_as_public" value="N" required<?php
			echo $reportConfig['as_public'] ? '' : ' checked'; ?>> No
    </label>
    <br>
    <span class="field-desc">
     If enabled, the report can be accessed publicly at the following URL:<br>
     <?php echo $this->getUrl( 'public.php?report_id=' . $_GET['report_id'], true, true ), "\n"; ?>
<?php
			if ( $includeDownload )
			{
?>
     <br>
     If report downloads are enabled, the report can be downloaded by all users who view the report
     at the public URL.
<?php
			}
?>
    </span>
   </td>
  </tr>
<?php
		}
	}



	// Output the report navigation links.
	function outputViewReportHeader( $reportLabel, $reportType, $canReset = false )
	{
		$canDownload = $this->isReportDownloadable( $_GET['report_id'] );
		$this->writeStyle();

?>
<div class="projhdr">
 <?php echo htmlspecialchars( $reportLabel ), "\n"; ?>
</div>
<p style="font-size:11px" class="hide_in_print">
 <a href="<?php echo $this->getUrl( 'reports.php' )
?>"><i class="fas fa-arrow-circle-left fs11"></i> Back to Advanced Reports</a>
<?php

		// If report can be downloaded, show the download link.
		if ( $canDownload )
		{
			$extraVarsDL = '';
			parse_str( $_SERVER['QUERY_STRING'], $getVars );
			foreach ( $getVars as $getVar => $getVal )
			{
				if ( ! in_array( $getVar, [ 'as_image', 'download', 'page', 'pid', 'prefix',
				                            'report_id', 'report_state' ] ) )
				{
					$extraVarsDL .= '&' . $getVar . '=' . rawurlencode( $getVal );
				}
			}

?>
 &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
 <a href="<?php
			echo $this->getUrl( $reportType . '_view.php?report_id=' . $_GET['report_id'] .
			                    $extraVarsDL . '&download=1' );
?>"><i class="fas fa-file-download fs11"></i> Download report</a>
<?php

		}

		// If the user can edit the report, show an edit link.
		if ( $this->isReportEditable( $reportType ) )
		{

?>
 &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
 <a href="<?php
			echo $this->getUrl( $reportType . '_edit.php?report_id=' . $_GET['report_id'] );
?>"><i class="fas fa-pencil-alt fs11"></i> Edit report</a>
<?php

		}

		// If applicable for the report type, show a link to reset the report state.
		if ( $canReset )
		{

?>
 <span id="mod-advrep-resetstate" style="display:none">
  &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
  <a href="<?php
			echo $this->escapeHTML( preg_replace( '/&report_state=[^&]+/', '',
			                                      $_SERVER['REQUEST_URI'] ) );
?>"><i class="fas fa-rotate-left fs11"></i> Reset</a>
 </span>
<?php

		}

?>
</p>
<?php

	}



	// Output the JavaScript for view report pages, to provide sorting/filtering.
	function outputViewReportJS()
	{

?>
<script type="text/javascript">
  $(function()
  {

    var vReportParams = {}
    try
    {
      vReportParams =
          JSON.parse(new URLSearchParams(new URL(window.location).search).get('report_state'))
      if ( vReportParams === null )
      {
        vReportParams = {}
      }
      else
      {
        $('#mod-advrep-resetstate').css('display','')
      }
    } catch (e) {} // Keep default value if exception.


    var filterTable = function()
    {
      var vShowAllRows = true
      var vShowNumRows = 0
      var vHeader = $('.mod-advrep-datatable thead th')
      $('.mod-advrep-datatable tbody tr').each(function(indexTr,elemTr)
      {
        var vShowRow = true
        $(elemTr).find('td').each(function(indexTd,elemTd)
        {
          var vFilter = vHeader[indexTd].getAttribute('data-filter')
          var vText = $(elemTd).text()
          if ( vFilter !== null && vFilter != '' &&
               ! vText.toLowerCase().includes( vFilter.toLowerCase() ) )
          {
            vShowRow = false
          }
        })

        if ( vShowRow )
        {
          elemTr.style.display = ''
          vShowNumRows++
        }
        else
        {
          elemTr.style.display = 'none'
          vShowAllRows = false
        }

      })

      restyleTable()
      $('#filtercount').text( vShowAllRows ? '' : ( vShowNumRows + ' / ' ) )

    }


    var restyleTable = function()
    {
      var vIndex = 0
      $('.mod-advrep-datatable tbody tr').each(function(indexTr,elemTr)
      {
        $(elemTr).removeClass('odd even')
        if ( elemTr.style.display != 'none' )
        {
          $(elemTr).addClass( vIndex % 2 == 0 ? 'odd' : 'even' )
          vIndex++
        }
      })
    }


    var updateURL = function()
    {
      var objURL = new URL(window.location)
      var objParams = new URLSearchParams(objURL.search)
      var newState = JSON.stringify(vReportParams)
      if ( newState == '{}' )
      {
        objParams.delete('report_state')
        $('#mod-advrep-resetstate').css('display','none')
      }
      else
      {
        objParams.set('report_state',newState)
        $('#mod-advrep-resetstate').css('display','')
      }
      objURL.search = objParams.toString()
      history.replaceState( null, null, objURL.toString() )
    }


    $('body').append('<datalist id="mod-advrep-filterlist"></datalist>')
    $('.mod-advrep-datatable th').each(function(index, elem)
    {
      $(elem).append('<span style="cursor:pointer;position:absolute;right:5px;bottom:10px" ' +
                     'class="fas fa-filter" title="Filter rows by this field..."></span>')

      if ( vReportParams.filter != undefined &&
           vReportParams.filter[elem.getAttribute('data-colnum')] != undefined )
      {
        elem.setAttribute('data-filter', vReportParams.filter[elem.getAttribute('data-colnum')])
        $(elem).find('.fas')[0].style.color = '#7a80dd'
      }

      $(elem).find('.fas').click(function(ev)
      {
        ev.stopPropagation()
        var vIcon = this
        var vFilter = elem.getAttribute('data-filter')
        if ( vFilter == null )
        {
          vFilter = ''
        }
        var vColNum = elem.getAttribute('data-colnum')
        var vItems = JSON.parse( elem.getAttribute('data-items') )
        $('#mod-advrep-filterlist').empty()
        if ( vItems !== false && vItems.length > 0 )
        {
          for ( var i = 0; i < vItems.length; i++ )
          {
            $('#mod-advrep-filterlist').append($('<option></option>').text(vItems[i]))
          }
        }
        var vDialog = $('<div><input type="text" style="width:350px" ' +
                        'list="mod-advrep-filterlist"></div>')
        vDialog.find('input[type="text"]').val(vFilter)
        vDialog.dialog(
        {
          autoOpen:true,
          buttons:{
            Reset : function()
            {
              vFilter = ''
              vDialog.dialog('close')
            },
            Filter : function()
            {
              vFilter = vDialog.find('input[type="text"]').val()
              vDialog.dialog('close')
            }
          },
          close: function()
          {
            elem.setAttribute('data-filter', vFilter)
            if ( vFilter == '' )
            {
              if ( vReportParams.filter != undefined && vReportParams.filter[vColNum] != undefined )
              {
                delete vReportParams.filter[vColNum]
              }
              if ( vReportParams.filter != undefined &&
                   Object.keys(vReportParams.filter).length == 0 )
              {
                delete vReportParams.filter
              }
            }
            else
            {
              if ( vReportParams.filter == undefined )
              {
                vReportParams.filter = {}
              }
              vReportParams.filter[vColNum] = vFilter
            }
            updateURL()
            filterTable()
            vIcon.style.color = ( vFilter == '' ) ? '' : '#7a80dd'
          },
          modal:true,
          resizable:false,
          title:'Enter filter text',
          width:400
        })
      })

    })
    filterTable()


    $('.sorting').click(function()
    {
      var vColNum = this.getAttribute('data-colnum') - 0
      if ( vReportParams.sort == undefined )
      {
        vReportParams.sort = []
      }
      vReportParams.sort = vReportParams.sort.filter( function(i) { return i.col != vColNum } )
      SortTable( 'mod-advrep-table', $(this).index(), this.getAttribute('data-type') )
      restyleTable()
      var vIsAsc = $(this).hasClass('sorting_asc')
      $(this).parent().find('th').removeClass('sorting_asc sorting_desc')
      $(this).addClass( vIsAsc ? 'sorting_desc' : 'sorting_asc' )
      vReportParams.sort.unshift( { col: vColNum, dir: ( vIsAsc ? 'desc' : 'asc' ) } )
      updateURL()
    });


<?php
		$userDateFormat = \DateTimeRC::get_user_format_base();
		if ( $userDateFormat == 'DMY' || $userDateFormat == 'MDY' )
		{
			$userDateSubstr = $userDateFormat == 'DMY' ? [ '3,5', '0,2' ] : [ '0,2', '3,5' ];
?>
    var vDateParse = Date.parse
    Date.parse = function ( vDateVal )
    {
      if ( /^[0-9]{2}[^0-9][0-9]{2}[^0-9][0-9]{4}([^0-9]|$)/.test( vDateVal ) )
      {
        vDateVal = '' + vDateVal.substring(6,10) + vDateVal.substring(5,6) +
                   vDateVal.substring(<?php echo $userDateSubstr[0]; ?>) + vDateVal.substring(2,3) +
                   vDateVal.substring(<?php echo $userDateSubstr[1]; ?>) + vDateVal.substring(10)
      }
      return vDateParse( vDateVal )
    };
<?php
		}
?>


    var vHeader = $('.mod-advrep-datatable thead th')
    $('.mod-advrep-datatable tbody tr').each(function(indexTr,elemTr)
    {
      $(elemTr).find('td').each(function(indexTd,elemTd)
      {
        var vText = $(elemTd).text()
        if ( typeof $(elemTd).attr('data-sortvalue') != 'undefined' )
        {
          vText = $(elemTd).attr('data-sortvalue')
        }
        var vItems = vHeader[indexTd].getAttribute('data-items')
        vItems = JSON.parse( vItems === null ? '[]' : vItems )
        if ( vItems !== false && vText != '' && vItems.indexOf( vText ) == -1 )
        {
          vItems.push( vText )
          if ( vItems.length > 30 )
          {
            vItems = false
          }
          else
          {
            vItems.sort()
          }
          vHeader[indexTd].setAttribute('data-items', JSON.stringify(vItems))
        }
        var vType = vHeader[indexTd].getAttribute('data-type')
        if ( vType === 'string' )
        {
          return
        }
        if ( new RegExp('^(0|-?[1-9][0-9]*)$').test(vText) ) // int
        {
          if ( vType == null )
          {
            vHeader[indexTd].setAttribute('data-type', 'int')
          }
          else if ( vType !== 'int' && vType !== 'float' )
          {
            vHeader[indexTd].setAttribute('data-type', 'string')
          }
        }
        else if ( new RegExp('^(0|-?[1-9][0-9]*)\\.[0-9]+$').test(vText) ) // float
        {
          if ( vType == null || vType === 'int' )
          {
            vHeader[indexTd].setAttribute('data-type', 'float')
          }
          else if ( vType !== 'float' )
          {
            vHeader[indexTd].setAttribute('data-type', 'string')
          }
        }
        else if ( ! Number.isNaN( Date.parse( vText ) ) ) // date
        {
          if ( vType == null )
          {
            vHeader[indexTd].setAttribute('data-type', 'date')
          }
          else if ( vType !== 'date' )
          {
            vHeader[indexTd].setAttribute('data-type', 'string')
          }
        }
        else if ( vText != '' ) // string
        {
          vHeader[indexTd].setAttribute('data-type', 'string')
        }
      })
    })
    vHeader.each(function(indexTh,elemTh)
    {
      if ( elemTh.getAttribute('data-type') == null )
      {
        elemTh.setAttribute('data-type', 'string')
      }
    });


    var vOldRowCompare = RowCompare
    var vOldRowCompareIntegers = RowCompareIntegers
    var vOldRowCompareNumbers = RowCompareNumbers
    var vOldRowCompareDates = RowCompareDates
    var vFuncCompareStVals = function ( a, b, f )
    {
      var vElemA = $(a.getElementsByTagName('td')[lastSort])
      var vElemB = $(b.getElementsByTagName('td')[lastSort])
      if ( typeof vElemA.attr('data-sortvalue') == 'undefined' ||
           typeof vElemB.attr('data-sortvalue') == 'undefined' )
      {
        return f( a, b )
      }
      vElemA.attr('data-displayvalue',vElemA.html())
      vElemA.text(vElemA.attr('data-sortvalue'))
      vElemB.attr('data-displayvalue',vElemB.html())
      vElemB.text(vElemB.attr('data-sortvalue'))
      var vCompareResult = f( a, b )
      vElemA.html(vElemA.attr('data-displayvalue'))
      vElemA.attr('data-displayvalue',null)
      vElemB.html(vElemB.attr('data-displayvalue'))
      vElemB.attr('data-displayvalue',null)
      return vCompareResult
    };
    RowCompare = function(a, b){ return vFuncCompareStVals(a, b, vOldRowCompare) };
    RowCompareIntegers = function(a, b){ return vFuncCompareStVals(a, b, vOldRowCompareIntegers) };
    RowCompareNumbers = function(a, b){ return vFuncCompareStVals(a, b, vOldRowCompareNumbers) };
    RowCompareDates = function(a, b){ return vFuncCompareStVals(a, b, vOldRowCompareDates) };


    (function()
    {
      if ( vReportParams.sort != undefined && Array.isArray( vReportParams.sort ) )
      {
        for ( var i = vReportParams.sort.length - 1; i >= 0; i-- )
        {
          var vColNum = vReportParams.sort[i].col
          var vSortDir = vReportParams.sort[i].dir
          var vColHdr = $('th[data-colnum="' + vColNum + '"]')
          var vDataType = vColHdr[0].getAttribute('data-type')
          SortTable( 'mod-advrep-table', vColNum, vDataType )
          if ( vSortDir == 'desc' )
          {
            SortTable( 'mod-advrep-table', vColNum, vDataType )
          }
          if ( i == 0 )
          {
            vColHdr.addClass( vSortDir == 'desc' ? 'sorting_desc' : 'sorting_asc' )
          }
        }
      }
    })()

  })
</script>
<?php

	}



	// Returns the supplied string with any HTML entity encoded, with the exception of hyperlinks,
	// bold and italic tags (if not nested). Placeholders for the project ID and web root path are
	// replaced with the corresponding values.
	// This is used primarily for report descriptions.
	function parseDescription( $str )
	{
		$str = str_replace( '$$PROJECT$$', intval( $this->getProjectId() ), $str );
		$str = str_replace( '$$WEBROOT$$', APP_PATH_WEBROOT, $str );
		return $this->parseHTML( $str );
	}



	// Returns the supplied string with any HTML entity encoded, with the exception of hyperlinks,
	// bold and italic tags. Nested tags are not supported and will remain entity encoded.
	// If the $forDownload parameter is true, supported HTML tags will be stripped instead.
	/** @psalm-pure */
	function parseHTML( $str, $forDownload = false )
	{
		if ( $forDownload )
		{
			return preg_replace( '/<((?<t1>a) href="[^"]*"( target="_blank")?|(?<t2>b|i))>(.*?)' .
			                     '<\/((?P=t1)|(?P=t2))>/', '$5', $str );
		}
		$fnParse = function( $m )
		{
			return '<' . $m[2] . $m[5] .
			       ( $m[2] == '' ? '' : ( ' href="' . $m[3] . '"' .
			                              ( $m[4] == '' ? '' : ' target="_blank"' ) ) ) .
			       '>' . $m[6] . '</' . $m[7] . '>';
		};
		return nl2br(
		        preg_replace_callback( '/&lt;((?<t1>a) href=&quot;((?(?=&quot;)|.)*)&quot;( ' .
		                               'target=&quot;_blank&quot;)?|(?<t2>b|i))&gt;(.*?)&lt;\/' .
		                               '((?P=t1)|(?P=t2))&gt;/',
		                               $fnParse, htmlspecialchars( $str, ENT_QUOTES ) ) );
	}



	// Takes logic and parses it, returning an array consisting of a function and information about
	// the parameters to be passed to the function to evaluate the logic.
	function parseLogic( $str, $requestType = false,
	                     $allowEditable = false, $createFunction = true )
	{
		// If an editable value is requested...
		if ( $allowEditable && preg_match( '/((\[[A-Za-z0-9_]+\]){2}):edit/', $str ) )
		{
			$str = str_replace( ':edit', '[3]', $str );
		}
		// Otherwise...
		else
		{
			$listStr = preg_split('/([\'"])/', $str, -1, PREG_SPLIT_DELIM_CAPTURE );
			$quote = '';
			$str = '';
			foreach ( $listStr as $strPart )
			{
				if ( $quote == '' && ( $strPart == "'" || $strPart == '"' ) )
				{
					$quote = $strPart;
				}
				elseif ( $quote != '' && $quote == $strPart )
				{
					$quote = '';
				}
				elseif ( $quote == '' )
				{
					// Convert [value] and [label] parameters after a field to numbers so they will
					// be accepted by the REDCap logic lexer/parser.
					$strPart = preg_replace( '/((\[[A-Za-z0-9_]+\]){2}):value/',
					                         '$1[1]', $strPart );
					$strPart = preg_replace( '/((\[[A-Za-z0-9_]+\]){2}):label/',
					                         '$1[2]', $strPart );
					// Also pipe in the values for any smart variables.
					$strPart = str_replace( '[is-download]',
					                        ( $requestType == 'download' ? '1' : '0' ), $strPart );
					$strPart = str_replace( '[is-api]',
					                        ( $requestType == 'api' ? '1' : '0' ), $strPart );
					$strPart =
						preg_replace_callback( '/\[q(int|str):([a-z0-9_]+)\]/', function ( $m )
						{
							if ( !isset( $_GET[ $m[2] ] ) )
							{
								return "''";
							}
							if ( $m[1] == 'int' )
							{
								if ( preg_match( '/^(0|(-?[1-9][0-9]*))$/', $_GET[ $m[2] ] ) )
								{
									return $_GET[ $m[2] ];
								}
								return "''";
							}
							return "'" . str_replace( "'", '', $_GET[ $m[2] ] ) . "'";
						}, $strPart );
					try
					{
						$strPart = \Piping::pipeSpecialTags( $strPart, $this->getProjectId(),
						                                     wrapInQuotes: true,
						                                     isUsedInCalcBranching: true );
					}
					catch ( \Error $e )
					{
						$strPart = \Piping::pipeSpecialTags( $strPart, $this->getProjectId(),
						                                     wrapInQuotes: true );
					}
				}
				$str .= $strPart;
			}
		}

		// Parse the logic.
		$lp = new \LogicParser();
		$logic = $lp->parse( $str, null, $createFunction );
		// If a function has not been created, just return the value from the REDCap logic parser.
		if ( ! $createFunction || ! is_array( $logic ) )
		{
			return $logic;
		}
		// Reformat the REDCap parser parameter details output.
		$logicParams = [];
		foreach ( $logic[1] as $param )
		{
			if ( $param[0] == '' || $param[1] == '' )
			{
				throw new \Exception( 'Invalid field identifier.' );
			}
			$dataType = null;
			if ( $param[3] === '1' )
			{
				$dataType = 'value';
			}
			elseif ( $param[3] === '2' )
			{
				$dataType = 'label';
			}
			elseif ( $allowEditable && $param[3] === '3' )
			{
				$dataType = 'edit';
			}
			$logicParams[] = [ $param[0], $param[1], $dataType ];
		}
		return [ $logic[0], $logicParams ];
	}



	// Perform grouping on an array of values.
	function performGrouping( $listValues, $funcName )
	{
		if ( $funcName == 'max' )
		{
			return array_reduce( $listValues,
			                     function ( $c, $i )
			                     {
			                         if ( $i != '' && ( $c == '' || $c < $i ) ) return $i;
			                         else return $c;
			                     }, '' );
		}
		if ( $funcName == 'min' )
		{
			return array_reduce( $listValues,
			                     function ( $c, $i )
			                     {
			                         if ( $i != '' && ( $c == '' || $c > $i ) ) return $i;
			                         else return $c;
			                     }, '' );
		}
		if ( $funcName == 'mean' || $funcName == 'sum' || $funcName == 'percent' )
		{
			$sum = 0;
			$count = 0;
			foreach ( $listValues as $value )
			{
				if ( preg_match( '/^[0-9]+(\.[0-9]+)?$/', $value ) )
				{
					$sum += $value;
					$count++;
				}
				elseif ( preg_match( '^[0-9]+(\.[0-9]+)?\/[0-9]+$', $value ) )
				{
					$value = explode( '/', $value );
					$sum += $value[0];
					$count += $value[1];
				}
			}
			if ( $count == 0 )
			{
				return 0 . ( $funcName == 'percent' ? '%' : '' );
			}
			return ( ( $sum / ( $funcName == 'sum' ? 1 : $count ) ) *
			         ( $funcName == 'percent' ? 100 : 1 ) ) .
			       ( $funcName == 'percent' ? '%' : '' );
		}
		if ( $funcName == 'median' )
		{
			$listValues = array_reduce( $listValues,
			                            function( $c, $i )
			                            {
			                                if ( $i != '' ) $c[] = $i;
			                                return $c;
			                            }, [] );
			if ( empty( $listValues ) )
			{
				return 0;
			}
			sort( $listValues );
			$count = count( $listValues );
			if ( $count % 2 == 0 )
			{
				return ( $listValues[ ( $count / 2 ) - 1 ] + $listValues[ $count / 2 ] ) / 2;
			}
			return $listValues[ floor( $count / 2 ) ];
		}
	}



	// Replace logic vars with values (do a search/replace excluding string values).
	function replaceLogicVars( $logic, $search, $replace )
	{
		$listStr = preg_split('/([\'"])/', $logic, -1, PREG_SPLIT_DELIM_CAPTURE );
		$quote = '';
		$logic = '';
		foreach ( $listStr as $strPart )
		{
			if ( $quote == '' && ( $strPart == "'" || $strPart == '"' ) )
			{
				$quote = $strPart;
			}
			elseif ( $quote != '' && $quote == $strPart )
			{
				$quote = '';
			}
			elseif ( $quote == '' )
			{
				$strPart = str_replace( $search, $replace, $strPart );
			}
			$logic .= $strPart;
		}
		return $logic;
	}



	// Sets the specified configuration option for a report to the specified value.
	function setReportConfig( $reportID, $configName, $configValue )
	{
		$projectID = $this->getProjectID();
		$reportConfig = $this->getReportConfig( $reportID );
		$reportConfig[ $configName ] = $configValue;
		$this->setSystemSetting( "p$projectID-report-config-$reportID", json_encode( $reportConfig ) );
	}



	// Sets the definition data for the specified report.
	function setReportData( $reportID, $reportData )
	{
		$projectID = $this->getProjectID();
		$this->setSystemSetting( "p$projectID-report-data-$reportID", json_encode( $reportData ) );
	}



	// Sorting function for the list of reports.
	function sortReports( $reportA, $reportB )
	{
		$categoryA = $reportA['category'] ?? '';
		$categoryB = $reportB['category'] ?? '';
		$labelA = $reportA['label'] ?? '';
		$labelB = $reportB['label'] ?? '';
		return ( strcmp( $categoryA, $categoryB ) * 1000 ) + strcmp( $labelA, $labelB );
	}



	// Replace placeholders in SQL with values.
	/** @psalm-taint-specialize */
	function sqlPlaceholderReplace( $sql, $test = false )
	{
		global $conn;
		if ( $test )
		{
			$sql = str_replace( [ '$$DAG$$', '$$PROJECT$$', '$$ROLE$$' ], '0', $sql );
			$sql = preg_replace( '/\$\$DATATABLE(\:[1-9][0-9]*)?\$\$/', 'redcap_data', $sql );
			$sql = preg_replace( '/\$\$LOGTABLE(\:[1-9][0-9]*)?\$\$/', 'redcap_log_event', $sql );
			$sql = str_replace( [ '$$USER$$', '$$WEBROOT$$' ], "'text'", $sql );
			$sql = preg_replace( '/\$\$QINT\:[a-z0-9_]+\$\$/', '0', $sql );
			$sql = preg_replace( '/\$\$QSTR\:[a-z0-9_]+\$\$/', "'text'", $sql );
		}
		else
		{
			$listLogTables = [];
			$userRole = $this->getUser()->getRights()['role_id'];
			$userRole = $userRole == null ? 'NULL' : intval( $userRole );
			$userDAG = $this->getUser()->getRights()['group_id'];
			$userDAG = $userDAG == null ? 'NULL' : intval( $userDAG );
			$sql = str_replace( '$$DAG$$', $userDAG, $sql );
			$sql = preg_replace_callback( '/\$\$DATATABLE(\:([1-9][0-9]*))?\$\$/',
			                              function( $m )
			                              {
			                                if ( $m[2] == '' )
			                                {
			                                  $pid = intval( $this->getProjectId() );
			                                }
			                                else
			                                {
			                                  $pid = intval( $m[2] );
			                                }
			                                return method_exists( '\REDCap', 'getDataTable' )
			                                       ? \REDCap::getDataTable( $pid ) : 'redcap_data';
			                              },
			                              $sql );
			$sql = preg_replace_callback( '/\$\$LOGTABLE(\:([1-9][0-9]*))?\$\$/',
			                              function( $m ) use ( $listLogTables )
			                              {
			                                if ( $m[2] == '' )
			                                {
			                                  $pid = intval( $this->getProjectId() );
			                                }
			                                else
			                                {
			                                  $pid = intval( $m[2] );
			                                }
			                                if ( isset( $listLogTables[ $pid ] ) )
			                                {
			                                  return $listLogTables[ $pid ];
			                                }
			                                list( $logTable ) =
			                                  $this->query( 'SELECT log_event_table FROM ' .
			                                                'redcap_projects WHERE project_id = ?',
			                                                [ $pid ] )->fetch_row();
			                                if ( $logTable == '' )
			                                {
			                                  $logTable = 'redcap_log_event';
			                                }
			                                $listLogTables[ $pid ] = $logTable;
			                                return $logTable;
			                              },
			                              $sql );
			$sql = str_replace( '$$PROJECT$$', intval( $this->getProjectId() ), $sql );
			$sql = str_replace( '$$ROLE$$', $userRole, $sql );
			$sql = str_replace( '$$USER$$',
			                    "'" . mysqli_real_escape_string( $conn, USERID ) . "'", $sql );
			$sql = str_replace( '$$WEBROOT$$',
			                    "'" . mysqli_real_escape_string( $conn, APP_PATH_WEBROOT ) . "'",
			                    $sql );
			$sql = preg_replace_callback( '/\$\$QINT\:([a-z0-9_]+)\$\$/',
			                              function( $m )
			                              {
			                                if ( ! isset( $_GET[ $m[1] ] ) ||
			                                     ! preg_match( '/^(0|-?[1-9][0-9]*)$/',
			                                                   $_GET[ $m[1] ] ) )
			                                {
			                                  return 'NULL';
			                                }
			                                return $_GET[ $m[1] ];
			                              },
			                              $sql );
			$sql =
			 preg_replace_callback( '/\$\$QSTR\:([a-z0-9_]+)\$\$/',
			                        function( $m )
			                        {
			                          global $conn;
			                          if ( ! isset( $_GET[ $m[1] ] ) )
			                          {
			                            return 'NULL';
			                          }
			                          return "'" . mysqli_real_escape_string( $conn,
			                                                                 $_GET[ $m[1] ] ) . "'";
			                        },
			                        $sql );
		}
		return $sql;
	}



	// Perform submission of all the report config values (upon edit form submission).
	// These are the values which are the same for each report type (e.g. visibility, category).
	function submitReportConfig( $reportID, $includeDownload = true, $includeAdditional = [] )
	{
		if ( ! is_array( $includeAdditional ) )
		{
			if ( is_string( $includeAdditional ) )
			{
				$includeAdditional = [ $includeAdditional ];
			}
			else
			{
				$includeAdditional = [];
			}
		}
		if ( $includeDownload )
		{
			$listConfig = [ 'label', 'category', 'annotation', 'visible',
			                'download', 'roles_access', 'roles_download' ];
		}
		else
		{
			$listConfig = [ 'label', 'category', 'annotation', 'visible', 'roles_access' ];
		}
		if ( in_array( 'saveable', $includeAdditional ) )
		{
			$listConfig[] = 'saveable';
			unset( $includeAdditional[ array_search( 'saveable', $includeAdditional ) ] );
		}
		foreach ( $includeAdditional as $additionalItem )
		{
			$listConfig[] = "as_$additionalItem";
			if ( $additionalItem == 'api' )
			{
				$listConfig[] = 'api_key';
			}
		}
		foreach ( $listConfig as $configSetting )
		{
			$configValue = $_POST["report_$configSetting"];
			if ( in_array( $configSetting, [ 'visible', 'download', 'as_image', 'as_api',
			                                 'saveable', 'as_public' ] ) )
			{
				$configValue = $configValue == 'Y' ? true : false;
			}
			elseif ( trim( $configValue ) === '' )
			{
				$configValue = null;
			}
			elseif ( in_array( $configSetting, [ 'annotation', 'roles_access', 'roles_download' ] ) )
			{
				$configValue = str_replace( "\r\n", "\n", $configValue );
			}
			$this->setReportConfig( $reportID, $configSetting, $configValue );
		}
		$this->setReportConfig( $reportID, 'lastupdated_user', USERID );
		$this->setReportConfig( $reportID, 'lastupdated_time', time() );
	}



	// Outputs HTTP headers for a report download (.csv file).
	function writeCSVDownloadHeaders( $reportID )
	{
		$queryDev = $this->query( 'SELECT value FROM redcap_config WHERE field_name = ?',
		                          [ 'is_development_server' ] );
		$isDev = $queryDev->fetch_row();
		$isDev = $isDev[0] == '1';
		$reportID =
			array_reduce( str_split( $reportID ), function( $c, $i ) { return $c . $i; }, '' );
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' .
		        trim( preg_replace( '/[^A-Za-z0-9-]+/', '_', \REDCap::getProjectTitle() ), '_-' ) .
		        '_' . preg_replace( '/[^A-Za-z0-9-]+/', '_', $reportID ) . '_' .
		        gmdate( 'Ymd-His' ) . ( $isDev ? '_dev' : '' ) . '.csv"' );
	}



	// CSS style for advanced report pages.
	function writeStyle()
	{
		$style = '
			.mod-advrep-errmsg
			{
				color: #c00;
				font-size: x-small;
			}
			.mod-advrep-description a:link
			{
				text-decoration: underline dotted;
			}
			.mod-advrep-description a:hover
			{
				text-decoration: underline solid;
			}
			.mod-advrep-formtable
			{
				width: 97%;
				border: solid 1px #000;
			}
			.mod-advrep-formtable th
			{
				padding: 5px;
				font-size: 130%;
				font-weight: bold;
			}
			.mod-advrep-formtable td
			{
				padding: 5px;
			}
			.mod-advrep-formtable td:first-child
			{
				width: 200px;
				padding-top: 7px;
				padding-right: 8px;
				text-align:right;
				vertical-align: top;
			}
			.mod-advrep-formtable input:not([type=submit]):not([type=radio]):not([type=checkbox])
			{
				width: 95%;
				max-width: 600px;
			}
			.mod-advrep-formtable textarea
			{
				width: 95%;
				max-width: 600px;
				height: 100px;
			}
			.mod-advrep-formtable label
			{
				margin-bottom: 0px;
			}
			.mod-advrep-formtable span.field-desc
			{
				font-size: 90%;
			}
			.mod-advrep-listtable
			{
				border: solid 1px #000;
				border-collapse: collapse;
			}
			.mod-advrep-listtable th
			{
				padding: 8px 5px;
				font-weight: bold;
				border: solid 1px #000;
			}
			.mod-advrep-listtable td
			{
				padding: 3px;
				border: solid 1px #000;
			}
			.mod-advrep-datatable
			{
				border-top: solid 1px #ccc;
				border-left: solid 1px #ccc;
				border-collapse: separate;
				border-spacing: 0px;
			}
			.mod-advrep-datatable th
			{
				background: #ffffe0;
				padding: 13px 25px 13px 5px !important;
				font-weight: bold;
				border-right: solid 1px #ccc;
				border-bottom: solid 1px #ccc;
			}
			.mod-advrep-datatable td
			{
				background: #fff;
				padding: 3px !important;
				border-right: solid 1px #ccc;
				border-bottom: solid 1px #ccc;
			}
			.mod-advrep-datatable th:first-child,
			.mod-advrep-datatable td:first-child
			{
				position: sticky;
				left: 0px;
			}
			.mod-advrep-datatable tr:first-child th
			{
				position: sticky !important;
				top: 0px;
			}
			.mod-advrep-datatable tr:first-child th:first-child
			{
				z-index: 2;
			}
			.mod-advrep-datatable tr:not(.odd):nth-child(2n) td,
			.mod-advrep-datatable tr.even td
			{
				background: #eee;
			}
			.mod-advrep-datatable a:link
			{
				text-decoration: underline solid;
			}
			.mod-advrep-gantt
			{
				display:grid;
				grid-auto-columns: minmax(5px, min-content);
				gap: 1px;
				justify-items:stretch;
				align-items:stretch;
				background: #aaa;
				border: solid 1px #000;
				width: min-content;
			}
			.mod-advrep-gantt *
			{
				border: solid 1px #000;
				margin: -1px;
				background: #fff;
				padding: 3px;
				overflow: hidden;
				cursor: default;
			}
			.mod-advrep-gantt-hdr
			{
				grid-row-start: 1;
				position: sticky;
				top: 0px;
				display: flex;
				align-items: center;
				border-bottom-width: 2px;
			}
			.mod-advrep-gantt-date
			{
				grid-row-start:1;
				position: sticky;
				top: 0px;
				border-bottom-width: 2px;
				text-align: center;
				padding-right: 10px;
			}
			.mod-advrep-gantt-date div
			{
				border: none;
				font-size: xx-small;
			}
			.mod-advrep-gantt-date span
			{
				writing-mode:vertical-lr;
				text-orientation:upright;
				border: none;
				width: 100%;
			}
			.mod-advrep-gantt-key
			{
				display: flex;
				gap: 20px;
				flex-wrap: wrap;
			}
			.mod-advrep-gantt-key > div
			{
				white-space: nowrap;
			}
			.mod-advrep-gantt-key > div > div
			{
				display: inline-block;
				margin-bottom: -3px;
				margin-right: 2px;
				width: 18px;
				height: 18px;
				border: solid 1px #000000;
			}
			.mod-advrep-chart-style0
			{
				background: #e6a1a1;
			}
			.mod-advrep-chart-style1
			{
				background: #e6e6a1;
			}
			.mod-advrep-chart-style2
			{
				background: #a1e6a1;
			}
			.mod-advrep-chart-style3
			{
				background: #a1e6e6;
			}
			.mod-advrep-chart-style4
			{
				background: #a1a1e6;
			}
			.mod-advrep-chart-style5
			{
				background: #e6a1e6;
			}
			.mod-advrep-chart-style6
			{
				background: linear-gradient(0.35turn, #e6c3a1 40%, #ffffff);
			}
			.mod-advrep-chart-style7
			{
				background: linear-gradient(0.35turn, #c3e6a1 40%, #ffffff);
			}
			.mod-advrep-chart-style8
			{
				background: linear-gradient(0.35turn, #a1e6c3 40%, #ffffff);
			}
			.mod-advrep-chart-style9
			{
				background: linear-gradient(0.35turn, #a1c3e6 40%, #ffffff);
			}
			.mod-advrep-chart-style10
			{
				background: linear-gradient(0.35turn, #c3a1e6 40%, #ffffff);
			}
			.mod-advrep-chart-style11
			{
				background: linear-gradient(0.35turn, #e6a1c3 40%, #ffffff);
			}
			.mod-advrep-chart-style12
			{
				background: #f2dada;
			}
			.mod-advrep-chart-style13
			{
				background: #f2f2da;
			}
			.mod-advrep-chart-style14
			{
				background: #daf2da;
			}
			.mod-advrep-chart-style15
			{
				background: #daf2f2;
			}
			.mod-advrep-chart-style16
			{
				background: #dadaf2;
			}
			.mod-advrep-chart-style17
			{
				background: #f2daf2;
			}
			.mod-advrep-chart-style18
			{
				background: linear-gradient(0.35turn, #f2e6da 40%, #ffffff);
			}
			.mod-advrep-chart-style19
			{
				background: linear-gradient(0.35turn, #e6f2da 40%, #ffffff);
			}
			.mod-advrep-chart-style20
			{
				background: linear-gradient(0.35turn, #daf2e6 40%, #ffffff);
			}
			.mod-advrep-chart-style21
			{
				background: linear-gradient(0.35turn, #dae6f2 40%, #ffffff);
			}
			.mod-advrep-chart-style22
			{
				background: linear-gradient(0.35turn, #e6daf2 40%, #ffffff);
			}
			.mod-advrep-chart-style23
			{
				background: linear-gradient(0.35turn, #f2dae6 40%, #ffffff);
			}
			';
		echo '<script type="text/javascript">',
			 '(function (){var el = document.createElement(\'style\');',
			 'el.setAttribute(\'type\',\'text/css\');',
			 'el.innerText = \'', addslashes( preg_replace( "/[\t\r\n ]+/", ' ', $style ) ), '\';',
			 'document.getElementsByTagName(\'head\')[0].appendChild(el)})()</script>';
	}


}
