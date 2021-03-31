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
	function redcap_module_configure_button_display( $project_id )
	{
		return $this->framework->getUser()->isSuperUser() ? true : null;
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
		$isSuperUser = $this->framework->getUser()->isSuperUser();
		$userRights = $this->framework->getUser()->getRights();
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
		// Allow editing if enabled for the user's role.
		if ( ( $this->getProjectSetting( 'edit-if-design' ) && $userRights[ 'design' ] == '1' ) ||
			 ( $this->getProjectSetting( 'edit-if-reports' ) && $userRights[ 'reports' ] == '1' ) )
		{
			return true;
		}
		// Otherwise don't allow editing.
		return false;
	}



	// Add a new report, with the specified ID (unique name), report type, and label.
	function addReport( $reportID, $reportType, $reportLabel )
	{
		// Set the report configuration.
		$config = [ 'type' => $reportType, 'label' => $reportLabel, 'visible' => false ];
		$this->setProjectSetting( "report-config-$reportID", json_encode( $config ) );
		// Add the report to the list of reports.
		$listIDs = $this->getProjectSetting( 'report-list' );
		if ( $listIDs === null )
		{
			$listIDs = [];
		}
		else
		{
			$listIDs = json_decode( $listIDs, true );
		}
		$listIDs[] = $reportID;
		$this->setProjectSetting( 'report-list', json_encode( $listIDs ) );
	}



	// Delete the specified report.
	function deleteReport( $reportID )
	{
		// Remove the report configuration and data.
		$this->removeProjectSetting( "report-config-$reportID" );
		$this->removeProjectSetting( "report-data-$reportID" );
		// Remove the report from the list of reports.
		$listIDs = $this->getProjectSetting( 'report-list' );
		if ( $listIDs === null )
		{
			return;
		}
		$listIDs = json_decode( $listIDs, true );
		if ( ( $k = array_search( $reportID, $listIDs ) ) !== false )
		{
			unset( $listIDs[$k] );
		}
		$this->setProjectSetting( 'report-list', json_encode( $listIDs ) );
	}



	// Returns a list of events for the project.
	function getEventList()
	{
		$listTypes = explode( ',', $fieldTypes );
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



	// Get the configuration for the specified report.
	// Optionally specify the configuration option name, otherwise all options are returned.
	function getReportConfig( $reportID, $configName = null )
	{
		$config = $this->getProjectSetting( "report-config-$reportID" );
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
		$data = $this->getProjectSetting( "report-data-$reportID" );
		if ( $data !== null )
		{
			$data = json_decode( $data, true );
		}
		return $data;
	}



	// Gets the list of reports, with the configuration data for each report.
	function getReportList()
	{
		$listIDs = $this->getProjectSetting( 'report-list' );
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
		         'sql' => 'SQL' ];
	}



	// Get the role name of the current user.
	function getUserRole()
	{
		$userRights = $this->framework->getUser()->getRights();
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



	// Output the form controls to set the report configuration on the edit report page.
	// These are the settings which are the same for all reports.
	function outputReportConfigOptions( $reportConfig, $includeDownload = true )
	{
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
	}



	// Output the report navigation links.
	function outputViewReportHeader( $reportLabel, $reportType )
	{
		$canDownload = $this->isReportDownloadable( $_GET['report_id'] );
		$this->writeStyle();

?>
<div class="projhdr">
 <?php echo htmlspecialchars( $reportLabel ), "\n"; ?>
</div>
<p style="font-size:11px" class="hide_in_print">
 <a href="<?php echo $this->getUrl( 'reports.php' )
?>" class="fas fa-arrow-circle-left fs11"> Back to Advanced Reports</a>
<?php

		// If report can be downloaded, show the download link.
		if ( $canDownload )
		{

?>
 &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
 <a href="<?php
			echo $this->getUrl( $reportType . '_view.php?report_id=' . $_GET['report_id'] .
			                    '&download=1' );
?>" class="fas fa-file-download fs11"> Download report</a>
<?php

		}

		// If the user can edit the report, show an edit link.
		if ( $this->isReportEditable( $reportType ) )
		{

?>
 &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
 <a href="<?php
			echo $this->getUrl( $reportType . '_edit.php?report_id=' . $_GET['report_id'] );
?>" class="fas fa-pencil-alt fs11"> Edit report</a>
<?php

		}

	}



	// Returns the supplied string with any HTML entity encoded, with the exception of hyperlinks.
	// If the $forDownload parameter is true, hyperlink tags will be stripped instead.
	function parseHTML( $str, $forDownload = false )
	{
		if ( $forDownload )
		{
			return preg_replace( '/<a href="[^"]*"( target="_blank")?>(.*?)<\/a>/', '$2', $str );
		}
		return preg_replace( '/&lt;a href="([^"]*)"( target="_blank")?&gt;(.*?)&lt;\/a&gt;/',
		                     '<a href="$1"$2>$3</a>',
		                     htmlspecialchars( $str, ENT_NOQUOTES ) );
	}



	// Sets the specified configuration option for a report to the specified value.
	function setReportConfig( $reportID, $configName, $configValue )
	{
		$reportConfig = $this->getReportConfig( $reportID );
		$reportConfig[ $configName ] = $configValue;
		$this->setProjectSetting( "report-config-$reportID", json_encode( $reportConfig ) );
	}



	// Sets the definition data for the specified report.
	function setReportData( $reportID, $reportData )
	{
		$this->setProjectSetting( "report-data-$reportID", json_encode( $reportData ) );
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



	// Perform submission of all the report config values (upon edit form submission).
	// These are the values which are the same for each report type (e.g. visibility, category).
	function submitReportConfig( $reportID, $includeDownload = true )
	{
		if ( $includeDownload )
		{
			$listConfig =
				[ 'label', 'category', 'visible', 'download', 'roles_access', 'roles_download' ];
		}
		else
		{
			$listConfig = [ 'label', 'category', 'visible', 'roles_access' ];
		}
		foreach ( $listConfig as $configSetting )
		{
			$configValue = $_POST["report_$configSetting"];
			if ( in_array( $configSetting, [ 'visible', 'download' ] ) )
			{
				$configValue = $configValue == 'Y' ? true : false;
			}
			elseif ( trim( $configValue ) === '' )
			{
				$configValue = null;
			}
			$this->setReportConfig( $reportID, $configSetting, $configValue );
		}
	}



	// Outputs HTTP headers for a report download (.csv file).
	function writeCSVDownloadHeaders( $reportID )
	{
		$queryDev = $this->query( "SELECT value FROM redcap.redcap_config" .
		                          " WHERE field_name = 'is_development_server'" );
		$isDev = mysqli_fetch_row( $queryDev );
		$isDev = $isDev[0] == '1';
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' .
		        trim( preg_replace( '/[^A-Za-z0-9-]+/', '_', \REDCap::getProjectTitle() ), '_-' ) .
		        "_{$reportID}_" . gmdate( 'Ymd-His' ) . ( $isDev ? '_dev' : '' ) . '.csv"' );
	}



	// CSS style for advanced report pages.
	function writeStyle()
	{
		$style = '
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
				padding: 13px 5px;
				font-weight: bold;
				border-right: solid 1px #ccc;
				border-bottom: solid 1px #ccc;
			}
			.mod-advrep-datatable td
			{
				background: #fff;
				padding: 3px;
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
				position: sticky;
				top: 0px;
			}
			.mod-advrep-datatable tr:first-child :first-child
			{
				z-index: 2;
			}
			.mod-advrep-datatable tr:nth-child(2n+1) td
			{
				background: #eee;
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
