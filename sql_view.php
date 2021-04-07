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
$query = mysqli_query( $conn, $module->sqlPlaceholderReplace( $reportData['sql_query'] ) );
$resultType = $reportData['sql_type'] ?? 'normal';
$columns = [];


// If the result type is EAV, construct the result dataset before outputting.
if ( $resultType == 'eav' || $resultType == 'eav-id' )
{
	if ( $resultType == 'eav-id' )
	{
		$columns[] = mysqli_fetch_field( $query )->name;
	}
	$resultData = [];
	while ( $infoRecord = mysqli_fetch_row( $query ) )
	{
		// Only allow SQL output with 3 fields.
		if ( count( $infoRecord ) != 3 )
		{
			break;
		}
		// Assemble the result dataset from the SQL output, interpreting the fields as follows.
		// Field 0: row ID
		// Field 1: column name
		// Field 2: data
		// Fields 3+: reserved for future use
		$resultData[ $infoRecord[0] ][ $infoRecord[1] ] = $infoRecord[2];
		if ( ! in_array( $infoRecord[1], $columns ) )
		{
			$columns[] = $infoRecord[1];
		}
		if ( $resultType == 'eav-id' )
		{
			$resultData[ $infoRecord[0] ][ $columns[0] ] = $infoRecord[0];
		}
	}
}




// Handle report download.
if ( isset( $_GET['download'] ) && $module->isReportDownloadable( $reportID ) )
{
	$module->writeCSVDownloadHeaders( $reportID );
	if ( $resultType == 'eav' || $resultType == 'eav-id' )
	{
		$first = true;
		foreach ( $columns as $columnName )
		{
			echo $first ? '' : ',';
			echo '"', str_replace( '"', '""', $columnName );
			$first = false;
		}
		echo "\n";
		foreach ( $resultData as $infoRecord )
		{
			$first = true;
			foreach ( $columns as $columnName )
			{
				echo $first ? '' : ',';
				echo '"';
				if ( isset( $infoRecord[ $columnName ] ) )
				{
					echo str_replace( '"', '""',
					                  $module->parseHTML( $infoRecord[ $columnName ], true ) );
				}
				echo '"';
				$first = false;
			}
		}
		exit;
	}
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
$module->outputViewReportHeader( $reportConfig['label'], 'sql' );

?>
</p>
<table class="mod-advrep-datatable">
<?php

// Output the report table.
if ( $resultType == 'eav' || $resultType == 'eav-id' )
{
?>
 <tr>
<?php
	foreach ( $columns as $columnName )
	{
?>
  <th><?php echo htmlspecialchars( $columnName ); ?></th>
<?php
	}
?>
 </tr>
<?php
	foreach ( $resultData as $infoRecord )
	{
?>
 <tr>
<?php
		foreach ( $columns as $columnName )
		{
?>
  <td><?php echo isset( $infoRecord[ $columnName ] )
                 ? $module->parseHTML( $infoRecord[ $columnName ] ) : ''; ?></td>
<?php
		}
?>
 </tr>
<?php
	}
}
else
{
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
}
?>
</table>
<?php

// Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
