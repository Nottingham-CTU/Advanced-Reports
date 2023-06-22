<?php
/*
 *	Imports the advanced reports configuration from a JSON document.
 */

namespace Nottingham\AdvancedReports;


if ( ! $module->framework->getUser()->isSuperUser() )
{
	exit;
}


$projectID = $module->getProjectID();


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
			 ! is_array( $data['report-list'] ) )
		{
			$mode = 'error';
			$error = 'The uploaded file is not a valid Advanced Reports export.';
		}
	}
	if ( $mode == 'verify' ) // no error
	{
		foreach ( $data['report-list'] as $reportID )
		{
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
		$listCurrent = json_decode( $module->getSystemSetting( "p$projectID-report-list" ),
		                            true ) ?? [];
		$listImported = $data['report-list'] ?? [];
		$listNew = array_diff( $listImported, $listCurrent );
		$listDeleted = array_diff( $listCurrent, $listImported );
		$listIdentical = [];
		$listChanged = [];
		foreach ( array_intersect( $listCurrent, $listImported ) as $reportID )
		{
			$currentConfig = $module->getReportConfig( $reportID );
			unset( $currentConfig['lastupdated_user'], $currentConfig['lastupdated_time'] );
			$identicalConfig = ( $currentConfig == $data["report-config-$reportID"] );
			$identicalData =
				( $module->getReportData( $reportID ) == $data["report-data-$reportID"] );
			if ( $identicalConfig && $identicalData )
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
		 ! is_array( $data['report-list'] ) )
	{
		$mode = 'error';
		$error = 'The uploaded file data is not valid.';
	}
	if ( $mode == 'complete' ) // no error
	{
		foreach ( $_POST as $key => $val )
		{
			if ( substr( $key, 0, 11 ) == 'report-add-' )
			{
				// Add new report into project from file.
				$reportID = substr( $key, 11 );
				$reportType = $data["report-config-$reportID"]['type'];
				$reportLabel = $data["report-config-$reportID"]['label'];
				$module->addReport( $reportID, $reportType, $reportLabel );
				$module->setSystemSetting( "p$projectID-report-config-$reportID",
				                           json_encode( $data["report-config-$reportID"] ) );
				$module->setReportData( $reportID, $data["report-data-$reportID"] );
			}
			elseif ( substr( $key, 0, 14 ) == 'report-config-' )
			{
				// Update report configuration (label, category, access permissions etc.)
				$reportID = substr( $key, 14 );
				$module->setSystemSetting( "p$projectID-report-config-$reportID",
				                           json_encode( $data["report-config-$reportID"] ) );
				$module->setReportConfig( $reportID, 'lastupdated_user', USERID );
				$module->setReportConfig( $reportID, 'lastupdated_time', time() );
			}
			elseif ( substr( $key, 0, 12 ) == 'report-data-' )
			{
				// Update report definition.
				$reportID = substr( $key, 12 );
				$module->setReportData( $reportID, $data["report-data-$reportID"] );
				$module->setReportConfig( $reportID, 'lastupdated_user', USERID );
				$module->setReportConfig( $reportID, 'lastupdated_time', time() );
			}
			elseif ( substr( $key, 0, 14 ) == 'report-delete-' )
			{
				// Remove report from project.
				$reportID = substr( $key, 14 );
				$module->deleteReport( $reportID );
			}
		}
	}
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
?>" class="fas fa-arrow-circle-left fs11"> Back to advanced reports</a>
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
     <li><?php echo htmlspecialchars( $reportID ); ?> &nbsp;<i>(<?php
			echo htmlspecialchars( $data["report-config-$reportID"]['label'] ); ?>)</i></li>
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
   <td><?php echo htmlspecialchars( $reportID ); ?></td>
   <td>
    <input type="checkbox" name="report-add-<?php
			echo htmlspecialchars( $reportID ); ?>" value="1" checked>
    Add this report
    <ul>
<?php
			foreach ( [ 'type' => 'Report type', 'label' => 'Report label',
			            'category' => 'Report category', 'visible' => 'Report is visible',
			            'roles_access' => 'Report accessible by roles',
			            'download' => 'Report can be downloaded',
			            'roles_download' => 'Report downloadable by roles',
			            'as_image' => 'Report can be retrieved as an image' ]
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
   <td><?php echo htmlspecialchars( $reportID ); ?></td>
   <td>
<?php
			if ( $changedConfig )
			{
?>
    <input type="checkbox" name="report-config-<?php
			echo htmlspecialchars( $reportID ); ?>" value="1" checked>
    Update report configuration (changes highlighted below)
    <br>
<?php
			}
			if ( $changedData )
			{
?>
    <input type="checkbox" name="report-data-<?php
			echo htmlspecialchars( $reportID ); ?>" value="1" checked>
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
			            'as_image' => 'Report can be retrieved as an image' ]
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
						$configValue[$configVer] = htmlspecialchars( $configValue[$configVer] );
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
   <td><?php echo htmlspecialchars( $reportID ); ?></td>
   <td>
    <input type="checkbox" name="report-delete-<?php
			echo htmlspecialchars( $reportID ); ?>" value="1">
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
				$configValue = htmlspecialchars( $configValue );
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
    <input type="hidden" name="import_data" value="<?php echo htmlspecialchars( $fileData ); ?>">
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
<p style="font-size:14px;color:#f00"><?php echo htmlspecialchars( $error ); ?></p>
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

