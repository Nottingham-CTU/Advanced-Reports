<?php
/**
 *	Accumulation Reports view page.
 */

namespace Nottingham\AdvancedReports;

const ACC_SMART_VAR = '[accumulation-number]';



// Verify the report exists, and is an accumulation report.
// Redirect to main reports page if not.
$reportID = $_GET['report_id'];
$listReports = $module->getReportList();
if ( ! isset( $listReports[$reportID] ) || $listReports[$reportID]['type'] != 'accumulation' )
{
	header( 'Location: ' . $module->getUrl( 'reports.php' ) );
	exit;
}


// Determine the request type (normal/csv/api).
$disableAccessControl = isset( $disableAccessControl ) ? $disableAccessControl : false;
$isApiRequest = isset( $isApiRequest ) ? $isApiRequest : false;
$isInternalRequest = isset( $isInternalRequest ) ? $isInternalRequest : false;
$isCsvDownload = ( ! $isApiRequest && isset( $_GET['download'] ) &&
                   ( $isInternalRequest || $module->isReportDownloadable( $reportID ) ) );
$requestType = ( ( $isApiRequest && ! $isInternalRequest )
                 ? 'api' : ( $isCsvDownload ? 'download' : false ) );


// Check user can view this report, redirect to main reports page if not.
if ( ! $disableAccessControl && ! $isApiRequest && ! $module->isReportAccessible( $reportID ) )
{
	header( 'Location: ' . $module->getUrl( 'reports.php' ) );
	exit;
}


// Get the report data.
$reportConfig = $listReports[$reportID];
$reportData = $module->getReportData( $reportID );

// Get the record names.
$dataTable = method_exists( '\REDCap', 'getDataTable' )
                ? \REDCap::getDataTable( $module->getProjectId() ) : ( 'redcap' . '_data' );
$listRecords = [];
$queryRecords = $module->query( 'SELECT DISTINCT record FROM ' . $dataTable .
                                ' WHERE project_id = ?', [ $module->getProjectId() ] );
while ( $infoRecord = $queryRecords->fetch_assoc() )
{
	$listRecords[] = $infoRecord['record'];
}

// Get the accumulation start, end and step values.
if ( ! empty( $listRecords ) )
{
	$accStart = \REDCap::evaluateLogic( $reportData['acc_start'], $module->getProjectId(),
	                                    $listRecords[0], null, 1, null, null, null, true );
	$accEnd = \REDCap::evaluateLogic( $reportData['acc_end'], $module->getProjectId(),
	                                  $listRecords[0], null, 1, null, null, null, true );
	$accStep = ( ( preg_match( '/^[0-9]+(\.[0-9]{1,2})?$/', $reportData['acc_step'] ) &&
	               $reportData['acc_step'] != 0 ) ? $reportData['acc_step'] : 1 ) * 1;
	$accFlip = 1;
	if ( $accStart === null || $accEnd === null )
	{
		$listRecords = [];
	}
	elseif ( $accStart > $accEnd )
	{
		$accStep *= -1;
		$accFlip = -1;
	}
}


