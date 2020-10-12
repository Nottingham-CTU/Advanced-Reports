<?php
/**
 *	SQL Reports view page.
 */



// Verify the report exists, is an SQL report, and is visible.
// Redirect to main reports page if not.
$reportID = $_GET['report_id'];
$listReports = $module->getReportList();
if ( ! isset( $listReports[$reportID] ) || $listReports[$reportID]['type'] != 'sql' )
{
	header( 'Location: ' . $module->getUrl( 'reports.php' ) );
	exit;
}
$reportConfig = $listReports[$reportID];
$reportData = $module->getReportData( $reportID );



// Check user can view this report, redirect to main reports page if not.
if ( ! $module->isReportAccessible( $reportID ) )
{
	header( 'Location: ' . $module->getUrl( 'reports.php' ) );
	exit;
}



// Get the report data.
$sql = $reportData['sql_query'];
$query = mysqli_query( $conn, $sql );
$columns = [];




// Handle report download.
if ( isset( $_GET['download'] ) && $module->isReportDownloadable( $reportID ) )
{
	$module->writeCSVDownloadHeaders( $reportID );
	while ( $infoRecord = mysqli_fetch_assoc( $query ) )
	{
		if ( empty( $columns ) )
		{
			foreach ( $infoRecord as $fieldName => $value )
			{
				echo count( $columns ) == 0 ? '' : ',';
				$columns[] = $fieldName;
				echo '"', str_replace( '"', '""', $fieldName ), '"';
			}
		}
		echo "\n";
		foreach ( $columns as $fieldName )
		{
			echo $fieldName == $columns[0] ? '' : ',';
			echo '"',
			     str_replace( '"', '""', $module->parseHTML( $infoRecord[ $fieldName ], true ) ),
			     '"';
		}
	}
	exit;
}


// Display the project header and report navigation links.
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$module->outputViewReportHeader( $reportConfig['label'] );

?>
</p>
<table class="mod-advrep-datatable">
<?php

// Output the report table.
while ( $infoRecord = mysqli_fetch_assoc( $query ) )
{
	if ( empty( $columns ) )
	{
?>
 <tr>
<?php
		foreach ( $infoRecord as $fieldName => $value )
		{
			$columns[] = $fieldName;
?>
  <th><?php echo htmlspecialchars( $fieldName ); ?></th>
<?php
		}
?>
 </tr>
<?php
	}
?>
 <tr>
<?php
	foreach ( $columns as $fieldName )
	{
?>
  <td><?php echo $module->parseHTML( $infoRecord[ $fieldName ] ); ?></td>
<?php
	}
?>
 </tr>
<?php
}
?>
</table>
<?php

// Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
