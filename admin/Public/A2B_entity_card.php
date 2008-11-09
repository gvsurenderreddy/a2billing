<?php
include ("../lib/admin.defines.php");
include ("../lib/admin.module.access.php");
include ("../lib/Form/Class.FormHandler.inc.php");
include ("./form_data/FG_var_card.inc");
include ("../lib/admin.smarty.php");



if (! has_rights (ACX_CUSTOMER)) { 
	Header ("HTTP/1.0 401 Unauthorized");
	Header ("Location: PP_error.php?c=accessdenied");	   
	die();	   
}

/***********************************************************************************/

$HD_Form -> setDBHandler (DbConnect());
$HD_Form -> init();


/********************************* BATCH UPDATE ***********************************/
getpost_ifset(array('popup_select', 'popup_formname', 'popup_fieldname', 'upd_inuse', 'upd_status', 'upd_language', 'upd_tariff', 'upd_credit', 'upd_credittype', 'upd_simultaccess', 'upd_currency', 'upd_typepaid', 'upd_creditlimit', 'upd_enableexpire', 'upd_expirationdate', 'upd_expiredays', 'upd_runservice', 'upd_runservice', 'batchupdate', 'check', 'type', 'mode', 'addcredit', 'cardnumber','description','upd_id_group','upd_id_agent','upd_discount','upd_refill_type','upd_description'));

// CHECK IF REQUEST OF BATCH UPDATE
if ($batchupdate == 1 && is_array($check)) {
	$SQL_REFILL="";
	$HD_Form->prepare_list_subselection('list');
	if (isset($check['upd_credit'])||(strlen(trim($upd_credit))>0)) {//set to refil
		$SQL_REFILL_CREDIT="";
		$SQL_REFILL_WHERE="";
		if($type["upd_credit"] == 1) {//equal
			$SQL_REFILL_CREDIT="($upd_credit -credit) ";
			$SQL_REFILL_WHERE=" AND $upd_credit<>credit ";//never write 0 refill
		} elseif($type["upd_credit"] == 2) {//+-
			 $SQL_REFILL_CREDIT="($upd_credit) ";
		}else{ 
			 $SQL_REFILL_CREDIT="(-$upd_credit) ";
		}
		$SQL_REFILL="INSERT INTO cc_logrefill (credit,card_id,description,refill_type)
		SELECT $SQL_REFILL_CREDIT,a.id,'$upd_description','$upd_refill_type' from  ".$HD_Form->FG_TABLE_NAME."  as a ";
		if (strlen($HD_Form->FG_TABLE_CLAUSE)>1) {
			$SQL_REFILL .= ' WHERE '.$HD_Form->FG_TABLE_CLAUSE.$SQL_REFILL_WHERE;
		}elseif((strlen($SQL_REFILL_WHERE)>1)&&($type["upd_credit"] == 1)) {
			$SQL_REFILL .= " WHERE $upd_credit<>credit ";
		}
	}

	// Array ( [upd_simultaccess] => on [upd_currency] => on )	
	$loop_pass=0;
	$SQL_UPDATE = '';
	foreach ($check as $ind_field => $ind_val) {
		//echo "<br>::> $ind_field -";
		$myfield = substr($ind_field,4);
		if ($loop_pass!=0) $SQL_UPDATE.=',';
		
		// Standard update mode
		if (!isset($mode["$ind_field"]) || $mode["$ind_field"]==1) {		
			if (!isset($type["$ind_field"])) {		
				$SQL_UPDATE .= " $myfield='".$$ind_field."'";
			} else {
				$SQL_UPDATE .= " $myfield='".$type["$ind_field"]."'";
			}
		// Mode 2 - Equal - Add - Subtract
		} elseif($mode["$ind_field"]==2) {
			if (!isset($type["$ind_field"])) {		
				$SQL_UPDATE .= " $myfield='".$$ind_field."'";
			} else {
				if ($type["$ind_field"] == 1) {
					$SQL_UPDATE .= " $myfield='".$$ind_field."'";					
				} elseif ($type["$ind_field"] == 2) {
					$SQL_UPDATE .= " $myfield = $myfield +'".$$ind_field."'";
				} else {
					$SQL_UPDATE .= " $myfield = $myfield -'".$$ind_field."'";
				}				
			}
		}
		$loop_pass++;
	}
	
	$SQL_UPDATE = "UPDATE $HD_Form->FG_TABLE_NAME SET $SQL_UPDATE";
	if (strlen($HD_Form->FG_TABLE_CLAUSE)>1) {
		$SQL_UPDATE .= ' WHERE ';
		$SQL_UPDATE .= $HD_Form->FG_TABLE_CLAUSE;
	}
	$update_msg_error = '<center><font color="red"><b>'.gettext('Could not perform the batch update!').'</b></font></center>';
	
	if (!$HD_Form -> DBHandle -> Execute("begin")){
		$update_msg =$update_msg_error;
	} else {
		
		if (isset($check['upd_credit']) && (strlen(trim($upd_credit))>0) && ($upd_refill_type>=0)) {
			if (! $res = $HD_Form -> DBHandle -> Execute($SQL_REFILL)) {
				$update_msg.= '<br/><center><font color="red"><b>'.gettext('Could not perform refill log for the batch update!').'</b></font></center>';
			}
		}
		if(!$HD_Form -> DBHandle -> Execute($SQL_UPDATE)){
			$update_msg =$update_msg_error;
		}
		if (! $res = $HD_Form -> DBHandle -> Execute("comit")) {
			$update_msg = '<center><font color="green"><b>'.gettext('The batch update has been successfully perform!').'</b></font></center>';
		}
	
	};
}



