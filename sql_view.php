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
$query = mysqli_query( $conn, $module->sqlPlaceholderReplace( $reportData['sql_query'] ) );
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
	// Determine the fonts and character sizes for the report.
	$imgHeaderFont = 5;
	$imgDataFont = 4;
	$imgHeaderCharW = imagefontwidth( $imgHeaderFont );
	$imgHeaderCharH = imagefontheight( $imgHeaderFont );
	$imgDataCharW = imagefontwidth( $imgDataFont );
	$imgDataCharH = imagefontheight( $imgDataFont );
	$imgHeaderH = $imgHeaderCharH + 2;
	$imgDataH = $imgDataCharH + 2;
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
	// Calculate column widths based on column name string lengths.
	$imgColumnWidths = [];
	foreach ( $columns as $columnName )
	{
		$imgColumnWidths[ $columnName ] = ( strlen( $columnName ) * $imgHeaderCharW ) + 5;
	}
	// Check the data in each column for each record, increase the column widths if necessary.
	foreach ( $resultData as $infoRecord )
	{
		foreach ( $columns as $columnName )
		{
			$imgParsedData = isset( $infoRecord[$columnName] )
			                    ? $module->parseHTML( $infoRecord[$columnName], true ) : '';
			$thisWidth = ( strlen( $imgParsedData ) * $imgDataCharW ) + 5;
			if ( $imgColumnWidths[$columnName] < $thisWidth )
			{
				$imgColumnWidths[$columnName] = $thisWidth;
			}
		}
	}
	// Calculate the image dimensions, create the image, and set the colours (black/white).
	$imgWidth = array_sum( $imgColumnWidths ) + 1;
	$imgHeight = $imgHeaderH + ( count( $resultData ) * ( $imgDataH ) ) + 1;
	$img = imagecreate( $imgWidth, $imgHeight );
	imagecolorallocate( $img, 255, 255, 255 );
	$imgBlack = imagecolorallocate( $img, 0, 0, 0 );
	// Draw the column headers.
	$posW = 0;
	$posH = 0;
	foreach ( $columns as $columnName )
	{
		$thisWidth = $imgColumnWidths[$columnName];
		imagerectangle( $img, $posW, $posH, $posW + $thisWidth, $posH + $imgHeaderH, $imgBlack );
		imagestring( $img, $imgHeaderFont, $posW + 2, $posH + 1, $columnName, $imgBlack );
		$posW += $thisWidth;
	}
	// Draw each row of data.
	$posW = 0;
	$posH += $imgHeaderH;
	foreach ( $resultData as $infoRecord )
	{
		foreach ( $columns as $columnName )
		{
			$imgParsedData = isset( $infoRecord[$columnName] )
			                    ? $module->parseHTML( $infoRecord[$columnName], true ) : '';
			$thisWidth = $imgColumnWidths[$columnName];
			imagerectangle( $img, $posW, $posH, $posW + $thisWidth, $posH + $imgDataH, $imgBlack );
			imagestring( $img, $imgDataFont, $posW + 2, $posH + 1, $imgParsedData, $imgBlack );
			$posW += $thisWidth;
		}
		$posW = 0;
		$posH += $imgDataH;
	}
	// Output the image as a PNG and exit.
	imagepng( $img );
	if ( $isInternalRequest )
	{
		return;
	}
	exit;
}



// Display the header and report navigation links.
if ( $disableAccessControl ) ($htmlPage = new \HtmlPage)->PrintHeader( false );
else require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$module->outputViewReportHeader( $reportConfig['label'], 'sql', true );

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


// Display the footer
if ( $disableAccessControl ) $htmlPage->PrintFooter();
else require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
