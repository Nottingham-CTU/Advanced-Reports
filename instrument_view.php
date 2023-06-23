<?php
/**
 *	Project Query Reports view page.
 */

namespace Nottingham\AdvancedReports;



// Verify the report exists, is a project query, and is visible.
// Redirect to main reports page if not.
$reportID = $_GET['report_id'];
$listReports = $module->getReportList();
if ( ! isset( $listReports[$reportID] ) || $listReports[$reportID]['type'] != 'instrument' )
{
	header( 'Location: ' . $module->getUrl( 'reports.php' ) );
	exit;
}


// Check user can view this report, redirect to main reports page if not.
if ( ! $module->isReportAccessible( $reportID ) )
{
	header( 'Location: ' . $module->getUrl( 'reports.php' ) );
	exit;
}

// Get the report data.
$reportConfig = $listReports[$reportID];
$reportData = $module->getReportData( $reportID );
$resultParams = [ 'return_format' => 'json-array', 'combine_checkbox_values' => true,
                  'exportDataAccessGroups' => true, 'exportSurveyFields' => true,
                  'returnBlankForGrayFormStatus' => true,
                  'removeMissingDataCodes' => $reportData['nomissingdatacodes'] ];
$redcapFields = [ 'redcap_event_name', 'redcap_repeat_instance', 'redcap_data_access_group' ];

// Build the result table.
$resultTable = [[]];
foreach ( $reportData['forms'] as $queryForm )
{
	// Get the form name and alias (use form name for alias if not defined).
	$form = $queryForm['form'];
	$alias = $queryForm['alias'] == '' ? $form : $queryForm['alias'];
	// Get the fields for the form and retrieve the values and value labels for each record.
	$fields = array_unique( array_merge( [ \REDCap::getRecordIdField() ],
	                                     \REDCap::getFieldNames( $form ) ) );
	$formValues = \REDCap::getData( $resultParams +
	                                [ 'exportAsLabels' => false, 'fields' => $fields ] );
	$formLabels = \REDCap::getData( $resultParams +
	                                [ 'exportAsLabels' => true, 'fields' => $fields ] );
	$newResultTable = [];
	foreach ( $resultTable as $resultRow )
	{
		foreach ( $formValues as $i => $formValuesRow )
		{
			if ( $formValuesRow[ $form . '_complete' ] === '' )
			{
				continue;
			}
			$formLabelsRow = $formLabels[$i];
			// Check if the row from this form should be joined with the result table row.
			$doJoin = true;
			if ( $queryForm['on'] != '' )
			{
				list( $joinFunction, $joinParamData ) = $module->parseLogic( $queryForm['on'] );
				$joinParams = [];
				foreach ( $joinParamData as $joinParamItem )
				{
					if ( $joinParamItem[0] == $alias )
					{
						if ( $joinParamItem[2] == 'label' )
						{
							$joinParams[] = $formLabelsRow[ $joinParamItem[1] ];
						}
						else
						{
							$joinParams[] = $formValuesRow[ $joinParamItem[1] ];
						}
					}
					else
					{
						$joinParams[] = $resultRow[ '[' . $joinParamItem[0] . '][' .
						                            $joinParamItem[1] . ']' ][
						                              $joinParamItem[2] == 'label'
						                              ? 'label' : 'value' ];
					}
				}
				$doJoin = $joinFunction( ...$joinParams );
			}
			// Join the rows if required.
			if ( $doJoin )
			{
				$newResultRow = $resultRow;
				$insertedRedcapFields = false;
				foreach ( $fields as $field )
				{
					$newResultRow[ '[' . $alias . '][' . $field . ']' ] =
						[ 'value' => $formValuesRow[$field], 'label' => $formLabelsRow[$field] ];
					if ( ! $insertedRedcapFields )
					{
						foreach ( $redcapFields as $field )
						{
							if ( isset( $formValuesRow[$field] ) )
							{
								$newResultRow[ '[' . $alias . '][' . $field . ']' ] =
										[ 'value' => $formValuesRow[$field],
										  'label' => $formLabelsRow[$field] ];
							}
						}
						$insertedRedcapFields = true;
					}
				}
				$newResultTable[] = $newResultRow;
			}
		}
	}
	$resultTable = &$newResultTable;
	unset( $newResultTable );
}

