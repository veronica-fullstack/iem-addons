<script src="includes/js/jquery/ui.js"></script>

<script>

	var PAGE = {
		init: function() {
			
			var frm = document.frmReEngagementEdit;



			$(frm).submit(function(event) {
				return PAGE.submit();
			});

			$('.cancelButton').click(function() {
				PAGE.cancel();
			});

			$('#hrefPreview').click(function() {
				var campaigns = PAGE.getSelectedCampaigns();
				if (!campaigns.length) {
					alert("{$lang.Addon_reengagement_PreviewNoneSelected}");
					$('#reengagement_list').focus();
					return false;
				}
				$(campaigns).each(function(i, e) {
					window.open('index.php?Page=Newsletters&Action=Preview&id=' + e, 'campaign' + e);
				});
				this.blur();
				return false;
			});
			
			$('#reengagePreview').click(function() {
				if (!frm.reengage_typeof.value.length) {
					alert("{$lang.Addon_reengagement_RSSNoneAdded}");
					$('#reengage_typeof').focus();
					return false;
				}
				window.open('index.php?Page=Addons&Addon=reengagement&Action=preview&id=' + frm.reengage_typeof.value);
				this.blur();
				return false;
			});
			
			$('#duration_type').change(function() {
				if (frm.duration_type.value > 1) {
					$('.Send_on_options_once').hide();
					$('.Send_on_options_hourly').hide();
					$('.Send_on_options_daily').hide();
					$('.Send_on_options_weekly').hide();
					
					if(frm.duration_type.value == 2){
						$('.Send_on_options_once').show();
						$('#duration_once_hr').focus();
					}else if(frm.duration_type.value == 3){
						$('.Send_on_options_hourly').show();
						$('#duration_hourly_hr').focus();
					}else if(frm.duration_type.value == 4){
						$('.Send_on_options_daily').show();
						$('#duration_daily_hr').focus();
					}else if(frm.duration_type.value == 5){
						$('.Send_on_options_weekly').show();
						$('#duration_weekly_hr').focus();
					}
					
					$('.Send_on_options_main').show();
					$('.Send_on_options').show();
					return true;
				}
				$('.Send_on_options_main').hide();
				this.blur();
				return false;
			});
		},

		getSelectedCampaigns: function() {
			var el = document.frmReEngagementEdit['reengagement_list'];
			var selected = [];

			for(var i = 0, j = el.options.length; i < j; ++i) {
				if(el.options[i].selected) {
					selected.push(el.options[i].value);
				}
			}
			return selected;
		},

		submit: function() {
			var frm = document.frmReEngagementEdit;
			var data = {
				reengageName: encodeURIComponent($.trim(frm.reengagename.value)),
				campaigns: this.getSelectedCampaigns(),
				reengage_typeof: encodeURIComponent($.trim(frm.reengage_typeof.value)),
				max_numberofdays: encodeURIComponent($.trim(frm.max_numberofdays.value))
			};

			if (data.reengageName == '') {
				alert('{$lang.Addon_reengagement_form_EnterName_Alert}');
				frm.reengagename.focus();
				return false;
			}

			if (data.campaigns.length < 1) {
				alert('{$lang.Addon_reengagement_form_SelectCampaigns_Alert}');
				return false;
			}

			if (data.max_numberofdays.value <= 0) {
				alert('{$lang.Addon_reengagement_form_MaxDays_Alert}');
				return false;
			}
			// Convert the days to hours in the form only once everything validates.
			return true;
		},

		cancel: function() {
			{if $FormType == 'create'}
				var confmsg = '{$lang.Addon_reengagement_form_Cancel_Create}';
			{elseif $FormType == 'edit'}
				var confmsg = '{$lang.Addon_reengagement_form_Cancel_Edit}';
			{/if}

			if (confirm(confmsg)) {
				window.location.href = "{$BaseAdminUrl}";
			}
		}
	};

	$(function() {
		PAGE.init();
	});
</script>

