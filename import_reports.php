<?php
/*
 *	Imports the advanced reports configuration from a JSON document.
 */

namespace Nottingham\AdvancedReports;


// Check user can edit reports.
if ( ! $module->isReportEditable() )
{
	exit;
}


$projectID = $module->getProjectID();
$singleReport = false;


$mode = 'upload';
if ( ! empty( $_FILES ) ) // file is uploaded
{
	$mode = 'verify';
	// Check that a file has been uploaded and it is valid.
	if ( ! is_uploaded_file( $_FILES['import_file']['tmp_name'] ) )
	{
		$mode = 'error';
		$error = 'No file uploaded.';
	}
	if ( $mode == 'verify' ) // no error
	{
		$fileData = file_get_contents( $_FILES['import_file']['tmp_name'] );
		$data = json_decode( $fileData, true );
		if ( $data == null || ! is_array( $data ) || ! isset( $data['report-list'] ) ||
			 ( ! is_array( $data['report-list'] ) && ! is_string( $data['report-list'] ) ) )
		{
			$mode = 'error';
			$error = 'The uploaded file is not a valid Advanced Reports export.';
		}
	}
	if ( $mode == 'verify' ) // no error
	{
		if ( is_string( $data['report-list'] ) )
		{
			$data['report-list'] = [ $data['report-list'] ];
			$singleReport = true;
		}
		foreach ( $data['report-list'] as $reportID )
		{
			if ( preg_match( '/[^a-z0-9_-]/', $reportID ) )
			{
				$mode = 'error';
				$error = "The uploaded file contains an invalid report ID: $reportID";
				break;
			}
			if ( ! isset( $data["report-config-$reportID"] ) ||
			     ! array_key_exists( "report-data-$reportID", $data ) )
			{
				$mode = 'error';
				$error = "Data missing for report $reportID";
				break;
			}
		}
	}
	// Parse the uploaded file for differences between the existing reports and those contained
	// within the file. The user will be asked to confirm the changes.
	if ( $mode == 'verify' ) // no error
	{
		$_SESSION['mod-advrep-import-hash'] = hash( 'sha256', $fileData );
		$listCurrent = json_decode( $module->getSystemSetting( "p$projectID-report-list" ),
		                            true ) ?? [];
		$listImported = $data['report-list'] ?? [];
		$listSkipped = $data['report-skip'] ?? [];
		$listNew = array_diff( $listImported, $listCurrent );
		$listDeleted = $singleReport ? [] : array_diff( $listCurrent, $listImported, $listSkipped );
		$listIdentical = [];
		$listChanged = [];
		foreach ( array_intersect( $listCurrent, $listImported ) as $reportID )
		{
			$currentConfig = $module->getReportConfig( $reportID );
			unset( $currentConfig['lastupdated_user'], $currentConfig['lastupdated_time'] );
			if ( isset( $currentConfig['api_key'] ) )
			{
				$data["report-config-$reportID"]['api_key'] = $currentConfig['api_key'];
			}
			$identicalConfig = ( $currentConfig == $data["report-config-$reportID"] );
			$identicalData =
				( $module->getReportData( $reportID ) == $data["report-data-$reportID"] );
			if ( ! $module->isReportEditable( $currentConfig['type'] ) ||
			     ! $module->isReportEditable( $data["report-config-$reportID"]['type'] ) )
			{
				$listSkipped[] = $reportID;
			}
			elseif ( $identicalConfig && $identicalData )
			{
				$listIdentical[] = $reportID;
			}
			else
			{
				$listChanged[] =
					[ 'id' => $reportID, 'config' => !$identicalConfig, 'data' => !$identicalData ];
			}
		}
	}
}
elseif ( ! empty( $_POST ) ) // normal POST request (confirming import)
{
	$mode = 'complete';
	// The contents of the file are passed across from the verify stage. If this is valid, the
	// selected changes are applied.
	$fileData = $_POST['import_data'];
	$data = json_decode( $fileData, true );
	if ( $data == null || ! is_array( $data ) || ! isset( $data['report-list'] ) ||
		 ( ! is_array( $data['report-list'] ) && ! is_string( $data['report-list'] ) ) ||
		 ! isset( $_SESSION['mod-advrep-import-hash'] ) ||
		 $_SESSION['mod-advrep-import-hash'] != hash( 'sha256', $fileData ) )
	{
		$mode = 'error';
		$error = 'The uploaded file data is not valid.';
	}
	unset( $_SESSION['mod-advrep-import-hash'] );
	if ( $mode == 'complete' ) // no error
	{
		foreach ( $_POST as $key => $val )
		{
			if ( substr( $key, 0, 11 ) == 'report-add-' )
			{
				// Add new report into project from file.
				$reportID = substr( $key, 11 );
				$reportType = $data["report-config-$reportID"]['type'];
				if ( $module->isReportEditable( $reportType ) )
				{
					$reportLabel = $data["report-config-$reportID"]['label'];
					$module->addReport( $reportID, $reportType, $reportLabel );
					$module->setSystemSetting( "p$projectID-report-config-$reportID",
					                           json_encode( $data["report-config-$reportID"] ) );
					$module->setReportConfig( $reportID, 'lastupdated_user', USERID );
					$module->setReportConfig( $reportID, 'lastupdated_time', time() );
					$module->setReportData( $reportID, $data["report-data-$reportID"] );
				}
			}
			elseif ( substr( $key, 0, 14 ) == 'report-config-' )
			{
				// Update report configuration (label, category, access permissions etc.)
				$reportID = substr( $key, 14 );
				$currentConfig = $module->getReportConfig( $reportID );
				$reportType = $currentConfig['type'];
				$reportType = ( $reportType == $data["report-config-$reportID"]['type'] )
				              ? $reportType : null;
				if ( $reportType !== null && $module->isReportEditable( $reportType ) )
				{
					if ( isset( $currentConfig['api_key'] ) )
					{
						$data["report-config-$reportID"]['api_key'] = $currentConfig['api_key'];
					}
					$module->setSystemSetting( "p$projectID-report-config-$reportID",
					                           json_encode( $data["report-config-$reportID"] ) );
					$module->setReportConfig( $reportID, 'lastupdated_user', USERID );
					$module->setReportConfig( $reportID, 'lastupdated_time', time() );
				}
			}
			elseif ( substr( $key, 0, 12 ) == 'report-data-' )
			{
				// Update report definition.
				$reportID = substr( $key, 12 );
				$reportType = $module->getReportConfig( $reportID, 'type' );
				$reportType = ( $reportType == $data["report-config-$reportID"]['type'] )
				              ? $reportType : null;
				if ( $reportType !== null && $module->isReportEditable( $reportType ) )
				{
					$module->setReportData( $reportID, $data["report-data-$reportID"] );
					$module->setReportConfig( $reportID, 'lastupdated_user', USERID );
					$module->setReportConfig( $reportID, 'lastupdated_time', time() );
				}
			}
			elseif ( substr( $key, 0, 14 ) == 'report-delete-' )
			{
				// Remove report from project.
				$reportID = substr( $key, 14 );
				$reportType = $module->getReportConfig( $reportID, 'type' );
				if ( $module->isReportEditable( $reportType ) )
				{
					$module->deleteReport( $reportID );
				}
			}
		}
	}
}
else
{
	unset( $_SESSION['mod-advrep-import-hash'] );
}