if ($id!="" || !is_null($id)) {	
	$HD_Form -> FG_EDITION_CLAUSE = str_replace("%id", "$id", $HD_Form -> FG_EDITION_CLAUSE);	
}


if (!isset($form_action))  $form_action="list"; //ask-add
if (!isset($action)) $action = $form_action;


$list = $HD_Form -> perform_action($form_action);


// #### HEADER SECTION
$smarty->display('main.tpl');



if ($popup_select) {
?>
<SCRIPT LANGUAGE="javascript">
<!-- Begin
function sendValue(selvalue){
	window.opener.document.<?php echo $popup_formname ?>.<?php echo $popup_fieldname ?>.value = selvalue;
	window.close();
}
// End -->
</script>
<?php
}


// #### HELP SECTION
if ($form_action=='list' && !($popup_select>=1)) {
	echo $CC_help_list_customer;
?>
<script language="JavaScript" src="javascript/card.js"></script>

<div class="toggle_hide2show">
<center><a href="#" target="_self" class="toggle_menu"><img class="toggle_hide2show" src="<?php echo KICON_PATH; ?>/toggle_hide2show.png" onmouseover="this.style.cursor='hand';" HEIGHT="16"> <font class="fontstyle_002"><?php echo gettext("SEARCH CARDS");?> </font></a></center>
	<div class="tohide" style="display:none;">

<?php
// #### CREATE SEARCH FORM
if ($form_action == "list") {
	$HD_Form -> create_search_form();
}
?>

	</div>
</div>

<?php

/********************************* BATCH UPDATE ***********************************/
if ( $form_action == "list" && (!($popup_select>=1)) ) {
		
	$instance_table_tariff = new Table("cc_tariffgroup", "id, tariffgroupname");
	$FG_TABLE_CLAUSE = "";
	$list_tariff = $instance_table_tariff -> Get_list ($HD_Form -> DBHandle, $FG_TABLE_CLAUSE, "tariffgroupname", "ASC", null, null, null, null);
	$nb_tariff = count($list_tariff);

	$instance_table_group=  new Table("cc_card_group"," id, name ");
	$list_group = $instance_table_group  -> Get_list ($HD_Form ->DBHandle, $FG_TABLE_CLAUSE, "name", "ASC", null, null, null, null);
	
	$instance_table_agent=  new Table("cc_agent"," id, login ");
	$list_agent = $instance_table_agent  -> Get_list ($HD_Form ->DBHandle, $FG_TABLE_CLAUSE, "login", "ASC", null, null, null, null);

	$list_refill_type=Constants::getRefillType_List();
	$list_refill_type["-1"]=array("NO REFILL","-1");
	
?>
<!-- ** ** ** ** ** Part for the Update ** ** ** ** ** -->
<div class="toggle_hide2show">
<center><a href="#" target="_self" class="toggle_menu"><img class="toggle_hide2show" src="<?php echo KICON_PATH; ?>/toggle_hide2show.png" onmouseover="this.style.cursor='hand';" HEIGHT="16"> <font class="fontstyle_002"><?php echo gettext("BATCH UPDATE");?> </font></a></center>
	<div class="tohide" style="display:none;">

<center>
<b>&nbsp;<?php echo $HD_Form -> FG_NB_RECORD ?> <?php echo gettext("cards selected!"); ?>&nbsp;<?php echo gettext("Use the options below to batch update the selected cards.");?></b>
	   <table align="center" border="0" width="65%"  cellspacing="1" cellpadding="2">
        <tbody>
		<form name="updateForm" action="<?php echo $_SERVER['PHP_SELF']?>" method="post">
		<INPUT type="hidden" name="batchupdate" value="1">
		<tr>		
          <td align="left" class="bgcolor_001" >
		  		<input name="check[upd_inuse]" type="checkbox" <?php if ($check["upd_inuse"]=="on") echo "checked"?>>
		  </td>
		  <td align="left"  class="bgcolor_001">
				1)&nbsp;<?php echo gettext("In use"); ?>&nbsp;: 
				<select NAME="upd_inuse" size="1" class="form_input_select">
				<?php foreach($inuse_list as $key => $cur_value) { ?>
					<option value='<?php echo $cur_value[1] ?>'  <?php if ($upd_inuse==$cur_value[1]) echo 'selected="selected"'?>><?php echo $cur_value[0] ?></option>
				<?php } ?>			
		    </select>
		  </td>
		</tr>
		<tr>		
          <td align="left"  class="bgcolor_001">
		  	<input name="check[upd_status]" type="checkbox" <?php if ($check["upd_status"]=="on") echo "checked"?> >
		  </td>
		  <td align="left" class="bgcolor_001">
			  	2)&nbsp;<?php echo gettext("Status");?>&nbsp;:
				<select NAME="upd_status" size="1" class="form_input_select">
				<?php foreach ($cardstatus_list as $key => $cur_value) { ?>
					<option value='<?php echo $cur_value[1] ?>' <?php if ($upd_status==$cur_value[1]) echo 'selected="selected"'?>><?php echo $cur_value[0] ?></option>                        
				<?php } ?>
				</select><br/>
		  </td>
		</tr>

		<tr>		
          <td align="left" class="bgcolor_001">
		  		<input name="check[upd_language]" type="checkbox" <?php if ($check["upd_language"]=="on") echo "checked"?>>
		  </td>
		  <td align="left"  class="bgcolor_001">
				3)&nbsp;<?php echo gettext("Language");?>&nbsp;: 
				<select NAME="upd_language" size="1" class="form_input_select">
				<?php foreach($language_list as $key => $cur_value) { ?>
					<option value='<?php echo $cur_value[1] ?>' <?php if ($upd_language==$cur_value[1]) echo 'selected="selected"'?>><?php echo $cur_value[0] ?></option>
				<?php } ?>
		    </select>
		  </td>
		</tr>
		<tr>		
          <td align="left"  class="bgcolor_001">
		  	<input name="check[upd_tariff]" type="checkbox" <?php if ($check["upd_tariff"]=="on") echo "checked"?> >
		  </td>
		  <td align="left" class="bgcolor_001">
			  	4)&nbsp;<?php echo gettext("Tariff");?>&nbsp;:
				<select NAME="upd_tariff" size="1" class="form_input_select">
					<?php foreach ($list_tariff as $recordset) { ?>
						<option class=input value='<?php echo $recordset[0]?>'  <?php if ($upd_tariff==$recordset[0]) echo 'selected="selected"'?>><?php echo $recordset[1]?></option>                        
					<?php } ?>
				</select><br/>
		  </td>
		</tr>
		<tr>		
          <td align="left" class="bgcolor_001">
		  		<input name="check[upd_credit]" type="checkbox" <?php if ($check["upd_credit"]=="on") echo "checked"?>>
				<input name="mode[upd_credit]" type="hidden" value="2">
		  </td>
		  <td align="left"  class="bgcolor_001">	
			  	5)&nbsp;<?php echo gettext("Credit");?>&nbsp;:
					<input class="form_input_text" name="upd_credit" size="10" maxlength="10"  value="<?php if (isset($upd_credit)) echo $upd_credit; else echo '0';?>">
				<font class="version">
				<input type="radio" NAME="type[upd_credit]" value="1" <?php if((!isset($type["upd_credit"]))|| ($type["upd_credit"]==1) ){?>checked<?php }?>><?php echo gettext("Equals");?>
				<input type="radio" NAME="type[upd_credit]" value="2" <?php if($type["upd_credit"]==2){?>checked<?php }?>> <?php echo gettext("Add");?>
				<input type="radio" NAME="type[upd_credit]" value="3" <?php if($type["upd_credit"]==3){?>checked<?php }?>> <?php echo gettext("Subtract");?>
				</font><br>&nbsp;&nbsp;&nbsp;Refill:
					<select NAME="upd_refill_type" size="1" class="form_input_select">
                    <?php foreach ($list_refill_type as $recordset){ ?>
                        <option class=input value='<?php echo $recordset[1]?>'  <?php if ($upd_refill_type==$recordset[1]) echo 'selected="selected"'?>><?php echo $recordset[0]?></option>
                    <?php } ?>
                </select> Description <input class="form_input_text" name="upd_description"  size="20" maxlength="20"  value="<?php if (isset($upd_description)) echo $upd_description;?>"><br/>

		  </td>
		</tr>
		<tr>		
          <td align="left" class="bgcolor_001">
		  		<input name="check[upd_simultaccess]" type="checkbox" <?php if ($check["upd_simultaccess"]=="on") echo "checked"?>>
		  </td>
		  <td align="left" class="bgcolor_001">	
				6)&nbsp;<?php echo gettext("Access");?>&nbsp;: 
				<select NAME="upd_simultaccess" size="1" class="form_input_select">
					<option value='0'  <?php if ($upd_simultaccess==0) echo 'selected="selected"'?>><?php echo gettext("INDIVIDUAL ACCESS");?></option>
					<option value='1'  <?php if ($upd_simultaccess==1) echo 'selected="selected"'?>><?php echo gettext("SIMULTANEOUS ACCESS");?></option>
		    </select>
		  </td>
		</tr>
		<tr>		
          <td align="left" class="bgcolor_001">
		  		<input name="check[upd_currency]" type="checkbox" <?php if ($check["upd_currency"]=="on") echo "checked"?>>
		  </td>
		  <td align="left"  class="bgcolor_001">
				7)&nbsp;<?php echo gettext("Currency");?>&nbsp;:
				<select NAME="upd_currency" size="1" class="form_input_select">
				<?php 
					foreach($currencies_list as $key => $cur_value) {											
				?>
					<option value='<?php echo $key ?>'  <?php if ($upd_currency==$key) echo 'selected="selected"'?>><?php echo $cur_value[1].' ('.$cur_value[2].')' ?></option>
				<?php } ?>			
		    </select>
		  </td>
		</tr>
		<tr>		
          <td align="left"  class="bgcolor_001">
		  		<input name="check[upd_typepaid]" type="checkbox" <?php if ($check["upd_typepaid"]=="on") echo "checked"?>>
		  </td>
		  <td align="left" class="bgcolor_001">	
				8)&nbsp;<?php echo gettext("Card type");?>&nbsp;:
				<select NAME="upd_typepaid" size="1" class="form_input_select" >
					<option value='0'  <?php if ($upd_typepaid==0) echo 'selected="selected"'?>><?php echo gettext("PREPAID CARD");?></option>
					<option value='1'  <?php if ($upd_typepaid==1) echo 'selected="selected"'?>><?php echo gettext("POSTPAY CARD");?></option>
		    </select>
		  </td>
		</tr>
		<tr>		
          <td align="left" class="bgcolor_001">
		  		<input name="check[upd_creditlimit]" type="checkbox" <?php if ($check["upd_creditlimit"]=="on") echo "checked"?>>
				<input name="mode[upd_creditlimit]" type="hidden" value="2">
		  </td>
		  <td align="left"  class="bgcolor_001">
				9)&nbsp;<?php echo gettext("Credit limit");?>&nbsp;:
				 	<input class="form_input_text" name="upd_creditlimit" size="10" maxlength="10"  value="<?php if (isset($upd_creditlimit)) echo $upd_creditlimit; else echo '0';?>" >
				<font class="version">
				<input type="radio" NAME="type[upd_creditlimit]" value="1" <?php if((!isset($type[upd_creditlimit]))|| ($type[upd_creditlimit]==1) ){?>checked<?php }?>> <?php echo gettext("Equals");?>
				<input type="radio" NAME="type[upd_creditlimit]" value="2" <?php if($type[upd_creditlimit]==2){?>checked<?php }?>><?php echo gettext("Add");?>
				<input type="radio" NAME="type[upd_creditlimit]" value="3" <?php if($type[upd_creditlimit]==3){?>checked<?php }?>> <?php echo gettext("Subtract");?>
				</font>
		  </td>
		</tr>
		<tr>
          <td align="left" class="bgcolor_001">
		  		<input name="check[upd_enableexpire]" type="checkbox" <?php if ($check["upd_enableexpire"]=="on") echo "checked"?>>
		  </td>
		  <td align="left"  class="bgcolor_001">
				10)&nbsp;<?php echo gettext("Enable expire");?>&nbsp;: 
				<select name="upd_enableexpire" class="form_input_select" >
					<option value="0"  <?php if ($upd_enableexpire==0) echo 'selected="selected"'?>> <?php echo gettext("NO EXPIRY");?></option>
					<option value="1"  <?php if ($upd_enableexpire==1) echo 'selected="selected"'?>> <?php echo gettext("EXPIRE DATE");?></option>
					<option value="2"  <?php if ($upd_enableexpire==2) echo 'selected="selected"'?>> <?php echo gettext("EXPIRE DAYS SINCE FIRST USE");?></option>
					<option value="3"  <?php if ($upd_enableexpire==3) echo 'selected="selected"'?>> <?php echo gettext("EXPIRE DAYS SINCE CREATION");?></option>
				</select>
		  </td>
		</tr>
		<tr>		
          <td align="left" class="bgcolor_001">
		  		<input name="check[upd_expirationdate]" type="checkbox" <?php if ($check["upd_expirationdate"]=="on") echo "checked"?>>
		  </td>
		  <td align="left"  class="bgcolor_001">
				<?php 
					$begin_date = date("Y");
					$begin_date_plus = date("Y")+10;	
					$end_date = date("-m-d H:i:s");
					$comp_date = "value='".$begin_date.$end_date."'";
					$comp_date_plus = "value='".$begin_date_plus.$end_date."'";
				?>
				11)&nbsp;<?php echo gettext("Expiry date");?>&nbsp;:
				 <input class="form_input_text"  name="upd_expirationdate" size="20" maxlength="30" <?php echo $comp_date_plus; ?>> <font class="version"><?php echo gettext("(Format YYYY-MM-DD HH:MM:SS)");?></font>
		  </td>
		</tr>
		<tr>		
          <td align="left" class="bgcolor_001">
		  		<input name="check[upd_expiredays]" type="checkbox" <?php if ($check["upd_expiredays"]=="on") echo "checked"?>>
		  </td>
		  <td align="left"  class="bgcolor_001">
				12)&nbsp;<?php echo gettext("Expiration days");?>&nbsp;: 
				<input class="form_input_text"  name="upd_expiredays" size="10" maxlength="6" value="<?php if (isset($upd_expiredays)) echo $upd_expiredays; else echo '0';?>">
				<br/>
		</td>
		</tr>
		<tr>		
          <td align="left" class="bgcolor_001">
		  	<input name="check[upd_runservice]" type="checkbox" <?php if ($check["upd_runservice"]=="on") echo "checked"?>>
		  </td>
		  <td align="left"  class="bgcolor_001">
				 13)&nbsp;<?php echo gettext("Run service");?>&nbsp;: 	
				<font class="version">
				<input type="radio" NAME="type[upd_runservice]" value="1" <?php if((!isset($type[upd_runservice]))|| ($type[upd_runservice]=='1') ){?>checked<?php }?>>
				<?php echo gettext("Yes");?> <input type="radio" NAME="type[upd_runservice]" value="0" <?php if($type[upd_runservice]=='0'){?>checked<?php }?>><?php echo gettext("No");?>
				</font>
		  </td>
		</tr>

		<tr>
		 <td align="left"  class="bgcolor_001">
                <input name="check[upd_id_group]" type="checkbox" <?php if ($check["upd_id_group"]=="on") echo "checked"?> >
          </td>
          <td align="left" class="bgcolor_001">
                14)&nbsp;<?php echo gettext("Group this batch belongs to");?>&nbsp;:
                <select NAME="upd_id_group" size="1" class="form_input_select">
                        <?php
                         foreach ($list_group as $recordset){
                        ?>
                                <option class=input value='<?php echo $recordset[0]?>'  <?php if ($upd_id_group==$recordset[0]) echo 'selected="selected"'?>><?php echo $recordset[1]?></option>
                        <?php } ?>
                </select><br/>
          </td>
        </tr>

		<tr>
         <td align="left"  class="bgcolor_001">
                <input name="check[upd_id_agent]" type="checkbox" <?php if ($check["upd_id_agent"]=="on") echo "checked"?> >
          </td>
          <td align="left" class="bgcolor_001">
                15)&nbsp;<?php echo gettext("Agent this batch belongs to");?>&nbsp;:
                <select NAME="upd_id_agent" size="1" class="form_input_select">
                        <?php
                         foreach ($list_agent as $recordset) {
                        ?>
                                <option class=input value='<?php echo $recordset[0]?>'  <?php if ($upd_id_agent==$recordset[0]) echo 'selected="selected"'?>><?php echo $recordset[1]?></option>
                        <?php } ?>
                </select><br/>
          </td>
        </tr>
		<tr>
         <td align="left"  class="bgcolor_001">
                <input name="check[upd_discount]" type="checkbox" <?php if ($check["upd_discount"]=="on") echo "checked"?> >
          </td>
          <td align="left" class="bgcolor_001">
                16)&nbsp;<?php echo gettext("Set discount to");?>&nbsp;:
                <select NAME="upd_discount" size="1" class="form_input_select">
					<option class=input value="0" ><?php echo gettext("NO DISCOUNT");?></option> 
                    <?php for($i=1;$i<99;$i++){ ?>
              			<option class=input value='<?php echo $i;?>'  <?php if ($upd_discount==$i) echo 'selected="selected"';echo '>'. $i; ?>%</option>
                    <?php } ?>
                </select><br/>
          </td>
        </tr>
		
		<tr>		
			<td align="right" class="bgcolor_001"></td>
		 	<td align="right"  class="bgcolor_001">
				<input class="form_input_button"  value=" <?php echo gettext("BATCH UPDATE CARD");?>  " type="submit">
        	</td>
		</tr>
		</form>
		</table>