<form name="frmReEngagementEdit" id="frmReEngagementEdit" method="post" action="{$AdminUrl}&Action={if $FormType == 'create'}Create{elseif $FormType == 'edit'}Edit&id={$reengageid}{/if}">
	<input type="hidden" id="action" name="action" value="{$action}" />
	<table cellspacing="0" cellpadding="0" width="100%" align="center">
		<tr>
			<td class="Heading1">
				{if $FormType == 'create'}
					{$lang.Addon_reengagement_Form_CreateHeading}
				{elseif $FormType == 'edit'}
					{$lang.Addon_reengagement_Form_EditHeading}
				{/if}
			</td>
		</tr>
		<tr>
			<td class="body pageinfo">
				<p>
					{$lang.Addon_reengagement_Form_Intro}
				</p>
			</td>
		</tr>
		<tr>
			<td>
				{$FlashMessages}
			</td>
		</tr>
		<tr>
			<td>
				<?php /*{if $ShowSend}<input class="FormButton submitButton" type="submit" name="Submit_Send" value="{$lang.Addon_reengagement_SaveSend}" style="width:100px" />{/if}*/ ?>
				<input class="FormButton submitButton" type="submit" name="Submit_Exit" value="{$lang.Addon_reengagement_SaveExit}" style="width:100px" />
				<input class="FormButton cancelButton" type="button" value="{$lang.Addon_reengagement_Cancel}" />
				<br />&nbsp;
				<table border="0" cellspacing="0" cellpadding="0" class="Panel">
					<tr>
						<td colspan="3" class="Heading2">
							&nbsp;&nbsp;{$lang.Addon_reengagement_Form_Settings}
						</td>
					</tr>
					<tr>
						<td class="FieldLabel" width="200" nowrap="nowrap">
							<img src="images/blank.gif" width="200" height="1" /><br />
							{template="required"}
							{$lang.Addon_reengagement_Form_CampaignName}:&nbsp;
						</td>
						<td width="85%">
							<input type="text" id="reengagename" name="reengagename" class="Field250 form_text" value="{$reengagename}" style="width:446px;" /> <br />
							<span class="aside">{$lang.Addon_reengagement_Form_CampaignName_Aside}</span>
							<br /><br />
						</td>
					</tr>
					<tr>
						<td class="FieldLabel">
							{template="required"}
							{$lang.Addon_reengagement_Form_ChooseList}:&nbsp;
						</td>
						<td>
							<select name="reengagement_list[]" id="record_triggeractions_addlist_listid" multiple="multiple" class="ISSelectReplacement ISSelectSearch">
                                {foreach from=$availableLists item=each}
                                    {if $each.listid != $record.listid}
                                        <option value="{$each.listid}"
                                            {if (is_array($reengagement_list) && in_array($each.listid, $reengagement_list)) || ($reengagement_list == $each.listid)}
                                                selected="selected"
                                            {/if}>
                                            {$each.name|htmlspecialchars, ENT_QUOTES, SENDSTUDIO_CHARSET}
                                        </option>
                                    {/if}
                                {/foreach}
                            </select>
							&nbsp;&nbsp;&nbsp;{$lnghlp.Addon_reengagement_Form_AddCampaigns}
						</td>
					</tr>
					<tr>
						<td class="FieldLabel">
							{template="required"}
							{$lang.Addon_reengagement_TypeOfEngage}:
						</td>
						<td style="padding-top:5px">
							<input type="radio" id="reengage_typeof" name="reengage_typeof" value="Not Click" {if $reengage_typeof == 'Not Click'} checked="checked" {/if} />Not Click
                            <input type="radio" id="reengage_typeof" name="reengage_typeof" value="Not Open" {if $reengage_typeof == 'Not Open'} checked="checked" {/if} />Not Open
                            <input type="radio" id="reengage_typeof" name="reengage_typeof" value="Both" {if $reengage_typeof == 'Both' || $reengage_typeof == ''} checked="checked" {/if} />Not Open/Click
						</td>
					</tr>
                    <tr>
						<td class="FieldLabel">
							{template="required"}
							{$lang.Addon_reengagement_MaxDays}:
						</td>
						<td style="padding-top:5px">
							<input id="max_numberofdays" name="max_numberofdays" value="{$max_numberofdays}" />
						</td>
					</tr>

					<tr>
						<td class="FieldLabel">
							{template="required"}
							{$lang.Addon_reengagement_isRemoveList}:
						</td>
						<td style="padding-top:5px">
							<input type="checkbox" id="is_removemail" name="is_removemail" {if $is_removemail == "on"} checked="checked" {/if} />
						</td>
					</tr>
                    
                    <tr>
						<td class="FieldLabel">
							{template="required"}
							{$lang.Addon_reengagement_transferOnList}:
						</td>
						<td style="padding-top:5px">
                        <?php /*
							<select name="contactlist[]" id="record_triggeractions_addlist_listid" class="ISSelectSearch">
                                {foreach from=$availableLists item=each}
                                    {if $each.listid != $record.listid}
                                        <option value="{$each.listid}"
                                            {if (is_array($contactlist) && in_array($each.listid, $contactlist)) || ($contactlist == $each.listid)}
                                                selected="selected"
                                            {/if}>
                                            {$each.name|htmlspecialchars, ENT_QUOTES, SENDSTUDIO_CHARSET}
                                        </option>
                                    {/if}
                                {/foreach}
                            </select>
                            */
                        ?>
                        <select name="contactlist[]" id="secondlist" multiple="multiple" class="ISSelectReplacement ISSelectSearch">
                                {foreach from=$availableLists item=each}
                                    {if $each.listid != $record.listid}
                                        <option value="{$each.listid}"
                                            {if (is_array($contactlist) && in_array($each.listid, $contactlist)) || ($reengagement_list == $each.listid)}
                                                selected="selected"
                                            {/if}>
                                            {$each.name|htmlspecialchars, ENT_QUOTES, SENDSTUDIO_CHARSET}
                                        </option>
                                    {/if}
                                {/foreach}
                            </select>
						</td>
					</tr>
                                        
                    <tr>
						<td class="FieldLabel">
							{template="required"}
							{$lang.Addon_reengagement_SendOn}:
						</td>
						<td style="padding-top:5px">
							<select name="duration_type" id="duration_type" class="ISSelectSearch" style="height:20px;">
                            	<option value="1" {if $duration_type == 1}selected="selected"{/if}>Save Draft</option>
                                <option value="2" {if $duration_type == 2}selected="selected"{/if}>Once</option>
                                <option value="3" {if $duration_type == 3}selected="selected"{/if}>Hourly</option>
                                <option value="4" {if $duration_type == 4}selected="selected"{/if}>Daily</option>
                                <option value="5" {if $duration_type == 5}selected="selected"{/if}>Weekly</option>
                                <option value="6" {if $duration_type == 6}selected="selected"{/if}>Monthly</option>
                            </select>
                        </td>
					</tr>
                    <tr>
						<td colspan="2">
                        	<div class="Send_on_options_main" {if intval($duration_type) <= 1}style="display:none;"{/if}>
                        	<div class="Send_on_options_once" {if intval($duration_type) != 2}style="display:none;"{/if}>
                            <table cellspacing="0" cellpadding="0" width="100%" align="center">
                                <tr>
                                    <td class="FieldLabel">
                                        {template="required"}
                                        {$lang.Addon_reengagement_OnDateTime}:
                                    </td>
                                    <td style="padding-top:5px">
                                    	<input type="hidden" name="duration_once" id="duration_once"  />
                                        <input id="duration_once_date" name="duration_once_date" value="<?php if(!$tpl->Get('duration_once')){echo date("Y-m-d");}else{echo date("Y-m-d", strtotime($tpl->Get('duration_once')));} ?>" width="75" /> {Ex.: 2010-10-24}
                                        <select name="duration_once_hr" id="duration_once_hr" style="width:50px;">
                                            <?php for($hours = 1;$hours <= 12; $hours++){ ?>
                                                <?php $newHours = "00"; if(strlen($hours) > 1){ $newHours = $hours;} else { $newHours = "0".$hours; } ?>
                                                <option value="<?php echo $newHours; ?>" <?php if(date("h", strtotime($tpl->Get('duration_once'))) == $newHours) { ?> selected="selected" <?php } ?>><?php echo $newHours; ?></option>
                                            <?php } ?>
                                        </select>
                                        <select name="duration_once_minutes" id="duration_once_minutes" style="width:50px;">
                                        <?php for($minutes = 0;$minutes < 60; $minutes++){ ?>
                                        	<?php $newMinute = "00"; if(strlen($minutes) > 1){ $newMinute = $minutes;} else { $newMinute = "0".$minutes; } ?>
                                        	<option value="<?php echo $newMinute; ?>" <?php if(date("i", strtotime($tpl->Get('duration_once'))) == $newMinute) { ?> selected="selected" <?php } ?>><?php echo $newMinute; ?></option>
                                        <?php } ?>
                                        </select>
                                        <select name="duration_once_ampm" id="duration_once_ampm" style="width:50px;">
                                        	<option value="AM" <?php if(date("A", strtotime($tpl->Get('duration_once'))) == "AM") { ?> selected="selected" <?php } ?>>AM</option>
                                            <option value="PM" <?php if(date("A", strtotime($tpl->Get('duration_once'))) == "PM") { ?> selected="selected" <?php } ?>>PM</option>
                                        </select>
                                        {Ex.: 10:10 AM}
                                    </td>
                                </tr>
                            </table>
                            </div>
                        	<div class="Send_on_options_hourly" {if intval($duration_type) != 3}style="display:none;"{/if}>
                            <table cellspacing="0" cellpadding="0" width="100%" align="center">
                                <tr>
                                    <td class="FieldLabel">
                                        {template="required"}
                                        {$lang.Addon_reengagement_EveryHours}:
                                    </td>
                                    <td style="padding-top:5px">
                                        <select id="duration_hourly" name="duration_hourly">
                                        	<option value="1" {if $duration_hourly == 1} selected="selected" {/if}>Every Hour</option>
                                            <?php for($hours=2;$hours<=12;$hours++){ ?>
                                            <option value="<?php echo $hours; ?>" <?php if($tpl->Get('duration_hourly') == $hours) { ?> selected="selected" <?php } ?>>Every <?php echo $hours; ?> Hours</option>
                                            <?php } ?>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                            </div>
                            <div class="Send_on_options_daily" {if intval($duration_type) != 4}style="display:none;"{/if}>
                            <table cellspacing="0" cellpadding="0" width="100%" align="center">
                                <tr>
                                    <td class="FieldLabel">
                                        {template="required"}
                                        {$lang.Addon_reengagement_OnDateTime}:
                                    </td>
                                    <td style="padding-top:5px">
                                    	<select name="duration_daily_hr" id="duration_daily_hr" style="width:50px;">
                                            <?php for($hours = 1;$hours <= 12; $hours++){ ?>
                                                <?php $newHours = "00"; if(strlen($hours) > 1){ $newHours = $hours;} else { $newHours = "0".$hours; } ?>
                                                <option value="<?php echo $newHours; ?>" <?php if(date("h", strtotime($tpl->Get('duration_daily'))) == $newHours) { ?> selected="selected" <?php } ?>><?php echo $newHours; ?></option>
                                            <?php } ?>
                                        </select>
                                        <select name="duration_daily_minutes" id="duration_daily_minutes" style="width:50px;">
                                        <?php for($minutes = 0;$minutes < 60; $minutes++){ ?>
                                        	<?php $newMinute = "00"; if(strlen($minutes) > 1){ $newMinute = $minutes;} else { $newMinute = "0".$minutes; } ?>
                                        	<option value="<?php echo $newMinute; ?>" <?php if(date("i", strtotime($tpl->Get('duration_daily'))) == $newMinute) { ?> selected="selected" <?php } ?>><?php echo $newMinute; ?></option>
                                        <?php } ?>
                                        </select>
                                        <select name="duration_daily_ampm" id="duration_daily_ampm" style="width:50px;">
                                        	<option value="AM" <?php if(date("A", strtotime($tpl->Get('duration_daily'))) == "AM") { ?> selected="selected" <?php } ?>>AM</option>
                                            <option value="PM" <?php if(date("A", strtotime($tpl->Get('duration_daily'))) == "PM") { ?> selected="selected" <?php } ?>>PM</option>
                                        </select>
                                    	
                                        <input id="duration_daily" type="hidden" name="duration_daily" value="{$duration_daily}" /> {Ex.: 10:20 PM}
                                    </td>
                                </tr>
                            </table>
                            </div>
                            <div class="Send_on_options_weekly" {if intval($duration_type) != 5}style="display:none;"{/if}>
                            <table cellspacing="0" cellpadding="0" width="100%" align="center">
                                <tr>
                                    <td class="FieldLabel">
                                        {template="required"}
                                        {$lang.Addon_reengagement_WeekDays}:
                                    </td>
                                    <td style="padding-top:5px">
                                    
                                        <select id="duration_weekly" name="duration_weekly[]" multiple="multiple" class="ISSelectReplacement">
                                        <?php $strTime = strtotime(date("2014-07-13")); for($weeks=1;$weeks<=7;$weeks++){ $strTime += 86400; ?>
                                        	<option value="<?php echo $weeks; ?>" <?php if((is_array($tpl->Get('duration_weekly')) && in_array($weeks, $tpl->Get('duration_weekly'))) || $weeks == $tpl->Get('duration_weekly')) { ?> selected="selected" <?php } ?>>Every <?php echo date("l", $strTime); ?></option>
                                        <?php } ?>
                                        </select>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <td class="FieldLabel">
                                        {template="required"}
                                        {$lang.Addon_reengagement_HoursOfDay}:
                                    </td>
                                    <td style="padding-top:5px">
                                    	<select name="duration_weekly_hr" id="duration_weekly_hr" style="width:50px;">
                                            <?php for($hours = 1;$hours <= 12; $hours++){ ?>
                                                <?php $newHours = "00"; if(strlen($hours) > 1){ $newHours = $hours;} else { $newHours = "0".$hours; } ?>
                                                <option value="<?php echo $newHours; ?>" <?php if(date("h", strtotime($tpl->Get('duration_weekly_time'))) == $newHours) { ?> selected="selected" <?php } ?>><?php echo $newHours; ?></option>
                                            <?php } ?>
                                        </select>
                                        <select name="duration_weekly_minutes" id="duration_weekly_minutes" style="width:50px;">
                                        <?php for($minutes = 0;$minutes < 60; $minutes++){ ?>
                                        	<?php $newMinute = "00"; if(strlen($minutes) > 1){ $newMinute = $minutes;} else { $newMinute = "0".$minutes; } ?>
                                        	<option value="<?php echo $newMinute; ?>" <?php if(date("i", strtotime($tpl->Get('duration_weekly_time'))) == $newMinute) { ?> selected="selected" <?php } ?>><?php echo $newMinute; ?></option>
                                        <?php } ?>
                                        </select>
                                        <select name="duration_weekly_ampm" id="duration_weekly_ampm" style="width:50px;">
                                        	<option value="AM" <?php if(date("A", strtotime($tpl->Get('duration_weekly_time'))) == "AM") { ?> selected="selected" <?php } ?>>AM</option>
                                            <option value="PM" <?php if(date("A", strtotime($tpl->Get('duration_weekly_time'))) == "PM") { ?> selected="selected" <?php } ?>>PM</option>
                                        </select>
                                    	
                                        <input type="hidden" id="duration_weekly_time" name="duration_weekly_time" value="{$duration_weekly_time}" /> {Ex.: 10:20 AM}
                                    </td>
                                </tr>
                             </table>
                             </div>   
                            </div>
						</td>
					</tr>
				</table>
				<table width="100%" cellspacing="0" cellpadding="2" border="0" class="PanelPlain">
					<tr>
						<td class="FieldLabel">&nbsp;</td>
						<td valign="top" height="30">
							<?php /*{if $ShowSend}<input class="FormButton submitButton" type="submit" name="Submit_Send" value="{$lang.Addon_reengagement_SaveSend}" style="width:100px" />{/if}*/ ?>
							<input class="FormButton submitButton" type="submit" name="Submit_Exit" value="{$lang.Addon_reengagement_SaveExit}" style="width:100px" />
							<input class="FormButton cancelButton" type="button" value="{$lang.Addon_reengagement_Cancel}" />
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</form>