// Build the result table.
$resultTable = [];
$hasGroups = false;
$listGroups = [];
$listColNames = [];
if ( ! empty( $listRecords ) )
{
	// Get the accumulation totals from the records.
	for ( $accThis = $accStart;
	      ( $accThis * $accFlip ) <= ( $accEnd * $accFlip ); $accThis += $accStep )
	{
		$accStr = strval( $accThis );
		$resultTable[ $accStr ] = [];
		foreach ( $listRecords as $recordName )
		{
			$groupName =
				\REDCap::evaluateLogic( $module->replaceLogicVars( $reportData['group_logic'],
				                                                   ACC_SMART_VAR, $accStr ),
				                        $module->getProjectId(), $recordName, null, 1, null, null,
				                        null, true );
			if ( $groupName == null )
			{
				$groupName = '';
			}
			$groupName = strval( $groupName );
			$listGroups[ $groupName ] = true;
			if ( $groupName != '' )
			{
				$hasGroups = true;
			}
			if ( ! isset( $resultTable[ $accStr ][ $groupName ] ) )
			{
				$resultTable[ $accStr ][ $groupName ] = 0;
			}
			$accRecord =
				\REDCap::evaluateLogic( $module->replaceLogicVars( $reportData['acc_logic'],
				                                                   ACC_SMART_VAR, $accStr ),
				                        $module->getProjectId(), $recordName, null, 1, null, null,
				                        null, true );
			if ( ! is_numeric( $accRecord ) )
			{
				$accRecord = 0;
			}
			$resultTable[ $accStr ][ $groupName ] += $accRecord;
		}
	}

	// Get sorted list of groups.
	$listGroups = array_keys( $listGroups );
	sort( $listGroups, SORT_NATURAL | SORT_FLAG_CASE );

	// Calculate differences between accumulation totals and calculate the column names.
	$accPrev = null;
	foreach ( array_keys( $resultTable ) as $accStr )
	{
		foreach ( $listGroups as $groupName )
		{
			if ( ! isset( $resultTable[ $accStr ][ $groupName ] ) )
			{
				$resultTable[ $accStr ][ $groupName ] = 0;
			}
			$resultTable[ $accStr ][ $groupName ] =
				[ 'val' => $resultTable[ $accStr ][ $groupName ],
				  'diff' => $resultTable[ $accStr ][ $groupName ] ];
			if ( $accPrev !== null )
			{
				$resultTable[ $accStr ][ $groupName ]['diff'] =
					$resultTable[ $accStr ][ $groupName ]['val'] -
					$resultTable[ $accPrev ][ $groupName ]['val'];
			}
		}
		$listColNames[ $accStr ] = $accStr;
		if ( $reportData['col_logic'] != '' )
		{
			$colName =
				\REDCap::evaluateLogic( $module->replaceLogicVars( $reportData['col_logic'],
				                                                   ACC_SMART_VAR, $accStr ),
				                        $module->getProjectId(), $listRecords[0], null, 1, null,
				                        null, null, true );
			if ( $colName !== null )
			{
				$listColNames[ $accStr ] = strval( $colName );
			}
		}
		$accPrev = $accStr;
	}
}



// Get the overall totals.
$listTotals = [];
if ( $hasGroups )
{
	foreach ( array_keys( $resultTable ) as $accStr )
	{
		$listTotals[ $accStr ] = [ 'val' => 0, 'diff' => 0 ];
		foreach ( $listGroups as $groupName )
		{
			$listTotals[ $accStr ]['val'] += $resultTable[ $accStr ][ $groupName ]['val'];
			$listTotals[ $accStr ]['diff'] += $resultTable[ $accStr ][ $groupName ]['diff'];
		}
	}
}



// Format and return the result table for API requests.
if ( $isApiRequest )
{
	foreach ( array_keys( $resultTable ) as $accStr )
	{
		if ( $hasGroups )
		{
			$resultGroups = [];
			$resultValue = 0;
			$resultChange = 0;
			foreach ( $listGroups as $groupName )
			{
				$resultGroups[ $groupName ] =
					[ 'value' => $resultTable[ $accStr ][ $groupName ]['val'],
					  'change' => $resultTable[ $accStr ][ $groupName ]['diff'] ];
			}
			$resultTable[ $accStr ] =
				[ 'label' => $listColNames[ $accStr ],
				  'value' => $listTotals[ $accStr ]['val'],
				  'change' => $listTotals[ $accStr ]['diff'],
				  'groups' => $resultGroups ];
		}
		else
		{
			$resultTable[ $accStr ] =
				[ 'label' => $listColNames[ $accStr ],
				  'value' => $resultTable[ $accStr ]['']['val'],
				  'change' => $resultTable[ $accStr ]['']['diff'] ];
		}
	}
	return $resultTable;
}



// If column order is to be reversed for output, do this now.
if ( $reportData['col_reverse'] )
{
	$listColNames = array_reverse( $listColNames, true );
}



// Function to format output according to display format.
function formatForOutput( $values, $format )
{
	if ( $format == 'diff' )
	{
		return ( $values['diff'] >= 0 ? '+' : '' ) . $values['diff'];
	}
	if ( $format == 'diffu' )
	{
		return strval( $values['diff'] );
	}
	if ( $format == 'cumul/diff' )
	{
		return $values['val'] . ' (' . ( $values['diff'] >= 0 ? '+' : '' ) . $values['diff'] . ')';
	}
	return $values['val'];
}