</center>
	</div>
</div>
<!-- ** ** ** ** ** Part for the Update ** ** ** ** ** -->
<?php
} // END if ($form_action == "list")
?>


<?php  if ( isset($_SESSION["is_sip_iax_change"]) && $_SESSION["is_sip_iax_change"]){ ?>
	  <table width="<?php echo $HD_Form -> FG_HTML_TABLE_WIDTH?>" border="0" align="center" cellpadding="0" cellspacing="0" >	  
		<TR><TD style="border-bottom: medium dotted #ED2525" align="center"> <?php echo gettext("Changes detected on SIP/IAX Friends");?></TD></TR>
		<TR><FORM NAME="sipfriend">
            <td height="31" class="bgcolor_013" style="padding-left: 5px; padding-right: 3px;" align="center">			
			<font color=white><b>
			<?php  if ( isset($_SESSION["is_sip_changed"]) && $_SESSION["is_sip_changed"] ){ ?>
			SIP : <input class="form_input_button"  TYPE="button" VALUE="<?php echo gettext("GENERATE ADDITIONAL_A2BILLING_SIP.CONF");?>"
			onClick="self.location.href='./CC_generate_friend_file.php?friend=sipfriend';">
			<?php }
			if ( isset($_SESSION["is_iax_changed"]) && $_SESSION["is_iax_changed"] ){ ?>
			IAX : <input class="form_input_button"  TYPE="button" VALUE="<?php echo gettext("GENERATE ADDITIONAL_A2BILLING_IAX.CONF");?>"
			onClick="self.location.href='./CC_generate_friend_file.php?friend=iaxfriend';">
			<?php } ?>	
			</b></font></td></FORM>
        </TR>
</table>
<?php  } // endif is_sip_iax_change

}elseif (!($popup_select>=1)) echo $CC_help_create_customer;


