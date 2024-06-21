<script>
	var PAGE = {
		init: function() {
				Application.Ui.CheckboxSelection(
					'table#ReengagementManageList',
					'input.UICheckboxToggleSelector',
					'input.UICheckboxToggleRows'
				);

			$('#DeleteReEngagementButton').click(function() {
				PAGE.deleteSelected();
			});

		},

		deleteSelected: function() {
			var selected = 	$('.reEngagementSelection').filter(function() { return this.checked; });

			if (selected.length < 1) {
				alert("{$lang.Addon_reengagement_Delete_SelectFirst}");
				return false;
			}

			if (!confirm("{$lang.Addon_reengagement_Delete_ConfirmMessage}")) {
				return;
			}

			var selectedIds = [];
			for(var i = 0, j = selected.length; i < j; ++i) {
				selectedIds.push(selected[i].value);
			}

			Application.Util.submitPost('{$AdminUrl}&Action=Delete', {reengageids: selectedIds});
		}
	};

	$(function() {
		PAGE.init();
	});

	function DelReEngagement(id, status)
	{
		if (id < 1) {
			return false;
		}

		if (status == 'i' || status == 'r') {
			alert('{$lang.Addon_reengagement_Manage_Delete_Disabled_Alert}');
			return false;
		}

		if (!confirm('{$lang.Addon_reengagement_DeleteOne_Confirm}')) {
			return false;
		}

		Application.Util.submitPost('{$AdminUrl}&Action=Delete', {reengageid: id});
		return true;
	}
</script>
<table width="100%" border="0">
	<tr>
		<td class="Heading1" colspan="2">{$lang.Addon_reengagement_Heading}</td>
	</tr>
	<tr>
		<td class="body pageinfo" colspan="2"><p>{$lang.Addon_reengagement_Intro}</p></td>
	</tr>
	<tr>
		<td colspan="2">
			{$FlashMessages}
		</td>
	</tr>
	<tr>
		<td class="body" colspan="2">
			{$ReEngagement_Create_Button}
			{if $ShowDeleteButton}
				<input class="SmallButton" type="button" style="width: 150px;" value="{$lang.Addon_reengagement_DeleteButton}" name="DeleteReEngagementButton" id="DeleteReEngagementButton"/>
			{/if}
		</td>
	</tr>
	<tr>
		<td valign="bottom">&nbsp;
			
		</td>
		<td align="right">
			<div align="right">
				{$Paging}
			</div>
		</td>
	</tr>