// Handle report download.
if ( $isCsvDownload )
{
	if ( ! $isInternalRequest )
	{
		$module->writeCSVDownloadHeaders( $reportID );
	}
	if ( $hasGroups )
	{
		echo '"Group",';
	}
	$firstField = true;
	foreach ( $listColNames as $colName )
	{
		echo $firstField ? '' : ',';
		$firstField = false;
		echo '"';
		$module->echoText( str_replace( '"', '""', $colName ) );
		echo '"';
	}
	foreach ( $listGroups as $groupName )
	{
		echo "\n";
		if ( $hasGroups )
		{
			echo '"';
			$module->echoText( str_replace( '"', '""', $groupName ) );
			echo '",';
		}
		$firstField = true;
		foreach ( $listColNames as $accStr => $colName )
		{
			echo $firstField ? '' : ',';
			$firstField = false;
			echo '"';
			$module->echoText( str_replace( '"', '""',
			                                formatForOutput( $resultTable[ $accStr ][ $groupName ],
			                                                 $reportData['display'] ) ) );
			echo '"';
		}
	}
	if ( $hasGroups && $reportData['group_total'] )
	{
		echo "\n";
		echo '"Total"';
		foreach ( $listColNames as $accStr => $colName )
		{
			echo ',"';
			$module->echoText( str_replace( '"', '""',
			                                formatForOutput( $listTotals[ $accStr ],
			                                                 $reportData['display'] ) ) );
			echo '"';
		}
	}
	if ( $isInternalRequest )
	{
		return;
	}
	exit;
}