if (isset($update_msg) && strlen($update_msg)>0) echo $update_msg; 



// #### TOP SECTION PAGE
$HD_Form -> create_toppage ($form_action);
if (!$popup_select && $form_action == "ask-add"){
?>
<table width="70%" align="center" cellpadding="2" cellspacing="0">
	<script language="javascript">
	function submitform()
	{
		document.cardform.submit();
	}
	</script>
	<form action="A2B_entity_card.php?form_action=ask-add&section=1" method="post" name="cardform">
	<tr>
		<td class="viewhandler_filter_td1">
		<span>		
			
			<font class="viewhandler_filter_on"><?php echo gettext("Change the Card Number Length")?> :</font>
			<select name="cardnumberlenght_list" size="1" class="form_input_select" onChange="submitform()">
			<?php 
			//for($i=CARDNUMBER_LENGTH_MIN;$i<=CARDNUMBER_LENGTH_MAX;$i++) {
			
			foreach ($A2B -> cardnumber_range as $value){
			?>
				<option value='<?php echo $value ?>' 
				<?php if ($value == $cardnumberlenght_list) echo "selected";
				?>> <?php echo $value." ".gettext("Digits");?> </option>
				
			<?php
			}
			?>						
			</select>
		</span>
		</td>	
	</tr>
	</form>
</table>

<?php
}

