<?php

namespace Nottingham\AdvancedReports;

class AdvancedReports extends \ExternalModules\AbstractExternalModule
{

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

	function isReportDownloadable( $reportID )
	{
		// Load the report config.
		$reportConfig = $this->getReportConfig( $reportID );
		// Don't allow downloads if they are deactivated.
		if ( ! $reportConfig['download'] )
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

	function getReportData( $reportID )
	{
		$data = $this->getProjectSetting( "report-data-$reportID" );
		if ( $data !== null )
		{
			$data = json_decode( $data, true );
		}
		return $data;
	}

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

	function setReportConfig( $reportID, $configName, $configValue )
	{
		$reportConfig = $this->getReportConfig( $reportID );
		$reportConfig[ $configName ] = $configValue;
		$this->setProjectSetting( "report-config-$reportID", json_encode( $reportConfig ) );
	}

	function setReportData( $reportID, $reportData )
	{
		$this->setProjectSetting( "report-data-$reportID", json_encode( $reportData ) );
	}

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
			.mod-advrep-datatable
			{
				border: solid 1px #000;
				border-collapse: separate;
				border-spacing: 0px;
			}
			.mod-advrep-datatable th
			{
				background: #fff;
				padding: 13px 5px;
				font-weight: bold;
				border: solid 1px #000;
				text-align: center;
			}
			.mod-advrep-datatable td
			{
				background: #fff;
				padding: 3px;
				border: solid 1px #000;
				text-align: center;
			}
			.mod-advrep-datatable th:first-child,
			.mod-advrep-datatable td:first-child
			{
				position: sticky;
				left: 0px;
				border-right-width: 3px;
			}
			.mod-advrep-datatable th:nth-child(2),
			.mod-advrep-datatable td:nth-child(2)
			{
				border-left-width: 0px;
			}
			.mod-advrep-datatable tr:first-child th
			{
				position: sticky;
				top: 0px;
				border-bottom-width: 3px;
			}
			.mod-advrep-datatable tr:nth-child(2) td
			{
				border-top-width: 0px;
			}
			.mod-advrep-datatable tr:first-child :first-child
			{
				z-index: 2;
			}
			.mod-advrep-datatable tr:nth-child(2n+1) td
			{
				background: #f8f8f8;
			}
			';
		echo '<script type="text/javascript">',
			 '(function (){var el = document.createElement(\'style\');',
			 'el.setAttribute(\'type\',\'text/css\');',
			 'el.innerText = \'', addslashes( preg_replace( "/[\t\r\n ]+/", ' ', $style ) ), '\';',
			 'document.getElementsByTagName(\'head\')[0].appendChild(el)})()</script>';
	}

}