// Handle retrieve report as image.
if ( isset( $_GET['as_image'] ) && $reportConfig['as_image'] )
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
	// Calculate column widths based on column name string lengths.
	$imgColumnWidths = [];
	$imgExtraRows = 0;
	if ( $hasGroups )
	{
		$imgColumnWidths[0] = ( strlen( 'Group' ) * $imgHeaderCharW ) + 5;
		if ( $reportData['group_total'] )
		{
			$thisWidth = ( strlen( 'Total' ) * $imgHeaderCharW ) + 5;
			if ( $imgColumnWidths[0] < $thisWidth )
			{
				$imgColumnWidths[0] = $thisWidth;
			}
			$imgExtraRows++;
		}
	}
	foreach ( $listColNames as $accStr => $colName )
	{
		$imgColumnWidths[ $accStr ] = ( strlen( $colName ) * $imgHeaderCharW ) + 5;
	}
	// Check the data in each column for each record, increase the column widths if necessary.
	foreach ( $listGroups as $groupName )
	{
		if ( $hasGroups )
		{
			$thisWidth = ( strlen( $groupName ) * $imgHeaderCharW ) + 5;
			if ( $imgColumnWidths[0] < $thisWidth )
			{
				$imgColumnWidths[0] = $thisWidth;
			}
		}
		foreach ( $listColNames as $accStr => $colName )
		{
			$imgParsedData = formatForOutput( $resultTable[ $accStr ][ $groupName ],
			                                  $reportData['display'] );
			$thisWidth = ( strlen( $imgParsedData ) * $imgDataCharW ) + 5;
			if ( $imgColumnWidths[ $accStr ] < $thisWidth )
			{
				$imgColumnWidths[ $accStr ] = $thisWidth;
			}
		}
	}
	// Calculate the image dimensions, create the image, and set the colours (black/white).
	$imgWidth = array_sum( $imgColumnWidths ) + 1;
	$imgHeight = $imgHeaderH + ( ( count( $listGroups ) + $imgExtraRows ) * ( $imgDataH ) ) + 1;
	$img = imagecreate( $imgWidth, $imgHeight );
	imagecolorallocate( $img, 255, 255, 255 );
	$imgBlack = imagecolorallocate( $img, 0, 0, 0 );
	// Draw the column headers.
	$posW = 0;
	$posH = 0;
	if ( $hasGroups )
	{
		$thisWidth = $imgColumnWidths[0];
		imagerectangle( $img, $posW, $posH, $posW + $thisWidth, $posH + $imgHeaderH, $imgBlack );
		imagestring( $img, $imgHeaderFont, $posW + 2, $posH + 1, 'Group', $imgBlack );
		$posW += $thisWidth;
	}
	foreach ( $listColNames as $accStr => $colName )
	{
		$thisWidth = $imgColumnWidths[ $accStr ];
		imagerectangle( $img, $posW, $posH, $posW + $thisWidth, $posH + $imgHeaderH, $imgBlack );
		imagestring( $img, $imgHeaderFont, $posW + 2, $posH + 1, $colName, $imgBlack );
		$posW += $thisWidth;
	}
	// Draw each row of data.
	$posW = 0;
	$posH += $imgHeaderH;
	foreach ( $listGroups as $groupName )
	{
		if ( $hasGroups )
		{
			$thisWidth = $imgColumnWidths[0];
			imagerectangle( $img, $posW, $posH, $posW + $thisWidth, $posH + $imgDataH, $imgBlack );
			imagestring( $img, $imgDataFont, $posW + 2, $posH + 1, $groupName, $imgBlack );
			$posW += $thisWidth;
		}
		foreach ( $listColNames as $accStr => $colName )
		{
			$imgParsedData = formatForOutput( $resultTable[ $accStr ][ $groupName ],
			                                  $reportData['display'] );
			$thisWidth = $imgColumnWidths[ $accStr ];
			imagerectangle( $img, $posW, $posH, $posW + $thisWidth, $posH + $imgDataH, $imgBlack );
			imagestring( $img, $imgDataFont, $posW + 2, $posH + 1, $imgParsedData, $imgBlack );
			$posW += $thisWidth;
		}
		$posW = 0;
		$posH += $imgDataH;
	}
	if ( $hasGroups && $reportData['group_total'] )
	{
		$thisWidth = $imgColumnWidths[0];
		imagerectangle( $img, $posW, $posH, $posW + $thisWidth, $posH + $imgDataH, $imgBlack );
		imagestring( $img, $imgDataFont, $posW + 2, $posH + 1, 'Total', $imgBlack );
		$posW += $thisWidth;
		foreach ( $listColNames as $accStr => $colName )
		{
			$imgParsedData = formatForOutput( $listTotals[ $accStr ], $reportData['display'] );
			$thisWidth = $imgColumnWidths[ $accStr ];
			imagerectangle( $img, $posW, $posH, $posW + $thisWidth, $posH + $imgDataH, $imgBlack );
			imagestring( $img, $imgDataFont, $posW + 2, $posH + 1, $imgParsedData, $imgBlack );
			$posW += $thisWidth;
		}
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
$module->outputViewReportHeader( $reportConfig['label'], 'accumulation', true );

// Initialise the row counter.
$rowCount = 0;


// If a description is provided, output it here.
if ( isset( $reportData['desc'] ) && $reportData['desc'] != '' )
{
?>
<p class="mod-advrep-description"><?php
	echo $module->parseDescription( $reportData['desc'] ); ?></p>
<?php
}


if ( count( $resultTable ) > 0 )
{
?>
<table id="mod-advrep-table" class="mod-advrep-datatable dataTable">
 <thead>
  <tr>
<?php
	$colNum = 0;
	if ( $hasGroups )
	{
?>
   <th class="sorting" data-colnum="<?php echo $colNum; ?>">
    <?php echo $module->escapeHTML( 'Group' ), "\n"; ?>
   </th>
<?php
		$colNum++;
	}
	foreach ( $listColNames as $colName )
	{
?>
   <th class="sorting" data-colnum="<?php echo $colNum; ?>">
    <?php echo $module->escapeHTML( $colName ), "\n"; ?>
   </th>
<?php
		$colNum++;
	}
?>
  </tr>
 </thead>
 <tbody>
<?php
	foreach ( $listGroups as $groupName )
	{
		$rowCount++;
?>
  <tr>
<?php
		if ( $hasGroups )
		{
?>
   <td><?php echo $module->escapeHTML( $groupName ); ?></td>
<?php
		}
		foreach ( $listColNames as $accStr => $colName )
		{
			$sortParam = '';
			if ( $reportData['display'] == 'cumul/diff' )
			{
				$sortParam = ' data-sortvalue="' .
				             $module->escapeHTML(
				                 formatForOutput($resultTable[ $accStr ][ $groupName ], 'cumul') ) .
				             '"';
			}
			echo '   <td', $sortParam, '>',
				 $module->escapeHTML( formatForOutput( $resultTable[ $accStr ][ $groupName ],
				                                       $reportData['display'] ) ), '</td>';
		}
?>
  </tr>
<?php
	}
?>
 </tbody>
<?php
	if ( $hasGroups && $reportData['group_total'] )
	{
		$rowCount++;
?>
 <tfoot>
  <tr>
   <td><?php echo $module->escapeHTML( 'Total' ); ?></td>
<?php
		foreach ( $listColNames as $accStr => $colName )
		{
?>
   <td><?php echo $module->escapeHTML( formatForOutput( $listTotals[ $accStr ],
                                                        $reportData['display'] ) ); ?></td>
<?php
		}
?>
  </tr>
 </tfoot>
<?php
	}
?>
</table>
<p>Total rows returned: <span id="filtercount"></span><?php echo $rowCount; ?></p>
<?php
}
else
{
?>
<p>No rows returned</p>
<?php
}


$module->outputViewReportJS();


// Display the footer
if ( $disableAccessControl ) $htmlPage->PrintFooter();
else require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
