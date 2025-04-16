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
		$error = ['error_no_file_uploaded'];
	}
	if ( $mode == 'verify' ) // no error
	{
		$fileData = file_get_contents( $_FILES['import_file']['tmp_name'] );
		$data = json_decode( $fileData, true );
		if ( $data == null || ! is_array( $data ) || ! isset( $data['report-list'] ) ||
			 ( ! is_array( $data['report-list'] ) && ! is_string( $data['report-list'] ) ) )
		{
			$mode = 'error';
			$error = ['error_exp_file_invalid'];
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
				$error = [ 'error_exp_file_inv_report_id', $reportID ];
				break;
			}
			if ( ! isset( $data["report-config-$reportID"] ) ||
			     ! array_key_exists( "report-data-$reportID", $data ) )
			{
				$mode = 'error';
				$error = [ 'error_exp_file_data_missing', $reportID ];
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
		$error = ['error_invalid_file_data'];
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
 <?php echo $module->tt('import_advanced_reports'), "\n"; ?>
</div>
<p style="font-size:11px">
 <a href="<?php echo $module->getUrl( 'reports.php' );
?>"><i class="fas fa-arrow-circle-left fs11"></i> <?php
echo $module->tt('back_to_advanced_reports'); ?></a>
</p>
<?php


// Display the file upload form.
if ( $mode == 'upload' )
{


?>
<form method="post" enctype="multipart/form-data">
 <table class="mod-advrep-formtable">
  <tr>
   <td><?php echo $module->tt('import_file'); ?></td>
   <td>
    <input type="file" name="import_file">
   </td>
  </tr>
  <tr>
   <td></td>
   <td>
    <input type="submit" value="<?php echo $module->tt('import_reports'); ?>">
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
   <th colspan="2"><?php echo $module->tt('import_skipped'); ?></th>
  </tr>
  <tr>
   <td colspan="2" style="text-align:left">
    <ul>
<?php
		foreach ( $listSkipped as $reportID )
		{
			$reportLabel = $module->getReportConfig( $reportID, 'label' );
			echo '     <li>', $module->escape( $reportID ), ' &nbsp;',
			     ( $reportLabel == '' ? '' :
			       '<i>(' . $module->escape( $reportLabel ) . ')</i>' ), "</li>\n";
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
   <th colspan="2"><?php echo $module->tt('import_identical'); ?></th>
  </tr>
  <tr>
   <td colspan="2" style="text-align:left">
    <ul>
<?php
		foreach ( $listIdentical as $reportID )
		{
?>
     <li><?php echo $module->escape( $reportID ); ?> &nbsp;<i>(<?php
			echo $module->escape( $data["report-config-$reportID"]['label'] ); ?>)</i></li>
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
   <th colspan="2"><?php echo $module->tt('import_new'); ?></th>
  </tr>
<?php
		foreach ( $listNew as $reportID )
		{
?>
  <tr>
   <td><?php echo $module->escape( $reportID ); ?></td>
   <td>
    <input type="checkbox" name="report-add-<?php
			echo $module->escape( $reportID ); ?>" value="1" checked>
    <?php echo $module->tt('import_add'), "\n"; ?>
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
					$configValue = $module->tt( 'opt_' . ( $configValue ? 'yes' : 'no' ) );
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
   <th colspan="2"><?php echo $module->tt('import_changed'); ?></th>
  </tr>
<?php
		foreach ( $listChanged as $reportChange )
		{
			$reportID = $reportChange['id'];
			$changedConfig = $reportChange['config'];
			$changedData = $reportChange['data'];
?>
  <tr>
   <td><?php echo $module->escape( $reportID ); ?></td>
   <td>
<?php
			if ( $changedConfig )
			{
?>
    <input type="checkbox" name="report-config-<?php
			echo $module->escape( $reportID ); ?>" value="1" checked>
    <?php echo $module->tt('import_update_config'), "\n"; ?>
    <br>
<?php
			}
			if ( $changedData )
			{
?>
    <input type="checkbox" name="report-data-<?php
			echo $module->escape( $reportID ); ?>" value="1" checked>
    <?php echo $module->tt('import_update_data'), "\n"; ?>
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
						$configValue[$configVer] =
							$module->tt( 'opt_' . ( $configValue[$configVer] ? 'yes' : 'no' ) );
					}
					elseif ( is_string( $configValue[$configVer] ) )
					{
						$configValue[$configVer] = $module->escape( $configValue[$configVer] );
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
   <th colspan="2"><?php echo $module->tt('import_deleted'); ?></th>
  </tr>
<?php
		foreach ( $listDeleted as $reportID )
		{
?>
  <tr>
   <td><?php echo $module->escape( $reportID ); ?></td>
   <td>
    <input type="checkbox" name="report-delete-<?php
			echo $module->escape( $reportID ); ?>" value="1">
    <?php echo $module->tt('import_delete'), "\n"; ?>
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
				$configValue = $module->escape( $configValue );
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
    <input type="submit" value="<?php echo $module->tt('import_update_reports'); ?>">
    <input type="hidden" name="import_data" value="<?php echo $module->escape( $fileData ); ?>">
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
<p style="font-size:14px;color:#f00"><?php echo $module->tt( ...$error ); ?></p>
<?php


}
// Display success message.
elseif ( $mode == 'complete' )
{


?>
<p style="font-size:14px"><?php echo $module->tt('import_complete'); ?></p>
<?php


}


// Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';