if (strlen($_GET["menu"])>0) $_SESSION["menu"] = $_GET["menu"];

if ($form_action=='ask-edit') {
	$inst_table = new Table("cc_card", "useralias, uipass");
	$FG_TABLE_CLAUSE = "id = $id";
	$list_card_info = $inst_table -> Get_list ($HD_Form -> DBHandle, $FG_TABLE_CLAUSE);			
	$username = $list_card_info[0][0];
	$password = base64_encode($list_card_info[0][1]);
	$link = CUSTOMER_UI_URL;
	echo "<div align=\"right\" style=\"padding-right:20px;\"><a href=\"$link?username=$username&password=$password\" target=\"_blank\">GO TO CUSTOMER ACCOUNT</a></div>";
}

$HD_Form -> create_form ($form_action, $list, $id=null) ;



// Code for the Export Functionality
$_SESSION[$HD_Form->FG_EXPORT_SESSION_VAR]= "SELECT ".$HD_Form -> FG_EXPORT_FIELD_LIST." FROM $HD_Form->FG_TABLE_NAME";

if (strlen($HD_Form->FG_TABLE_CLAUSE)>1) 
	$_SESSION[$HD_Form->FG_EXPORT_SESSION_VAR] .= " WHERE $HD_Form->FG_TABLE_CLAUSE ";

if (!is_null ($HD_Form->FG_ORDER) && ($HD_Form->FG_ORDER!='') && !is_null ($HD_Form->FG_SENS) && ($HD_Form->FG_SENS!='')) 
	$_SESSION[$HD_Form->FG_EXPORT_SESSION_VAR].= " ORDER BY $HD_Form->FG_ORDER $HD_Form->FG_SENS";



// #### FOOTER SECTION
if (!($popup_select>=1))
	$smarty->display('footer.tpl');