// Display the project header
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$module->writeStyle();


?>
<div class="projhdr">
 Import Advanced Reports
</div>
<p style="font-size:11px">
 <a href="<?php echo $module->getUrl( 'reports.php' )
?>"><i class="fas fa-arrow-circle-left fs11"></i> Back to advanced reports</a>
</p>
<?php


// Display the file upload form.
if ( $mode == 'upload' )
{


?>
<form method="post" enctype="multipart/form-data">
 <table class="mod-advrep-formtable">
  <tr>
   <td>Import file</td>
   <td>
    <input type="file" name="import_file">
   </td>
  </tr>
  <tr>
   <td></td>
   <td>
    <input type="submit" value="Import">
   </td>
  </tr>
 </table>
</form>
<?php


}
// Display the options to confirm the changes to the report definitions introduced by the file.
elseif ( $mode == 'verify' )
{


?>
<form method="post">
 <table class="mod-advrep-formtable">
<?php
	if ( count( $listSkipped ) > 0 )
	{
?>
  <tr>
   <th colspan="2">Skipped Reports</th>
  </tr>
  <tr>
   <td colspan="2" style="text-align:left">
    <ul>
<?php
		foreach ( $listSkipped as $reportID )
		{
			$reportLabel = $module->getReportConfig( $reportID, 'label' );
			echo '     <li>', $module->escapeHTML( $reportID ), ' &nbsp;',
			     ( $reportLabel == '' ? '' :
			       '<i>(' . $module->escapeHTML( $reportLabel ) . ')</i>' ), "</li>\n";
		}
?>
    </ul>
   </td>
  </tr>
<?php
	}

	if ( count( $listIdentical ) > 0 )
	{
?>
  <tr>
   <th colspan="2">Identical Reports</th>
  </tr>
  <tr>
   <td colspan="2" style="text-align:left">
    <ul>
<?php
		foreach ( $listIdentical as $reportID )
		{
?>
     <li><?php echo $module->escapeHTML( $reportID ); ?> &nbsp;<i>(<?php
			echo $module->escapeHTML( $data["report-config-$reportID"]['label'] ); ?>)</i></li>
<?php
		}
?>
    </ul>
   </td>
  </tr>
<?php
	}

	if ( count( $listNew ) > 0 )
	{
?>
  <tr>
   <th colspan="2">New Reports</th>
  </tr>
<?php
		foreach ( $listNew as $reportID )
		{
?>
  <tr>
   <td><?php echo $module->escapeHTML( $reportID ); ?></td>
   <td>
    <input type="checkbox" name="report-add-<?php
			echo $module->escapeHTML( $reportID ); ?>" value="1" checked>
    Add this report
    <ul>
<?php
			foreach ( [ 'type' => 'Report type', 'label' => 'Report label',
			            'category' => 'Report category', 'visible' => 'Report is visible',
			            'roles_access' => 'Report accessible by roles',
			            'download' => 'Report can be downloaded',
			            'roles_download' => 'Report downloadable by roles',
			            'as_image' => 'Report can be retrieved as an image',
			            'as_api' => 'API access enabled' ]
			          as $configName => $configLabel )
			{
				$configValue = $data["report-config-$reportID"][$configName];
				if ( $configValue === null )
				{
					continue;
				}
				if ( is_bool( $configValue ) )
				{
					$configValue = $configValue ? 'Yes' : 'No';
				}
				elseif ( is_string( $configValue ) )
				{
					$configValue = htmlspecialchars( $configValue );
					if ( strpos( $configValue, "\n" ) !== false )
					{
						$configValue = str_replace( [ "\r\n", "\n" ], '</li><li>', $configValue );
						$configValue = "<ul><li>$configValue</li></ul>";
					}
				}
?>
     <li><b><?php echo $configLabel; ?>:</b> <?php echo $configValue; ?></li>
<?php
			}
?>
    </ul>
   </td>
  </tr>
<?php
		}
	}

	if ( count( $listChanged ) > 0 )
	{
?>
  <tr>
   <th colspan="2">Changed Reports</th>
  </tr>
<?php
		foreach ( $listChanged as $reportChange )
		{
			$reportID = $reportChange['id'];
			$changedConfig = $reportChange['config'];
			$changedData = $reportChange['data'];
?>
  <tr>
   <td><?php echo $module->escapeHTML( $reportID ); ?></td>
   <td>
<?php
			if ( $changedConfig )
			{
?>
    <input type="checkbox" name="report-config-<?php
			echo $module->escapeHTML( $reportID ); ?>" value="1" checked>
    Update report configuration (changes highlighted below)
    <br>
<?php
			}
			if ( $changedData )
			{
?>
    <input type="checkbox" name="report-data-<?php
			echo $module->escapeHTML( $reportID ); ?>" value="1" checked>
    Update report definition
<?php
			}
?>
    <ul>
<?php
			foreach ( [ 'type' => 'Report type', 'label' => 'Report label',
			            'category' => 'Report category', 'visible' => 'Report is visible',
			            'roles_access' => 'Report accessible by roles',
			            'download' => 'Report can be downloaded',
			            'roles_download' => 'Report downloadable by roles',
			            'as_image' => 'Report can be retrieved as an image',
			            'as_api' => 'API access enabled' ]
			          as $configName => $configLabel )
			{
				$configValue = [];
				$configValue['old'] = $module->getReportConfig( $reportID, $configName );
				$configValue['new'] = $data["report-config-$reportID"][$configName];
				if ( $configValue['old'] === null && $configValue['new'] === null )
				{
					continue;
				}
				foreach ( [ 'old', 'new' ] as $configVer )
				{
					if ( is_bool( $configValue[$configVer] ) )
					{
						$configValue[$configVer] = $configValue[$configVer] ? 'Yes' : 'No';
					}
					elseif ( is_string( $configValue[$configVer] ) )
					{
						$configValue[$configVer] = $module->escapeHTML( $configValue[$configVer] );
						if ( strpos( $configValue[$configVer], "\n" ) !== false )
						{
							$configValue[$configVer] = str_replace( [ "\r\n", "\n" ], '</li><li>',
							                                        $configValue[$configVer] );
							$configValue[$configVer] = '<ul><li>' . $configValue[$configVer] .
							                           '</li></ul>';
						}
					}
				}

				if ( $configValue['old'] == $configValue['new'] )
				{
?>
     <li><b><?php echo $configLabel; ?>:</b> <?php echo $configValue['new']; ?></li>
<?php
				}
				else
				{
?>
     <li style="color:#c00;text-decoration:line-through"><b><?php echo $configLabel; ?>:</b> <?php
					echo $configValue['old']; ?></li>
     <li style="color:#060"><b><?php echo $configLabel; ?>:</b> <?php
					echo $configValue['new']; ?></li>
<?php
				}
			}
?>
    </ul>
   </td>
  </tr>
<?php
		}
	}

	if ( count( $listDeleted ) > 0 )
	{
?>
  <tr>
   <th colspan="2">Reports Not In Import File</th>
  </tr>
<?php
		foreach ( $listDeleted as $reportID )
		{
?>
  <tr>
   <td><?php echo $module->escapeHTML( $reportID ); ?></td>
   <td>
    <input type="checkbox" name="report-delete-<?php
			echo $module->escapeHTML( $reportID ); ?>" value="1">
    Delete this report
    <ul>
<?php
			foreach ( [ 'type' => 'Report type', 'label' => 'Report label',
			            'category' => 'Report category' ]
			          as $configName => $configLabel )
			{
				$configValue = $module->getReportConfig( $reportID, $configName );
				if ( $configValue === null )
				{
					continue;
				}
				$configValue = $module->escapeHTML( $configValue );
?>
     <li><b><?php echo $configLabel; ?>:</b> <?php echo $configValue; ?></li>
<?php
			}
?>
    </ul>
   </td>
  </tr>
<?php
		}
	}
?>
  <tr>
   <td></td>
   <td>
    <input type="submit" value="Update Selected Reports">
    <input type="hidden" name="import_data" value="<?php echo $module->escapeHTML( $fileData ); ?>">
   </td>
  </tr>
 </table>
</form>
<?php


}
// Display error message.
elseif ( $mode == 'error' )
{


?>
<p style="font-size:14px;color:#f00"><?php echo $module->escapeHTML( $error ); ?></p>
<?php


}
// Display success message.
elseif ( $mode == 'complete' )
{


?>
<p style="font-size:14px">Import complete</p>
<?php


}


// Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';

