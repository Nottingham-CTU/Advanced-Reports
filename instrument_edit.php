<?php
/**
 *	Instrument Query Reports edit page.
 */

namespace Nottingham\AdvancedReports;



// Check user can edit Instrument Query reports and verify the report exists and is an Instrument
// Query report.
// Redirect to main reports page if not.
$reportID = $_GET['report_id'];
$listReports = $module->getReportList();
if ( ! $module->isReportEditable( 'instrument' ) ||
     ! isset( $listReports[$reportID] ) || $listReports[$reportID]['type'] != 'instrument' )
{
	header( 'Location: ' . $module->getUrl( 'reports.php' ) );
	exit;
}
$reportConfig = $listReports[$reportID];
$reportData = $module->getReportData( $reportID );



// Handle form submissions.
if ( ! empty( $_POST ) )
{
	// Validate data
	$validationMsg = '';
	// - Check the forms/fields are specified if an alias or join condition is specified.
	foreach ( $_POST['query_form'] as $i => $formName )
	{
		if ( $formName == '' &&
		     ( $i == 0 || $_POST['query_form_alias'][$i] != '' ||
		       $_POST['query_form_on'][ $i - 1 ] != '' ) )
		{
			$validationMsg =
					'Form cannot be empty if the first entry or if alias/condition specified.';
			break;
		}
	}
	if ( $validationMsg == '' )
	{
		foreach ( $_POST['query_select_field'] as $i => $fieldName )
		{
			if ( $fieldName == '' && $_POST['query_select_alias'][$i] != '' )
			{
				$validationMsg = 'Field cannot be empty if alias specified.';
			}
		}
	}
	// - Check the validity of forms join condition logic.
	if ( $validationMsg == '' )
	{
		try
		{
			foreach ( $_POST['query_form_on'] as $formCond )
			{
				if ( $formCond != '' )
				$module->parseLogic( $formCond, false, false, false );
			}
		}
		catch ( \Exception $e )
		{
			$validationMsg = 'Error in form join condition logic - ' . $e->getMessage();
		}
	}
	// - Check the validity of condition logic.
	if ( $validationMsg == '' && $_POST['query_where'] != '' )
	{
		try
		{
			$module->parseLogic( $_POST['query_where'], false, false, false );
		}
		catch ( \Exception $e )
		{
			$validationMsg = 'Error in condition logic - ' . $e->getMessage();
		}
	}
	// - Check the validity of sorting logic.
	if ( $validationMsg == '' && $_POST['query_orderby'] != '' )
	{
		try
		{
			$orderby = $_POST['query_orderby'];
			if ( strtolower( substr( rtrim( $orderby ), -5 ) ) == ' desc' )
			{
				$orderby = substr( rtrim( $orderby ), 0, -5 );
			}
			$module->parseLogic( $orderby, false, false, false );
		}
		catch ( \Exception $e )
		{
			$validationMsg = 'Error in sorting logic - ' . $e->getMessage();
		}
	}
	// - Check the validity of field name/logic.
	$hasGroupingUnselected = false;
	$hasGroupingSelected = false;
	if ( $validationMsg == '' )
	{
		try
		{
			foreach ( $_POST['query_select_field'] as $index => $fieldName )
			{
				if ( $fieldName != '' )
				{
					$module->parseLogic( $fieldName, false, true, false );
					if ( $_POST['query_grouping'][ $index ] == '' )
					{
						$hasGroupingUnselected = true;
					}
					if ( $_POST['query_grouping'][ $index ] != '' )
					{
						$hasGroupingSelected = true;
					}
				}
			}
		}
		catch ( \Exception $e )
		{
			$validationMsg = 'Error in field name/logic - ' . $e->getMessage();
		}
	}
	// - Check the validity of field name/logic.
	if ( $validationMsg == '' && $hasGroupingUnselected && $hasGroupingSelected )
	{
		$validationMsg = 'If grouping is selected, it must be selected for all fields';
	}
	if ( isset( $_SERVER['HTTP_X_RC_ADVREP_INSTQUERYCHK'] ) )
	{
		header( 'Content-Type: application/json' );
		if ( $validationMsg == '' )
		{
			echo 'true';
		}
		else
		{
			echo json_encode( $validationMsg );
		}
		exit;
	}
	if ( $validationMsg != '' )
	{
		exit;
	}

	// Save data
	$module->submitReportConfig( $reportID, true, 'image' );
	$reportData = [ 'desc' => $_POST['query_desc'], 'forms' => [], 'where' => $_POST['query_where'],
	                'orderby' => $_POST['query_orderby'], 'select' => [],
	                'nomissingdatacodes' => isset( $_POST['query_nomissingdatacodes'] ) ];
	foreach ( $_POST['query_form'] as $i => $formName )
	{
		if ( $formName == '' )
		{
			continue;
		}
		$reportData['forms'][] = [ 'form' => $formName, 'alias' => $_POST['query_form_alias'][$i],
		                           'join' => ( ( $i == 0 ) ? ''
		                                                 : $_POST['query_form_join'][ $i - 1 ] ),
		                           'on' => ( ( $i == 0 ) ? ''
		                                                 : $_POST['query_form_on'][ $i - 1 ] ) ];
	}
	foreach ( $_POST['query_select_field'] as $i => $fieldName )
	{
		if ( $fieldName == '' )
		{
			continue;
		}
		$reportData['select'][] = [ 'field' => $fieldName,
		                            'alias' => $_POST['query_select_alias'][$i],
		                            'grouping' => $_POST['query_grouping'][$i] ];
	}
	$module->setReportData( $reportID, $reportData );
	header( 'Location: ' . $module->getUrl( 'reports_edit.php' ) );
	exit;
}



