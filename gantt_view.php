<?php
/**
 *	Gantt chart view page.
 */



define( 'GANTT_DAY', 86400 );
define( 'GANTT_WEEK', ( GANTT_DAY * 7 ) );
define( 'GANTT_SPLIT_DAY', 6 );
define( 'GANTT_SPLIT_WEEK', 7 );
define( 'GANTT_STEP_DAY', ( GANTT_DAY / GANTT_SPLIT_DAY ) );
define( 'GANTT_STEP_WEEK', ( GANTT_WEEK / GANTT_SPLIT_WEEK ) );


// Verify the report exists, is a Gantt report, and is visible.
// Redirect to main reports page if not.
$reportID = $_GET['report_id'];
$listReports = $module->getReportList();
if ( ! isset( $listReports[$reportID] ) || $listReports[$reportID]['type'] != 'gantt' )
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



// Determine the report date range.
$reportStart = strtotime( '-' . ( gmdate( 'N' ) - 1 ) . ' days 00:00:00 UTC' );
$reportMiddle = strtotime( '+2 weeks 00:00:00 UTC', $reportStart );
$reportEnd = strtotime( 'first day of +4 months 00:00:00 UTC', $reportStart );


// Get the report data.
$listChartEntries = [];
foreach ( REDCap::getData( 'array' ) as $infoRecord )
{
	$listReportLabels = [];
	$listReportCategories = [];
	foreach ( $reportData['labels'] as $infoLabel )
	{
		if ( REDCap::isLongitudinal() )
		{
			$event = REDCap::getEventIdFromUniqueEvent( $infoLabel['event'] );
		}
		else
		{
			$event = array_key_first( $infoRecord );
		}
		if ( ! array_key_exists( $event, $infoRecord ) )
		{
			continue;
		}
		$field = $infoLabel['field'];
		$listReportLabels[ $infoLabel['name'] ] = $infoRecord[ $event ][ $field ];
	}
	if ( empty( $listReportLabels ) )
	{
		continue;
	}
	foreach ( $reportData['chart_categories'] as $infoCategory )
	{
		if ( REDCap::isLongitudinal() )
		{
			$sEvent = REDCap::getEventIdFromUniqueEvent( $infoCategory['start_event'] );
			$eEvent = REDCap::getEventIdFromUniqueEvent( $infoCategory['end_event'] );
		}
		else
		{
			$sEvent = $eEvent = array_key_first( $infoRecord );
		}
		if ( ! array_key_exists( $sEvent, $infoRecord ) ||
		     ! array_key_exists( $eEvent, $infoRecord ) )
		{
			continue;
		}
		$sField = $infoCategory['start_field'];
		$eField = $infoCategory['end_field'];
		$sDate = $infoRecord[ $sEvent ][ $sField ];
		$eDate = $infoRecord[ $eEvent ][ $eField ];
		if ( ! preg_match( '/^-?[0-9]+$/', $sDate ) )
		{
			$sDate = strtotime( $sDate . ' UTC' );
		}
		if ( ! preg_match( '/^-?[0-9]+$/', $eDate ) )
		{
			$eDate = strtotime( $eDate . ' UTC' );
		}
		if ( $sDate > $eDate || $eDate < $reportStart || $sDate > $reportEnd )
		{
			continue;
		}
		$listReportCategories[] = [ 'name' => $infoCategory['name'],
		                            'start' => $sDate, 'end' => $eDate ];
	}
	if ( empty( $listReportCategories ) )
	{
		continue;
	}
	$listChartEntries[] = [ 'labels' => $listReportLabels, 'categories' => $listReportCategories ];
}


// Display the project header and report navigation links.
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$module->outputViewReportHeader( $reportConfig['label'] );

