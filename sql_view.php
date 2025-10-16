<?php
/**
 *	SQL Reports view page.
 */

namespace Nottingham\AdvancedReports;



// Verify the report exists, and is an SQL report.
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


// Determine the request type (normal/csv/api).
$disableAccessControl = isset( $disableAccessControl ) ? $disableAccessControl : false;
$isApiRequest = isset( $isApiRequest ) ? $isApiRequest : false;
$isInternalRequest = isset( $isInternalRequest ) ? $isInternalRequest : false;
$isCsvDownload = ( ! $isApiRequest && isset( $_GET['download'] ) &&
                   ( $isInternalRequest || $module->isReportDownloadable( $reportID ) ) );


// Check user can view this report, redirect to main reports page if not.
if ( ! $disableAccessControl && ! $isApiRequest && ! $module->isReportAccessible( $reportID ) )
{
	header( 'Location: ' . $module->getUrl( 'reports.php' ) );
	exit;
}



// Get the report data.
$sqlQuery = $module->sqlPlaceholderReplace( $reportData['sql_query'] );
$query = mysqli_query( $GLOBALS['conn'], $sqlQuery );
$resultType = $reportData['sql_type'] ?? 'normal';
$columns = [];


// If the result type is EAV, construct the result dataset before outputting.
if ( $resultType == 'eav' || $resultType == 'eav-id' )
{
	if ( isset( $reportData['sql_cols'] ) && $reportData['sql_cols'] != '' )
	{
		$columns = str_getcsv( $reportData['sql_cols'], ',', '"', '' );
	}
	if ( $resultType == 'eav-id' )
	{
		array_unshift( $columns, mysqli_fetch_field( $query )->name );
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



// Return the result table for API requests.
if ( $isApiRequest )
{
	if ( $resultType != 'eav' && $resultType != 'eav-id' )
	{
		$resultData = [];
		while ( $infoRecord = mysqli_fetch_assoc( $query ) )
		{
			$resultData[] = $infoRecord;
		}
	}
	return $resultData;
}




// Handle report download.
if ( $isCsvDownload )
{
	if ( ! $isInternalRequest )
	{
		$module->writeCSVDownloadHeaders( $reportID );
	}
	if ( $resultType == 'eav' || $resultType == 'eav-id' )
	{
		$first = true;
		foreach ( $columns as $columnName )
		{
			echo $first ? '' : ',';
			echo '"';
			$module->echoText( str_replace( '"', '""', $columnName ) );
			echo '"';
			$first = false;
		}
		foreach ( $resultData as $infoRecord )
		{
			echo "\n";
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
		if ( $isInternalRequest )
		{
			return;
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
				echo '"';
				$module->echoText( str_replace( '"', '""', $fieldName ) );
				echo '"';
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
	if ( $isInternalRequest )
	{
		return;
	}
	exit;
}



// Handle retrieve report as image.
if ( isset( $_GET['as_image']) && $reportConfig['as_image'] )
{
	if ( ! $isInternalRequest )
	{
		header( 'Content-Type: image/png' );
	}
	// If a non-EAV report, retrieve all the data so the table sizes can be calculated.
	// For EAV reports, this has already been done.
	if ( ! ( $resultType == 'eav' || $resultType == 'eav-id' ) )
	{
		$columns = [];
		$resultData = [];
		while ( $infoRecord = mysqli_fetch_assoc( $query ) )
		{
			if ( empty( $columns ) )
			{
				foreach ( $infoRecord as $fieldName => $value )
				{
					$columns[] = $fieldName;
				}
			}
			$resultData[] = $infoRecord;
		}
	}
	$img = $module->reportImageCreate();
	foreach ( [ 'reportImageRowPrepare', 'reportImageRowWrite' ] as $imageRowFunc )
	{
		// Prepare/draw the header row.
		$module->$imageRowFunc( $img, $columns );
		// Prepare/draw each row of data.
		foreach ( $resultData as $infoRecord )
		{
			$imgRow = [];
			foreach ( $columns as $columnName )
			{
				$imgRow[] = isset( $infoRecord[$columnName] )
				               ? $module->parseHTML( $infoRecord[$columnName], true ) : '';
			}
			$module->$imageRowFunc( $img, $imgRow );
		}
	}
	// Output the image as a PNG and exit.
	$module->reportImageOutput( $img );
	if ( $isInternalRequest )
	{
		return;
	}
	exit;
}



// Display the header and report navigation links.
$module->writePageHeader( $disableAccessControl );
$module->outputViewReportHeader( $reportConfig['label'], 'sql',
                                 [ 'canReset' => true, 'asImage' => $reportConfig['as_image'] ] );

// Initialise the row counter.
$rowCount = 0;


// If a description is provided, output it here.
if ( isset( $reportData['sql_desc'] ) && $reportData['sql_desc'] != '' )
{
?>
<p class="mod-advrep-description"><?php
	echo $module->parseDescription( $reportData['sql_desc'] ); ?></p>
<?php
}


?>
<table id="mod-advrep-table" class="mod-advrep-datatable dataTable">
<?php

// Output the report table (EAV types).
if ( $resultType == 'eav' || $resultType == 'eav-id' )
{
?>
 <thead>
  <tr>
<?php
	$colNum = 0;
	foreach ( $columns as $columnName )
	{
?>
   <th class="sorting" data-colnum="<?php echo $colNum; ?>">
    <?php echo $module->escapeHTML( $columnName ), "\n"; ?>
   </th>
<?php
		$colNum++;
	}
?>
  </tr>
 </thead>
 <tbody>
<?php
	foreach ( $resultData as $infoRecord )
	{
		$rowCount++;
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
	if ( $rowCount == 0 )
	{
?>
  <tr><td>No rows returned</td></tr>
<?php
	}
?>
 </tbody>
<?php
}
// Output the report table (normal dataset type).
else
{
	while ( $infoRecord = mysqli_fetch_assoc( $query ) )
	{
		$rowCount++;
		if ( empty( $columns ) )
		{
?>
 <thead>
  <tr>
<?php
			$colNum = 0;
			foreach ( $infoRecord as $fieldName => $value )
			{
				$columns[] = $fieldName;
?>
  <th class="sorting" data-colnum="<?php echo $colNum; ?>">
   <?php echo $module->escapeHTML( $fieldName ), "\n"; ?>
  </th>
<?php
				$colNum++;
			}
?>
  </tr>
 </thead>
 <tbody>
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
	if ( $rowCount == 0 )
	{
?>
  <tr><td>No rows returned</td></tr>
<?php
	}
?>
 </tbody>
<?php
}
?>
</table>
<?php

if ( $rowCount > 0 )
{
?>
<p>Total rows returned: <span id="filtercount"></span><?php echo $rowCount; ?></p>
<?php
}


$module->outputViewReportJS();

if ( defined('SUPER_USER') && SUPER_USER )
{
?>
<script type="text/javascript">
$(function()
{
  var vNavLinks = $('.mod-advrep-navlinks')
  var vDQTLink = $('<a href="#"><i class="fas fa-database fs11"></i> ' +
                   'Open in Database Query Tool</a>')
  vDQTLink.on('click', function(ev)
  {
    ev.preventDefault()
    var vForm = $('<form action="' + app_path_webroot + 'ControlCenter/database_query_tool.php" ' +
                  'method="post"></form>')
    vForm.append('<input type="hidden" name="query" ' +
                 'value="<?php echo $module->escape( $sqlQuery ); ?>">')
    vForm.append('<input type="hidden" name="redcap_csrf_token" value="' + redcap_csrf_token + '">')
    $('body').append(vForm)
    vForm.trigger('submit')
  })
  vNavLinks.append(vDQTLink)
})
</script>
<?php
}


// Display the footer
$module->writePageFooter();