function writeInstrumentRow1( $setWidths, $formVal, $aliasVal ) // TODO: join option
{
	global $module;
?>

      <td style="text-align:left;width:<?php echo $setWidths ? '60px' : 'unset'; ?>">
<?php
	if ( ! $setWidths )
	{
?>
       <select name="query_form_join[]">
        <option value="inner">Inner Join</option>
        <option value="left">Left Join</option>
       </select>
<?php
	}
?>
      </td>
      <td style="text-align:left;width:<?php echo $setWidths ? '60px' : 'unset'; ?>"><?php
$module->outputInstrumentDropdown( 'query_form[]', $formVal ?? '' );
?></td>
      <td style="text-align:left;width:unset">
       <input type="text" name="query_form_alias[]" placeholder="alias (optional)"
              value="<?php echo $module->escapeHTML( $aliasVal ?? '' ); ?>"
              style="width:100%<?php echo $setWidths ? ';min-width:80px' : ''; ?>">
      </td>
<?php
}



function writeInstrumentRow2( $onCondVal, $formVal, $firstFormVal, $aliasVal, $firstAliasVal )
{
	global $module, $recordIDField;
	$formVal = ( $aliasVal == '' ) ? $formVal : $aliasVal;
	$firstFormVal = ( $firstAliasVal == '' ) ? $firstFormVal : $firstAliasVal;
?>

      <td style="text-align:left;width:unset">On Condition</td>
      <td colspan="2" style="text-align:left;width:unset">
       <input type="text" name="query_form_on[]" placeholder="condition logic"
              style="width:100%;max-width:unset"
              data-default="<?php echo $formVal == '' || $firstFormVal == '' ? ''
                                       : ( '[' . $formVal . '][' . $recordIDField . '] = [' .
                                           $firstFormVal . '][' . $recordIDField . ']' ); ?>"
              value="<?php echo $module->escapeHTML( $onCondVal ?? '' ); ?>">
      </td>
<?php
}