?>
<div class="mod-advrep-gantt" style="grid-template-columns:repeat(<?php
echo count( $reportData['labels'] ) ?>,min-content)">
<?php
foreach ( $reportData['labels'] as $infoLabel )
{
?>
 <div class="mod-advrep-gantt-hdr"><?php echo htmlspecialchars( $infoLabel['name'] ); ?></div>
<?php
}
$prevMonthHdr = '';
$prevDateIncrement = '';
$dateIncrement = GANTT_DAY;
$dateSpan = GANTT_SPLIT_DAY;
$dateStyle = '';
for ( $date = $reportStart; $date < $reportEnd; $date += $dateIncrement )
{
	if ( $date >= $reportMiddle )
	{
		$dateIncrement = GANTT_WEEK;
		$dateSpan = GANTT_SPLIT_WEEK;
		$dateStyle = ';border-bottom-width:5px';
	}
	$thisMonthHdr = gmdate( 'M', $date );
	if ( $thisMonthHdr == $prevMonthHdr && $dateIncrement == $prevDateIncrement )
	{
		$thisMonthHdr = '&nbsp;&nbsp;&nbsp;';
	}
	else
	{
		$prevMonthHdr = $thisMonthHdr;
		$prevDateIncrement = $dateIncrement;
	}
	$thisDayHdr = gmdate( 'j', $date );
	$thisDateHdr = '<span>' . $thisMonthHdr . '&nbsp;</span>' . $thisDayHdr;
?>
 <div class="mod-advrep-gantt-date" style="grid-column:span <?php
	echo $dateSpan, $dateStyle; ?>"><?php echo $thisDateHdr; ?></div>
<?php
}
?>
<?php
foreach ( $listChartEntries as $infoChartEntry )
{
	$firstLabel = true;
	foreach ( $reportData['labels'] as $infoLabel )
	{
		$labelValue = $infoChartEntry['labels'][$infoLabel['name']] ?? '';
		$chartEntryLines = 2;
?>
 <div style="grid-row-end:span <?php
		echo count( $infoChartEntry['categories'] ), $firstLabel ? ';grid-column-start:1' : '';
?>"><?php echo $labelValue == '' ? '&nbsp;' : htmlspecialchars( $labelValue ); ?></div>
<?php
		$firstLabel = false;
	}
	foreach ( $infoChartEntry['categories'] as $infoChartCategory )
	{
		// Determine the start/end timestamps from the category definition and record data.
		$itemStartTime = $infoChartCategory['start'];
		$itemEndTime = $infoChartCategory['end'];
		// Fit the record/category item within the time range of the chart.
		$itemStartTime = $itemStartTime < $reportStart ? $reportStart : $itemStartTime;
		$itemEndTime = $itemEndTime > $reportEnd ? $reportEnd : $itemEndTime;
		// Place the item correctly within the chart.
		$itemStart = 1 + count( $reportData['labels'] );
		if ( $itemStartTime > $reportMiddle )
		{
			$itemStart += floor( ( $reportMiddle - $reportStart ) / GANTT_STEP_DAY );
			$itemStart += floor( ( $itemStartTime - $reportMiddle ) / GANTT_STEP_WEEK );
		}
		else
		{
			$itemStart += floor( ( $itemStartTime - $reportStart ) / GANTT_STEP_DAY );
		}
		$itemEnd = 1 + count( $reportData['labels'] );
		if ( $itemEndTime > $reportMiddle )
		{
			$itemEnd += floor( ( $reportMiddle - $reportStart ) / GANTT_STEP_DAY );
			$itemEnd += floor( ( $itemEndTime - $reportMiddle ) / GANTT_STEP_WEEK );
		}
		else
		{
			$itemEnd += ceil( ( $itemEndTime - $reportStart ) / GANTT_STEP_DAY );
		}
?>
 <div style="grid-column:<?php
echo "$itemStart/$itemEnd"; ?>" title="<?php
echo htmlspecialchars( $infoChartCategory['name'] ); ?>"><?php
echo htmlspecialchars( $infoChartCategory['name'] ); ?></div>
<?php
	}
}
?>
</div>
<?php

// Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';

