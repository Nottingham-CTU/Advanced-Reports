<?php
/*
 *	Advanced reports overview page.
 */



function sortReports( $reportA, $reportB )
{
	$categoryA = $reportA['category'] ?? '';
	$categoryB = $reportB['category'] ?? '';
	$labelA = $reportA['label'] ?? '';
	$labelB = $reportB['label'] ?? '';
	return ( strcmp( $categoryA, $categoryB ) * 10 ) + strcmp( $labelA, $labelB );
}



// Display the project header
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

$listReports = $module->getReportList();
uasort( $listReports, 'sortReports' );

?>
<div class="projhdr">
 Advanced Reports
</div>
<?php
if ( $module->isReportEditable() )
{
?>
<p style="font-size:11px;text-align:right">
 <a href="<?php echo $module->getUrl( 'reports_edit.php' )
?>" class="fas fa-pencil-alt fs11"> Edit reports</a>
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
	echo $module->getUrl( $infoReport['type'] . '_view.php?report_id=' . $reportID ); ?>"><?php
	echo htmlspecialchars( $infoReport['label'] ); ?></a></li>
<?php
}
?>
</ul>
<p>&nbsp;</p>
<?php
if ( $module->framework->getUser()->isSuperUser() )
{
?>
<p>&nbsp;</p>
<hr style="max-width:300px;margin-left:0px">
<p><b>Administrative Options</b></p>
<ul>
 <li>
  <a href="<?php echo $module->getUrl( 'export_reports.php' ) ?>">Export report definitions</a>
 </li>
 <li>
  <a href="<?php echo $module->getUrl( 'import_reports.php' ) ?>">Import report definitions</a>
 </li>
</ul>
<?php
}
?>
<?php

// Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