function writeSelectRow( $setWidths, $fieldVal, $aliasVal, $groupVal )
{
	global $module;
?>

      <td style="text-align:center;width:15px;padding-right:0px">
       <div style="margin-bottom:-8px;cursor:pointer"
            onclick="$(this).closest('tr').insertBefore($(this).closest('tr').prev('tr:visible'))">
        <i class="fas fa-caret-up fs11"></i>
       </div>
       <div style="cursor:pointer"
            onclick="$(this).closest('tr').insertAfter($(this).closest('tr').next('tr:visible'))">
        <i class="fas fa-caret-down fs11"></i>
       </div>
      </td>
      <td style="text-align:left;width:<?php echo $setWidths ? '50%;max-width:425px' : 'unset'; ?>">
       <input type="text" name="query_select_field[]" placeholder="field name/logic"
              value="<?php echo $module->escapeHTML( $fieldVal ?? '' ); ?>"
              data-list="field-var-list" style="width:100%">
      </td>
      <td style="text-align:left;width:unset">
       <input type="text" name="query_select_alias[]" placeholder="alias (optional)"
              value="<?php echo $module->escapeHTML( $aliasVal ?? '' ); ?>"
              style="width:100%<?php echo $setWidths ? ';min-width:60px' : ''; ?>">
      </td>
      <td style="text-align:left;width:unset">
       <select name="query_grouping[]" style="font-style:italic"
               onchange="$(this).val()==''?$(this).css('font-style','italic')
                                          :$(this).css('font-style','')">
        <option value="" style="font-style:italic">grouping (optional)</option>
<?php
	foreach ( [ 'this' => 'group by this field',
	            'max' => 'maximum value',
	            'mean' => 'mean average',
	            'median' => 'median average',
	            'min' => 'minimum value',
	            'percent' => 'percentage',
	            'sum' => 'sum of all values' ]
	          as $optVal => $optLbl )
	{
?>
        <option <?php echo $groupVal == $optVal ? 'selected ' : ''; ?>value="<?php echo $optVal; ?>"
                style="font-style:normal"><?php echo $optLbl; ?></option>
<?php
	}
?>
       </select>
      </td>
<?php
}



// Get fields and smart variables for field suggestions.
$recordIDField = \REDCap::getRecordIdField();
$smartVarsInfo = \Piping::getSpecialTagsInfo();
$listSmartVars = array_merge( array_keys( $smartVarsInfo[ $GLOBALS['lang']['global_17'] ] ),
                              array_keys( $smartVarsInfo[ $GLOBALS['lang']['global_156'] ] ),
                              [ 'is-download' ] );
array_walk( $listSmartVars, function( &$i ) { $i = '[' . $i . ']'; } );
$listFormVars = [];
foreach ( array_keys( $module->getInstrumentList() ) as $instrument )
{
	$listFormVars[ $instrument ] = array_values(
	                                array_unique( array_merge( [ $recordIDField ],
	                                                    \REDCap::getFieldNames( $instrument ) ) ) );
}




// Display the project header
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$module->writeStyle();

?>
<div class="projhdr">
 Advanced Reports &#8212; Edit Instrument Query Report: <?php
echo $module->escapeHTML( $reportID ), "\n"; ?>
</div>
<p style="font-size:11px">
 <a href="<?php echo $module->getUrl( 'reports_edit.php' );
?>" class=""><i class="fas fa-arrow-circle-left fs11"></i> Back to edit reports</a>
 &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
 <a href="<?php echo $module->getUrl( 'README-InstrumentQ.md' );
?>" target="_blank"><i class="fas fa-book fs11"></i> View Documentation</a>
</p>
<form method="post" id="queryform">
 <table class="mod-advrep-formtable">
<?php $module->outputReportConfigOptions( $reportConfig, true, 'image' ); ?>
  <tr><th colspan="2">Report Definition</th></tr>
  <tr>
   <td>Description</td>
   <td>
    <textarea name="query_desc" style="height:70px;max-width:95%;white-space:pre"><?php
