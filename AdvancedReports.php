<?php

namespace Nottingham\AdvancedReports;

class AdvancedReports extends \ExternalModules\AbstractExternalModule
{

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
			     $UITweaker->areCustomReportsExpected() )
			{
				$listReports = $this->getReportList();
				$reportTypes = $this->getReportTypes();
				foreach ( $listReports as $reportID => $reportConfig )
				{
					$reportData = $this->getReportData( $reportID );
					$description = '';
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
					$permissions = 'View Access: ' .
					               str_replace( "\n", ', ', $reportConfig['roles_access'] );
					if ( array_key_exists('as_image', $reportConfig) && $reportConfig['as_image'] )
					{
						$permissions .= "\nCan retrieve as image";
					}
					if ( $reportConfig['download'] )
					{
						$permissions .= "\nDownload: " .
						                str_replace( "\n", ', ', $reportConfig['roles_download'] );
					}
					if ( ! $reportConfig['visible'] )
					{
						$permissions = "(hidden)\n$permissions";
					}
					// For SQL reports...
					if ( $reportConfig['type'] == 'sql' )
					{
						// Populate description and definition with the report description and SQL.
						$description = $reportData['sql_desc'];
						$definition = $reportData['sql_query'];
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
								$options .= ', columns: ' . $reportData['sql_cols'];
							}
						}
					}
					// For Instrument Queries...
					elseif ( $reportConfig['type'] == 'instrument' )
					{
						$description = $reportData['desc'];
						$definition = 'Instruments:';
						foreach ( $reportData['forms'] as $queryForm )
						{
							$definition .= "\n- ";
							if ( $definition != "Instruments:\n- " )
							{
								$definition .= 'JOIN ';
							}
							$definition .= $queryForm['form'];
							if ( $queryForm['alias'] != '' )
							{
								$definition .= ' AS `' . $queryForm['alias'] . '`';
							}
							if ( $queryForm['on'] != '' )
							{
								$definition .= ' ON ' . $queryForm['on'];
							}
						}
						if ( $reportData['where'] != '' )
						{
							$definition .= "\nCondition: " . $reportData['where'];
						}
						if ( $reportData['orderby'] != '' )
						{
							$definition .= "\nSorting: " . $reportData['orderby'];
						}
						if ( ! empty( $reportData['select'] ) )
						{
							$definition .= "\nFields to display:";
							foreach ( $reportData['select'] as $queryField )
							{
								$definition .= "\n- " . $queryField['field'];
								if ( $queryField['alias'] != '' )
								{
									$definition .= ' AS `' . $queryField['alias'] . '`';
								}
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
						$definition = 'Labels:';
						foreach ( $reportData['labels'] as $infoLabel )
						{
							$definition .= "\n- " . $infoLabel['name'] . ': [';
							if ( $infoLabel['event'] != '' )
							{
								$definition .= $infoLabel['event'] . '][';
							}
							$definition .= $infoLabel['field'] . ']';
						}
						$definition .= "\nCategories:";
						foreach ( $reportData['chart_categories'] as $infoCategory )
						{
							$definition .= "\n- " . $infoCategory['name'] . ': [';
							if ( $infoCategory['start_event'] != '' )
							{
								$definition .= $infoCategory['start_event'] . '][';
							}
							$definition .= $infoCategory['start_field'] . '] - [';
							if ( $infoCategory['end_event'] != '' )
							{
								$definition .= $infoCategory['end_event'] . '][';
							}
							$definition .= $infoCategory['end_field'] . ']';
						}
					}
					// Add the report to the simplified view.
					$UITweaker->addCustomReport( [ 'title' => $reportConfig['label'],
					                               'type' => $reportTypes[ $reportConfig['type'] ],
					                               'description' => $description,
					                               'permissions' => $permissions,
					                               'definition' => $definition,
					                               'options' => $options ] );
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
			$listIDs = json_decode( $listIDs, true );
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
		if ( ( $k = array_search( $reportID, $listIDs ) ) !== false )
		{
			unset( $listIDs[$k] );
		}
		$this->setSystemSetting( "p$projectID-report-list", json_encode( $listIDs ) );
	}



	// Echo plain text to output (without Psalm taints).
	// Use only for e.g. JSON or CSV output.
	function echoText( $text )
	{
		echo array_reduce( str_split( $text ), function( $c, $i ) { return $c . $i; }, '' );
	}



	// Escapes text for inclusion in HTML.
	function escapeHTML( $text )
	{
		return htmlspecialchars( $text, ENT_QUOTES );
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
				$listFields[ $infoField['field_name'] ] =
					$infoField['field_name'] . ' - ' . $infoField['field_label'];
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
			if ( $configName !== null )
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
		$projectID = $this->getProjectID();
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
		return [ 'gantt' => 'Gantt',
		         'instrument' => 'Instrument Query',
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
           value="<?php echo htmlspecialchars( $reportConfig['label'] ); ?>">
   </td>
  </tr>
  <tr>
   <td>Report Category</td>
   <td>
    <input type="text" name="report_category"
           value="<?php echo htmlspecialchars( $reportConfig['category'] ); ?>">
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
    <textarea name="report_roles_access"><?php echo $reportConfig['roles_access']; ?></textarea>
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
    <textarea name="report_roles_download"><?php echo $reportConfig['roles_download']; ?></textarea>
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

?>
 &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
 <a href="<?php
			echo $this->getUrl( $reportType . '_view.php?report_id=' . $_GET['report_id'] .
			                    '&download=1' );
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
    })


    var vHeader = $('.mod-advrep-datatable thead th')
    $('.mod-advrep-datatable tbody tr').each(function(indexTr,elemTr)
    {
      $(elemTr).find('td').each(function(indexTd,elemTd)
      {
        var vText = $(elemTd).text()
        var vItems = vHeader[indexTd].getAttribute('data-items')
        vItems = JSON.parse( vItems === null ? '[]' : vItems )
        if ( vItems !== false && vText != '' && vItems.indexOf( vText ) == -1 )
        {
          vItems.push( vText )
          if ( vItems.length > 20 )
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
        else // string
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
		return nl2br( $this->parseHTML( $str ) );
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
		return preg_replace_callback( '/&lt;((?<t1>a) href=&quot;((?(?=&quot;)|.)*)&quot;( ' .
		                              'target=&quot;_blank&quot;)?|(?<t2>b|i))&gt;(.*?)&lt;\/' .
		                              '((?P=t1)|(?P=t2))&gt;/',
		                              $fnParse, htmlspecialchars( $str, ENT_QUOTES ) );
	}



	// Takes logic and parses it, returning an array consisting of a function and information about
	// the parameters to be passed to the function to evaluate the logic.
	function parseLogic( $str, $createFunction = true )
	{
		// Convert [value] and [label] parameters after a field to numbers so they will be accepted
		// by the REDCap logic lexer/parser. Also pipe in the values for any smart variables.
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
				$strPart = preg_replace( '/((\[[A-Za-z0-9_]+\]){2}):value/', '$1[1]', $strPart );
				$strPart = preg_replace( '/((\[[A-Za-z0-9_]+\]){2}):label/', '$1[2]', $strPart );
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
			$logicParams[] = [ $param[0], $param[1], $dataType ];
		}
		return [ $logic[0], $logicParams ];
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
	/** @psalm-taint-escape sql */
	function sqlPlaceholderReplace( $sql, $test = false )
	{
		global $conn;
		if ( $test )
		{
			$sql = str_replace( [ '$$DAG$$', '$$PROJECT$$', '$$ROLE$$' ], '0', $sql );
			$sql = str_replace( '$$LOGTABLE$$', 'redcap_log_event', $sql );
			$sql = str_replace( [ '$$USER$$', '$$WEBROOT$$' ], "'text'", $sql );
			$sql = preg_replace( '/\$\$QINT\:[a-z0-9_]+\$\$/', '0', $sql );
			$sql = preg_replace( '/\$\$QSTR\:[a-z0-9_]+\$\$/', "'text'", $sql );
		}
		else
		{
			list( $logTable ) = $this->query( 'SELECT log_event_table FROM redcap_projects ' .
			                                  'WHERE project_id = ?',
			                                  [ $this->getProjectId() ] )->fetch_row();
			$userRole = $this->getUser()->getRights()['role_id'];
			$userRole = $userRole == null ? 'NULL' : intval( $userRole );
			$userDAG = $this->getUser()->getRights()['group_id'];
			$userDAG = $userDAG == null ? 'NULL' : intval( $userDAG );
			$sql = str_replace( '$$DAG$$', $userDAG, $sql );
			$sql = str_replace( '$$LOGTABLE$$', $logTable, $sql );
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
			$listConfig =
				[ 'label', 'category', 'visible', 'download', 'roles_access', 'roles_download' ];
		}
		else
		{
			$listConfig = [ 'label', 'category', 'visible', 'roles_access' ];
		}
		foreach ( $includeAdditional as $additionalItem )
		{
			$listConfig[] = "as_$additionalItem";
		}
		foreach ( $listConfig as $configSetting )
		{
			$configValue = $_POST["report_$configSetting"];
			if ( in_array( $configSetting, [ 'visible', 'download', 'as_image' ] ) )
			{
				$configValue = $configValue == 'Y' ? true : false;
			}
			elseif ( trim( $configValue ) === '' )
			{
				$configValue = null;
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