</table>
<form name="reengagementlist" id="reengagementlist">
<table class="Text" width="100%" cellspacing="0" cellpadding="0" border="0" id="ReengagementManageList">
	<tr class="Heading3">
		<td width="1" align="center">
			<input class="UICheckboxToggleSelector" type="checkbox" name="toggle"/>
		</td>
		<td width="5">&nbsp;</td>
		<td width="15%" nowrap="nowrap">
			{$lang.Addon_reengagement_Manage_ReName}
			<a href="{$AdminUrl}&SortBy=reengagename&Direction=asc"><img src="{$ApplicationUrl}images/sortup.gif" border="0"/></a>
			<a href="{$AdminUrl}&SortBy=reengagename&Direction=desc"><img src="{$ApplicationUrl}images/sortdown.gif" border="0"/></a>
		</td>
		<td width="*" nowrap="nowrap">
			{$lang.Addon_reengagement_Manage_RefineList}
		</td>
        <td width="*" nowrap="nowrap">
			{$lang.Addon_reengagement_Manage_TypeOfEngage}
			<a href="{$AdminUrl}&SortBy=reengage_typeof&Direction=asc"><img src="{$ApplicationUrl}images/sortup.gif" border="0"/></a>
			<a href="{$AdminUrl}&SortBy=reengage_typeof&Direction=desc"><img src="{$ApplicationUrl}images/sortdown.gif" border="0"/></a>
		</td>
		<td width="*" nowrap="nowrap">
			{$lang.Addon_reengagement_Manage_MaxDays}
		</td>
        <td width="8%" nowrap="nowrap">
			{$lang.Addon_reengagement_Manage_ReCreated}
			<a href="{$AdminUrl}&SortBy=createdate&Direction=asc"><img src="{$ApplicationUrl}images/sortup.gif" border="0"/></a>
			<a href="{$AdminUrl}&SortBy=createdate&Direction=desc"><img src="{$ApplicationUrl}images/sortdown.gif" border="0"/></a>
		</td>
        <td width="10%" nowrap="nowrap">
			{$lang.Addon_reengagement_Manage_ReLastSent}
			<a href="{$AdminUrl}&SortBy=lastsent&Direction=asc"><img src="{$ApplicationUrl}images/sortup.gif" border="0"/></a>
			<a href="{$AdminUrl}&SortBy=lastsent&Direction=desc"><img src="{$ApplicationUrl}images/sortdown.gif" border="0"/></a>
		</td>
		<td width="175" nowrap="nowrap">
			{$lang.Addon_reengagement_Manage_ReAction}
		</td>
	</tr>
	{foreach from=$reEngagements key=k item=reengagementEntry}
		<tr class="GridRow" id="{$reengagementEntry.reengageid}">
			<td width="1">
				<input class="UICheckboxToggleRows reEngagementSelection" type="checkbox" name="reengageids[]" value="{$reengagementEntry.reengageid}">
			</td>
			<td>
					<img src="{$ApplicationUrl}/addons/reengagement/images/sendreengageemail.png" border="0"/>
			</td>
			<td>
				{$reengagementEntry.reengagename}
			</td>
			<td>
				{$reengagementEntry.nameoflists}
			</td>
			<td>
				{$reengagementEntry.reengage_typeof}
			</td>
            <td>
				{$reengagementEntry.reengagedetails.max_numberofdays}
			</td>
			<td>
				{$reengagementEntry.createdate|dateformat,$DateFormat}
			</td>
			<td>
            	{if $reengagementEntry.jobstatus == 'n'}
                	<font color="red">Not Active till once cron run.</font>
                {else}
                    {if $reengagementEntry.lastsent == 0}
                        {$lang.Addon_reengagement_Manage_LastSent_Never}
                    {else}
                        {$reengagementEntry.lastsent|dateformat,"d M Y H:i"}
                    {/if}
                {/if}
			</td>
			<td>
				{if $SendPermission}
					{if $reengagementEntry.jobstatus == 'i'}
						<a href="{$AdminUrl}&Action=Pause&id={$reengagementEntry.reengageid}&Step=20">{$lang.Addon_reengagement_Pause}</a>
					{elseif $reengagementEntry.jobstatus == 'p'}
						<a href="{$AdminUrl}&Action=Send&id={$reengagementEntry.reengageid}&Step=30">{$lang.Addon_reengagement_Resume}</a>
					{elseif $reengagementEntry.jobstatus == 'w'}
						{if $ScheduleSendPermission}
							<a href="{$ApplicationUrl}index.php?Page=Schedule">{$lang.Addon_reengagement_WaitingToSend}</a>
						{else}
							<a href="{$AdminUrl}&Action=Send&id={$reengagementEntry.reengageid}&Step=20">{$lang.Addon_reengagement_WaitingToSend}</a>
						{/if}
					{elseif $reengagementEntry.jobstatus == 't'}
						<span class="HelpText" onMouseOut="HideHelp('reengageDisplayTimeout{$reengagementEntry.reengageid}');"
						onMouseOver="ShowQuickHelp('reengageDisplayTimeout{$reengagementEntry.reengageid}', '{$reengagementEntry.TimeoutTipHeading}', '{$reengagementEntry.TimeoutTipDetails}');">
							{if $ScheduleSendPermission}
								{$lang.Addon_reengagement_TimeoutMode}
							{else}
								<a href="{$AdminUrl}&Action=Send&id={$reengagementEntry.reengageid}&Step=20">{$lang.Addon_reengagement_TimeoutMode}</a>
							{/if}
						</span><div id="reengageDisplayTimeout{$reengagementEntry.reengageid}" style="display: none;"></div>
					{else}
						<?php /*<a href="{$AdminUrl}&Action=Send&id={$reengagementEntry.reengageid}">{$lang.Addon_reengagement_Manage_Send}</a>*/ ?>
					{/if}
				{/if}
				{if $EditPermission}
					{if $reengagementEntry.jobstatus == 'i' || $reengagementEntry.jobstatus == 'r'}
						&nbsp;<a href="#" onClick="alert('{$lang.Addon_reengagement_Manage_Edit_Disabled_Alert}'); return false;" title="{$lang.Addon_reengagement_Manage_Edit_Disabled}">{$lang.Addon_reengagement_Manage_Edit}</a>
					{else}
						&nbsp;<a href="{$AdminUrl}&Action=Edit&id={$reengagementEntry.reengageid}">{$lang.Addon_reengagement_Manage_Edit}</a>
					{/if}
				{/if}
				{if $CopyPermission}
					&nbsp;<a href="{$AdminUrl}&Action=Create&Copy={$reengagementEntry.reengageid}">{$lang.Addon_reengagement_Manage_Copy}</a>
				{/if}
				{if $DeletePermission}
					&nbsp;<a href="#" {if $reengagementEntry.jobstatus == 'i' || $reengagementEntry.jobstatus == 'r'} title="{$lang.Addon_reengagement_Manage_Delete_Disabled}"{/if} onClick="return DelReEngagement({$reengagementEntry.reengageid}, '{$reengagementEntry.jobstatus}');">{$lang.Addon_reengagement_Manage_Delete}</a>
				{/if}
				&nbsp;<a href="{$AdminUrl}&Action=TransferList&id={$reengagementEntry.reengageid}">{$lang.Addon_reengagement_Manage_Transfer}</a>
			</td>
		</tr>
	{/foreach}
</table>
</form>