echo $reportData['desc'] ?? ''; ?></textarea>
    <br>
    <span class="field-desc">
     Optional. If specified, displays this text above the report.
     Supports &lt;a&gt;, &lt;b&gt; and &lt;i&gt; HTML tags.
    </span>
   </td>
  </tr>
  <tr>
   <td></td>
   <td>
    <div id="query_err_msg" class="mod-advrep-errmsg" style="display:none;margin-top:5px">
     <i class="fas fa-exclamation-triangle"></i>
     <span></span>
    </div>
   </td>
  </tr>
  <tr>
   <td>Instruments</td>
   <td>
    <table id="inst-entries-tbl" style="width:95%;max-width:750px">
     <tr><?php
writeInstrumentRow1( true, $reportData['forms'][0]['form'], $reportData['forms'][0]['alias'] );
?></tr>
<?php
$firstForm = true;
foreach ( $reportData['forms'] as $formData )
{
	if ( $firstForm )
	{
		$firstForm = false;
		continue;
	}
?>
     <tr><?php writeInstrumentRow1( false, $formData['form'], $formData['alias'] ); ?></tr>
     <tr><?php
writeInstrumentRow2( $formData['on'], $formData['form'], $reportData['forms'][0]['form'],
                     $formData['alias'], $reportData['forms'][0]['alias'] );
?></tr>
<?php
}
?>
     <tr style="display:none"><?php writeInstrumentRow1( false, '', '' ); ?></tr>
     <tr style="display:none"><?php
writeInstrumentRow2( '', '', $reportData['forms'][0]['form'],
                     '', $reportData['forms'][0]['alias'] );
?></tr>
    </table>
    <span id="inst-entries-link" style="display:none">
     <a onclick="$('#inst-entries-tbl tr').slice(-2).clone(true).css('display',''
                    ).insertBefore($('#inst-entries-tbl tr').slice(-2,-1));return false"
        href="#" class=""><i class="fas fa-plus-circle fs12"></i> Add instrument</a>
     <br>
     <span style="font-size:0.8em">
      Inner Join will only include rows where there is data on both sides of the join.<br>
      Left Join will include rows where there is data before the join, even if there is no data
      after the join.
     </span>
    </span>
   </td>
  </tr>
  <tr>
   <td>Condition</td>
   <td>
    <input type="text" name="query_where" style="width:95%;max-width:750px"
           placeholder="condition logic"
           value="<?php echo $module->escapeHTML( $reportData['where'] ?? '' ); ?>">
   </td>
  </tr>
  <tr>
   <td>Sorting</td>
   <td>
    <input type="text" name="query_orderby" style="width:95%;max-width:750px"
           placeholder="sorting logic" list="field-var-list-sort"
           value="<?php echo $module->escapeHTML( $reportData['orderby'] ?? '' ); ?>">
   </td>
  </tr>
  <tr>
   <td>Fields to display</td>
   <td style="padding:0px">
    <table id="field-entries-tbl" style="width:95%;max-width:750px">
     <tr><?php
writeSelectRow( true, $reportData['select'][0]['field'], $reportData['select'][0]['alias'],
                $reportData['select'][0]['grouping'] );
?></tr>
<?php
$firstField = true;
foreach ( $reportData['select'] as $fieldData )
{
	if ( $firstField )
	{
		$firstField = false;
		continue;
	}
?>
     <tr><?php
writeSelectRow( false, $fieldData['field'], $fieldData['alias'], $fieldData['grouping'] );
?></tr>
<?php
}
?>
     <tr style="display:none"><?php
writeSelectRow( false, '', '', '' );
?></tr>
    </table>
    <span id="field-entries-link" style="display:none">
     &nbsp;
     <a onclick="$('#field-entries-tbl tr').last().clone().css('display',''
                    ).insertBefore($('#field-entries-tbl tr').last());$('#field-entries-tbl tr')
                    .eq(-2).find('[name=&quot;query_select_field[]&quot;]').combobox();return false"
        href="#" class=""><i class="fas fa-plus-circle fs12"></i> Add field</a>
    </span>
   </td>
  </tr>
  <tr>
   <td>Hide missing data codes</td>
   <td>
    <input type="checkbox" name="query_nomissingdatacodes"<?php