// Run any where condition.
if ( $reportData['where'] != '' )
{
	$newResultTable = [];
	list( $whereFunction, $whereParamData ) = $module->parseLogic( $reportData['where'] );
	foreach ( $resultTable as $resultRow )
	{
		$whereParams = [];
		foreach ( $whereParamData as $whereParamItem )
		{
			$whereParams[] = $resultRow[ '[' . $whereParamItem[0] . '][' .
			                             $whereParamItem[1] . ']' ][
			                               $whereParamItem[2] == 'label'
			                               ? 'label' : 'value' ];
		}
		if ( $whereFunction( ...$whereParams ) )
		{
			$newResultTable[] = $resultRow;
		}
	}
	$resultTable = &$newResultTable;
	unset( $newResultTable );
}

// Perform any sorting.
if ( $reportData['orderby'] != '' )
{
	$sortDirection = 1;
	if ( strtolower( substr( rtrim( $reportData['orderby'] ), -5 ) ) == ' desc' )
	{
		$sortDirection = -1;
		$reportData['orderby'] = substr( rtrim( $reportData['orderby'] ), 0, -5 );
	}
	list( $sortFunction, $sortParamData ) = $module->parseLogic( $reportData['orderby'] );
	usort( $resultTable, function ( $resultRow1, $resultRow2 )
	                     use ( $sortFunction, $sortParamData, $sortDirection )
	{
		$sortParams1 = [];
		$sortParams2 = [];
		foreach ( $sortParamData as $sortParamItem )
		{
			$sortParams1[] = $resultRow1[ '[' . $sortParamItem[0] . '][' . $sortParamItem[1] . ']' ]
			                            [ $sortParamItem[2] == 'label' ? 'label' : 'value' ];
			$sortParams2[] = $resultRow2[ '[' . $sortParamItem[0] . '][' . $sortParamItem[1] . ']' ]
			                            [ $sortParamItem[2] == 'label' ? 'label' : 'value' ];
		}
		$sortValue1 = $sortFunction( ...$sortParams1 );
		$sortValue2 = $sortFunction( ...$sortParams2 );
		if ( $sortValue1 == $sortValue2 )
		{
			return 0;
		}
		return ( $sortValue1 < $sortValue2 ? -1 : 1 ) * $sortDirection;
	} );
}

// If fields to select specified, select them.
if ( ! empty( $reportData['select'] ) )
{
	$newResultTable = [];
	$selectFields = [];
	foreach ( $reportData['select'] as $selectField )
	{
		if ( $selectField['alias'] == '' )
		{
			$selectField['alias'] = $selectField['field'];
		}
		$selectFields[] = [ 'field' => $selectField['field'], 'alias' => $selectField['alias'],
		                    'function' => $module->parseLogic( $selectField['field'] ) ];
	}
	foreach ( $resultTable as $resultRow )
	{
		$newResultRow = [];
		foreach ( $selectFields as $selectField )
		{
			$selectParams = [];
			foreach ( $selectField['function'][1] as $selectParamItem )
			{
				$selectParams[] = $resultRow[ '[' . $selectParamItem[0] . '][' .
			                                  $selectParamItem[1] . ']' ][
			                                    $selectParamItem[2] == 'value'
			                                    ? 'value' : 'label' ];
			}
			$newResultRow[ $selectField['alias'] ] =
					$selectField['function'][0]( ...$selectParams );
		}
		$newResultTable[] = $newResultRow;
	}
	$resultTable = &$newResultTable;
	unset( $newResultTable );
}

// Display the project header and report navigation links.

require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$module->outputViewReportHeader( $reportConfig['label'], 'instrument', true );

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


?>
<table id="mod-advrep-table" class="mod-advrep-datatable dataTable">
 <thead>
  <tr>
<?php
if ( count( $resultTable ) > 0 )
{
	foreach ( $resultTable[0] as $field => $value )
	{
?>
   <th class="sorting"><?php echo $module->escapeHTML( $field ); ?></th>
<?php
	}
}
?>
  </tr>
 </thead>
 <tbody>
<?php
foreach ( $resultTable as $resultRow )
{
	$rowCount++;
?>
  <tr>
<?php
	foreach ( $resultRow as $value )
	{
		if ( is_array( $value ) )
		{
			$value = $value['label'];
		}
?>
   <td><?php echo $module->parseHTML( $value ); ?></td>
<?php
	}
	if ( $rowCount == 0 )
	{
?>
  <tr><td>No rows returned</td></tr>
<?php
	}
?>
  </tr>
<?php
}
?>
 </tbody>
</table>
<?php

if ( $rowCount > 0 )
{
?>
<p>Total rows returned: <span id="filtercount"></span><?php echo $rowCount; ?></p>
<?php
}


$module->outputViewReportJS();


// Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';