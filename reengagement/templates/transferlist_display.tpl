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
		<td class="Heading1" colspan="2">{$lang.Addon_reengagement_Transfer_Heading}</td>
	</tr>
	<tr>
		<td class="body pageinfo" colspan="2"><p>{$lang.Addon_reengagement_Transfer_Intro}</p></td>
	</tr>
	<tr>
		<td colspan="2">
			{$FlashMessages}
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
		<td width="*" nowrap="nowrap">
			{$lang.Addon_reengagement_Manage_Emailaddress}
			<a href="{$AdminUrl}&Action=TransferList&SortBy=emailaddress&Direction=asc"><img src="{$ApplicationUrl}images/sortup.gif" border="0"/></a>
			<a href="{$AdminUrl}&Action=TransferList&SortBy=emailaddress&Direction=desc"><img src="{$ApplicationUrl}images/sortdown.gif" border="0"/></a>
		</td>
		<td width="*" nowrap="nowrap">
			{$lang.Addon_reengagement_Manage_RefineList}
		</td>
        <td width="*" nowrap="nowrap">
			{$lang.Addon_reengagement_Manage_MaxDays}
		</td>
        <td width="*" nowrap="nowrap">
			{$lang.Addon_reengagement_Manage_TransferOn}
			<a href="{$AdminUrl}&Action=TransferList&SortBy=transferdate&Direction=asc"><img src="{$ApplicationUrl}images/sortup.gif" border="0"/></a>
			<a href="{$AdminUrl}&Action=TransferList&SortBy=transferdate&Direction=desc"><img src="{$ApplicationUrl}images/sortdown.gif" border="0"/></a>
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
				{$reengagementEntry.emailaddress}
			</td>
			<td>
				{$reengagementEntry.lists}
			</td>
			<td>
				{if $reengagementEntry.numberofdays != ''}{$reengagementEntry.numberofdays}{/if}
			</td>
			<td>
				{$reengagementEntry.transferdate|dateformat,"d M Y H:i"}
			</td>
		</tr>
	{/foreach}
</table>
</form>
