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
$canSaveIfApi = ( ! $module->getSystemSetting( 'admin-only-api' ) ||
                  $module->getUser()->isSuperUser() );
$canSaveIfPublic = ( ! $module->getSystemSetting( 'admin-only-public' ) ||
                     $module->getUser()->isSuperUser() );
$canSaveIfEditable = ( ! $module->getSystemSetting( 'admin-only-editable' ) ||
                       $module->getUser()->isSuperUser() );



// Handle form submissions.
if ( ! empty( $_POST ) )
{
	// Validate data
	$validationMsg = '';
	if ( ! $canSaveIfApi && $_POST['report_as_api'] == 'Y' )
	{
		$validationMsg = 'Reports with API access can only be saved by an administrator.';
	}
	if ( ! $canSaveIfPublic && $_POST['report_as_public'] == 'Y' )
	{
		$validationMsg = 'Reports with Public access can only be saved by an administrator.';
	}
	// - Check the forms/fields are specified if an alias or join condition is specified.
	if ( $validationMsg == '' )
	{
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
					if ( ! $canSaveIfEditable &&
					     preg_match( '/^\\[[a-z0-9_]+\\]\\[[a-z0-9_]+\\]:edit$/i', $fieldName ) )
					{
						$validationMsg = 'Reports with editable fields can only be saved ' .
						                 'by an administrator.';
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
	$module->submitReportConfig( $reportID, true, [ 'saveable', 'image', 'api', 'public' ] );
	$reportData = [ 'desc' => $_POST['query_desc'], 'forms' => [], 'where' => $_POST['query_where'],
	                'orderby' => $_POST['query_orderby'], 'select' => [],
	                'nomissingdatacodes' => isset( $_POST['query_nomissingdatacodes'] ),
	                'dateformat' => $_POST['query_dateformat'] ];
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



function writeInstrumentRow1( $setWidths, $formVal, $aliasVal, $joinVal = '' )
{
	global $module;

?>
      <td style="text-align:center;width:15px;padding-right:0px">
<?php
	if ( ! $setWidths )
	{
?>
       <div class="instq-row-from-move" style="cursor:ns-resize">
        <i class="fas fa-arrows-up-down fs11"></i>
       </div>
<?php
	}
?>
      </td>
      <td style="text-align:left;width:<?php echo $setWidths ? '60px' : 'unset'; ?>">
<?php
	if ( ! $setWidths )
	{
?>
       <select name="query_form_join[]">
        <option value="inner">Inner Join</option>
        <option value="left"<?php echo $joinVal == 'left' ? ' selected' : ''; ?>>Left Join</option>
       </select>
<?php
	}
?>
      </td>
      <td style="text-align:left;width:<?php echo $setWidths ? '60px' : 'unset'; ?>"><?php
$module->outputInstrumentDropdown( 'query_form[]', $formVal ?? '',
                                   [ 'redcap_users' => 'redcap_users - Project Users'] );
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
      <td style="width:unset"></td>
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
       <div class="instq-row-select-move" style="cursor:ns-resize">
        <i class="fas fa-arrows-up-down fs11"></i>
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
       <select name="query_grouping[]"<?php echo $groupVal == ''
                                                 ? ' style="font-style:italic"' : '', "\n"; ?>
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
                              [ 'is-download', 'is-api' ] );
array_walk( $listSmartVars, function( &$i ) { $i = '[' . $i . ']'; } );
$listCommonFormVars = [ $recordIDField ];
if ( \REDCap::isLongitudinal() )
{
	$listCommonFormVars[] = 'redcap_event_name';
}
$listCommonFormVars[] = 'redcap_repeat_instance';
if ( ! empty( \REDCap::getGroupNames() ) )
{
	$listCommonFormVars[] = 'redcap_data_access_group';
}
$listCommonFormVars2 = [ 'redcap_form_url', 'redcap_survey_url', 'redcap_created_by',
                         'redcap_created_time', 'redcap_updated_by', 'redcap_updated_time',
                         'redcap_last_instance' ];
$listFormVars = [];
foreach ( array_keys( $module->getInstrumentList() ) as $instrument )
{
	$formFieldNames = \REDCap::getFieldNames( $instrument );
	foreach ( $formFieldNames as $i => $formFieldName )
	{
		if ( \REDCap::getFieldType( $formFieldName ) == 'descriptive' )
		{
			unset( $formFieldNames[$i] );
		}
	}
	$listFormVars[ $instrument ] = array_values(
	                                array_unique( array_merge( $listCommonFormVars,
	                                                           array_values( $formFieldNames ),
	                                                           $listCommonFormVars2 ) ) );
}
$listFormVars['redcap_users'] = [ 'username', 'firstname', 'lastname', 'email', 'role_name', 'dag',
                                  'added', 'expiration', 'first_activity', 'last_activity' ];




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
<?php $module->outputReportConfigOptions( $reportConfig, true,
                                          [ 'saveable', 'image', 'api', 'public' ] ); ?>
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
     <tbody>
      <tr><?php
writeInstrumentRow1( true, $reportData['forms'][0]['form'], $reportData['forms'][0]['alias'] );
?></tr>
     </tbody>
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
     <tbody class="instq-row-from">
      <tr><?php
	writeInstrumentRow1( false, $formData['form'], $formData['alias'], $formData['join'] );
?></tr>
      <tr><?php
	writeInstrumentRow2( $formData['on'], $formData['form'], $reportData['forms'][0]['form'],
	                     $formData['alias'], $reportData['forms'][0]['alias'] );
?></tr>
     </tbody>
<?php
}
?>
     <tbody class="instq-row-from" style="display:none">
      <tr><?php writeInstrumentRow1( false, '', '' ); ?></tr>
      <tr><?php
writeInstrumentRow2( '', '', $reportData['forms'][0]['form'],
                     '', $reportData['forms'][0]['alias'] );
?></tr>
     </tbody>
    </table>
    <span id="inst-entries-link" style="display:none">
     <a onclick="$('#inst-entries-tbl tbody').last().clone().css('display',''
                    ).insertBefore($('#inst-entries-tbl tbody').last());return false"
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
     <tr class="instq-row-select"><?php
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
     <tr class="instq-row-select"><?php
	writeSelectRow( false, $fieldData['field'], $fieldData['alias'], $fieldData['grouping'] );
?></tr>
<?php
}
?>
     <tr class="instq-row-select" style="display:none"><?php
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
  <tr>
   <td>Date display format</td>
   <td>
    <select name="query_dateformat">
     <option value=""<?php echo $reportData['dateformat'] ?? '' == '' ? ' selected' : ''?>>
      Date fields (labels) in user's preferred format
     </option>
     <option value="upf"<?php echo $reportData['dateformat'] ?? '' == 'upf' ? ' selected' : ''?>>
      All date values in user's preferred format
     </option>
     <option value="dmy"<?php echo $reportData['dateformat'] ?? '' == 'dmy' ? ' selected' : ''?>>
      All date values in D-M-Y format
     </option>
     <option value="mdy"<?php echo $reportData['dateformat'] ?? '' == 'mdy' ? ' selected' : ''?>>
      All date values in M-D-Y format
     </option>
    </select>
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
     $.ajax( { url : window.location.href,
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
   $('[name="query_form[]"]:visible').change( vFuncUpdateVars )
   $('[name="query_form_alias[]"]:visible').keyup( vFuncUpdateVars )
   setTimeout( function()
   {
     $('[name="query_select_field[]"]:visible').combobox()
   }, 2000 )
   $('[name="query_form[]"]').css( 'max-width', '450px' )

   var vFuncMakeDraggable = function( vClass, vFirst = false )
   {
     if ( vClass == '.instq-row-from' && ! vFirst )
     {
       $('[name="query_form[]"]:visible').last().change( vFuncUpdateVars )
       $('[name="query_form_alias[]"]:visible').last().keyup( vFuncUpdateVars )
     }
     $(vClass).draggable( {
       axis: 'y',
       handle: vClass + '-move',
       revert: true,
       revertDuration: 0,
       start: function (ev, ui)
       {
         $(vClass).last().css('opacity','0').css('display','')
       },
       stop: function (ev, ui)
       {
         $(vClass).last().css('opacity','').css('display','none')
       },
       zIndex: 1000
     } )
     $(vClass).droppable( {
       accept: vClass,
       addClasses: false,
       drop: function (ev, ui)
       {
         $(ev.target).css('border-top','')
         $(ui.draggable).insertBefore($(ev.target))
       },
       over: function (ev, ui)
       {
         $(ev.target).css('border-top','2px solid #000')
       },
       out: function (ev, ui)
       {
         $(ev.target).css('border-top','')
       }
     } )
   }
   vFuncMakeDraggable('.instq-row-from', true)
   vFuncMakeDraggable('.instq-row-select', true)
   $('#inst-entries-link a').click(function(){vFuncMakeDraggable('.instq-row-from')})
   $('#field-entries-link a').click(function(){vFuncMakeDraggable('.instq-row-select')})
 })()
</script>
<?php

// Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