echo $reportData['nomissingdatacodes'] ? ' checked' : '';
?>>
   </td>
  </tr>
  <tr><td colspan="2">&nbsp;</td></tr>
  <tr>
   <td></td>
   <td>
    <input type="submit" value="Save Report">
   </td>
  </tr>
 </table>
</form>
<datalist id="field-var-list"></datalist>
<datalist id="field-var-list-sort"></datalist>
<?php $module->outputComboboxJS(); ?>
<script type="text/javascript">
 (function ()
 {
   $('#inst-entries-link, #field-entries-link').css('display','')
   var vValidated = false
   $('#queryform')[0].onsubmit = function()
   {
     if ( vValidated )
     {
       return true
     }
     $.ajax( { url : '<?php echo $module->getUrl( 'instrument_edit.php?report_id=' . $reportID ); ?>',
               method : 'POST',
               data : $('#queryform').serialize(),
                        headers : { 'X-RC-AdvRep-InstQueryChk' : '1' },
                        dataType : 'json',
                        success : function ( result )
                        {
                          if ( result === true )
                          {
                            vValidated = true
                            $('#queryform')[0].submit()
                            $('#query_err_msg').css( 'display', 'none' )
                          }
                          else
                          {
                            var vMsg = 'Invalid instrument query: ' + result
                            $('#query_err_msg span').text( vMsg )
                            $('#query_err_msg').css( 'display', '' )
                          }
                        }
             } )
     return false
   }
   var vSmartVars = <?php echo json_encode( $listSmartVars ), "\n"; ?>
   var vFormVars = <?php echo json_encode( $listFormVars ), "\n"; ?>
   var vFuncUpdateVars = function()
   {
     $('#field-var-list').html('')
     $('#field-var-list-sort').html('')
     var vFormElems = $('[name="query_form[]"]')
     var vAliasElems = $('[name="query_form_alias[]"]')
     var vOnCondElems = $('[name="query_form_on[]"]')
     var vFirstAlias = vAliasElems[0].value == '' ? vFormElems[0].value : vAliasElems[0].value
     for ( var vIndex = 0; vIndex < vFormElems.length; vIndex++ )
     {
       var vFormName = vFormElems[ vIndex ].value
       var vAlias = vAliasElems[ vIndex ].value == '' ? vFormName : vAliasElems[ vIndex ].value
       if ( vIndex > 0 )
       {
         var vOnCondElem = vOnCondElems.eq( vIndex - 1 )
         var vNewOnCond = ''
         if ( vAlias != '' && vFirstAlias != '' )
         {
           vNewOnCond = '[' + vAlias + '][<?php echo $recordIDField; ?>] = ' +
                        '[' + vFirstAlias + '][<?php echo $recordIDField; ?>]'
         }
         if ( vOnCondElem.data('default') == vOnCondElem.val() )
         {
           vOnCondElem.val(vNewOnCond)
         }
         vOnCondElem.data('default',vNewOnCond)
       }
       if ( vFormName == '' )
       {
         continue
       }
       vFormVars[ vFormName ].forEach( function( vItem )
       {
         $('<option></option>').text( '[' + vAlias + '][' + vItem + ']'
                                                        ).appendTo( $('#field-var-list') )
         $('<option></option>').text( '[' + vAlias + '][' + vItem + ']'
                                                        ).appendTo( $('#field-var-list-sort') )
         $('<option></option>').text( '[' + vAlias + '][' + vItem + '] DESC'
                                                        ).appendTo( $('#field-var-list-sort') )
       } )
     }
     vSmartVars.forEach( function( vItem )
     {
       $('<option></option>').text( vItem ).appendTo( $('#field-var-list') )
     } )
   }
   vFuncUpdateVars()
   $('[name="query_form[]"]').change( vFuncUpdateVars )
   $('[name="query_form_alias[]"]').keyup( vFuncUpdateVars )
   setTimeout( function()
   {
     $('[name="query_select_field[]"]:visible').combobox()
   }, 2000 )
   $('[name="query_form[]"]').css( 'max-width', '450px' )
 })()
</script>
<?php

// Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
