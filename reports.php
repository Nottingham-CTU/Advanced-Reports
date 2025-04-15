<?php
/*
 *	Advanced reports overview page.
 */

namespace Nottingham\AdvancedReports;


// Convert old reports data to v1.2.4+ format.
$projectID = $module->getProjectID();
if ( $module->getProjectSetting( 'report-list' ) !== null )
{
	$settings = $module->getProjectSettings( $projectID );
	foreach ( $settings as $settingKey => $settingValue )
	{
		if ( in_array( $settingKey, ['enabled', 'edit-if-design', 'edit-if-reports'] ) ||
		     substr( $settingKey, 0, 9 ) == 'reserved-' )
		{
			continue;
		}
		$module->setSystemSetting( "p$projectID-$settingKey", $settingValue );
		$module->removeProjectSetting( $settingKey, $projectID );
	}
}


// Get and sort the list of reports.
$listReports = $module->getReportList();
uasort( $listReports, [ $module, 'sortReports' ] );



// Display the project header
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';



?>
<div class="projhdr">
 <?php echo $module->tt('advanced_reports'), "\n"; ?>
</div>
<?php
if ( $module->isReportEditable() )
{
?>
<p style="font-size:11px;text-align:right">
 <a href="<?php echo $module->getUrl( 'reports_edit.php' )
?>"><i class="fas fa-pencil-alt fs11"></i> <?php echo $module->tt('edit_reports'); ?></a>
</p>
<?php
}
?>
<p>&nbsp;</p>
<ul>
<?php
$lastCategory = '';
foreach ( $listReports as $reportID => $infoReport )
{
	if ( ! $infoReport['visible'] || ! $module->isReportAccessible( $reportID ) )
	{
		continue;
	}
	if ( $infoReport['category'] != $lastCategory )
	{
?>
</ul>
<p><b><?php echo htmlspecialchars( $infoReport['category'] ); ?></b></p>
<ul>
<?php
		$lastCategory = $infoReport['category'];
	}
?>
 <li><a href="<?php
	echo $module->getUrl( 'view.php?report_id=' . $reportID ); ?>"><?php
	echo htmlspecialchars( $infoReport['label'] ); ?></a></li>
<?php
}
?>
</ul>
<p>&nbsp;</p>
<?php

// Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
