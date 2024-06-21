<script>
	var PAGE = {
		init: function() {
			Application.Ui.CheckboxSelection(
				'table#ReengagementStatisticList',
				'input.UICheckboxToggleSelector',
				'input.UICheckboxToggleRows'
			);

			$('.disabledlink').click(function() {
				alert('{$lang.Addon_reengagement_Stats_NotFinished}');
				return false;
			});

			$('.StatsDisplayDeleteStat').click(PAGE.deleteReengagementStat);
			$('.StatsDisplayExportStat').click(PAGE.exportReengagementStat);
			$('#StatsForm').submit(PAGE.selectReengagementAction);
		},

		selectReengagementAction: function(e) {
			var subAction = $('#SelectAction').val();
			var selected = 	$('.statsSelection:checked');

			if (selected.length < 1) {
				alert("{$lang.Addon_reengagement_Multi_SelectFirst}");
				return false;
			}

			switch (subAction) {
				case 'MultiDelete':
					if (!confirm('{$lang.Addon_reengagement_Delete_ConfirmMessage}')) {
						return false;
					}
					// flow through to standard form submission
					break;

				case 'MultiPrint':
					var jobids = $('.jobid:checked').map(function() {
						return 'jobids[]=' + $(this).val();
					});
					var statids = $('.jobid:checked').map(function() {
						return 'statids[]=' + $(this).next('.statid').val();
					});
					var job_stats = $.makeArray(jobids).join('&') + '&' + $.makeArray(statids).join('&');
					var url = 'addons/reengagement/print_stats_options.php?height=270&width=420&overflow=none&path=addons/reengagement/&statstype=reengagement&Action=printsubaction=print&' + job_stats + '&options[]=snapshot&options[]=open&options[]=click&options[]=bounce&options[]=unsubscribe';
					tb_show('{$lang.Addon_reengagement_PrintReEngagementStatistics}', url, '');
					return false;
					break;

				case 'MultiExport':
					// flow through to standard form submission
					break;

				default :
					alert('{$lang.PleaseChooseAction}');
					return false;
					break;
			}
		},

		deleteSelected: function() {
			var selected = 	$('.statsSelection:checked');

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

			Application.Util.submitPost('{$AdminUrl}&Action=Stats&Step=40', {statids: selectedIds});
			e.stopPropagation();
			e.preventDefault();
		},

		deleteReengagementStat: function(e) {
			if (confirm('Are you sure you want to delete the selected Re Engagement Statistics?')) {
				var jobID = $(this).attr('id').match(/hrefStatsDisplayDeleteJob_(\d*)/);
				var reengageID = $(this).attr('reengageid').match(/hrefStatsDisplayDeleteJob_(\d*)/);
				Application.Util.submitPost('index.php?Page=Addons&Addon=reengagement&Action=Stats',
					{SubAction : 'Delete', jobid: jobID[1], reengageid: reengageID[1]}
				);
			}
			e.stopPropagation();
			e.preventDefault();
		},

		exportReengagementStat: function(e) {
			var jobID = $(this).attr('id').match(/hrefStatsDisplayExportJob_(\d*)/);
			var reengageID = $(this).attr('reengageid').match(/hrefStatsDisplayExportJob_(\d*)/);
			Application.Util.submitPost('index.php?Page=Addons&Addon=reengagement&Action=Stats',
				{SubAction : 'Export', jobid: jobID[1], reengageid: reengageID[1]}
			);
			e.stopPropagation();
			e.preventDefault();
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

		Application.Util.submitPost('{$AdminUrl}&Action=Stats&Step=40', {statid: id});
		return true;
	}
</script>


<table width="100%" border="0">
	<tr>
		<td class="Heading1">{$lang.Addon_reengagement_Stats_Heading}</td>
	</tr>
	<tr>
		<td class="body pageinfo"><p>{$lang.Addon_reengagement_Stats_Intro}</p></td>
	</tr>
	<tr>
		<td>
			{$FlashMessages}
		</td>
	</tr>
	<tr>
		<td class="body">
			<form name="StatsForm" id="StatsForm" method="post" action="{$AdminUrl}&Action=Stats">
			<!--<form name="mystatsform" method="post">-->
				<table width="100%" border="0">
					<tr>
						<td valign="top">
							<select id="SelectAction" name="SubAction">
								<option value="" selected="selected">{$lang.Addon_reengagement_Stats_ChooseAction}</option>
								<option value="MultiDelete">{$lang.Addon_reengagement_Stats_DeleteSelected}</option>
								<option value="MultiPrint">{$lang.Addon_reengagement_Stats_PrintSelected}</option>
								<option value="MultiExport">{$lang.Addon_reengagement_Stats_ExportSelected}</option>
							</select>
							<input type="submit" name="cmdChangeType" class="Text" value="{$lang.Go}" />
						</td>
						<td align="right">
							{$Paging}
						</td>
					</tr>
				</table>
				<table border="0" cellspacing="0" cellpadding="0" width="100%" class="Text" id="ReengagementStatisticList">
					<tr class="Heading3">
						<td width="5"><input type="checkbox" name="toggle" class="UICheckboxToggleSelector"></td>
						<td width="5">&nbsp;</td>
						<td width="15%" nowrap="nowrap">
							{$lang.Addon_reengagement_Stats_RssName}&nbsp;<a href='{$AdminUrl}&Action=Stats&SortBy=reengagename&Direction=asc'><img src="{$ApplicationUrl}images/sortup.gif" border="0"></a>&nbsp;<a href='{$AdminUrl}&Action=Stats&SortBy=reengagename&Direction=desc'><img src="{$ApplicationUrl}images/sortdown.gif" border="0"></a>
						</td>
						<td width="10%" nowrap="nowrap">
							{$lang.Addon_reengagement_Stats_TypeOfEngage}&nbsp;<a href='{$AdminUrl}&Action=Stats&SortBy=reengage_typeof&Direction=asc'><img src="{$ApplicationUrl}images/sortup.gif" border="0"></a>&nbsp;<a href='{$AdminUrl}&Action=Stats&SortBy=reengage_typeof&Direction=desc'><img src="{$ApplicationUrl}images/sortdown.gif" border="0"></a>
						</td>
						<td width="%10" nowrap="nowrap">
							{$lang.Addon_reengagement_Stats_ListNames}&nbsp;<a href='{$AdminUrl}&Action=Stats&SortBy=list&Direction=asc'><img src="{$ApplicationUrl}images/sortup.gif" border="0"></a>&nbsp;<a href='{$AdminUrl}&Action=Stats&SortBy=list&Direction=desc'><img src="{$ApplicationUrl}images/sortdown.gif" border="0"></a>
						</td>
						<td width="20%">
							{$lang.Addon_reengagement_Stats_CampaignNames}&nbsp;<a href='{$AdminUrl}&Action=Stats&SortBy=list&Direction=asc'><img src="{$ApplicationUrl}images/sortup.gif" border="0"></a>&nbsp;<a href='{$AdminUrl}&Action=Stats&SortBy=list&Direction=desc'><img src="{$ApplicationUrl}images/sortdown.gif" border="0"></a>
						</td>
						<td width="10%" nowrap="nowrap">
							{$lang.Addon_reengagement_Stats_DateFinished}&nbsp;<a href='{$AdminUrl}&Action=Stats&SortBy=finishtime&Direction=asc'><img src="{$ApplicationUrl}images/sortup.gif" border="0"></a>&nbsp;<a href='{$AdminUrl}&Action=Stats&SortBy=finishtime&Direction=desc'><img src="{$ApplicationUrl}images/sortdown.gif" border="0"></a>
						</td>
						<td width="100" nowrap="nowrap">
							{$lang.Addon_reengagement_Stats_TotalRecipients}&nbsp;<a href='{$AdminUrl}&Action=Stats&SortBy='><img src="{$ApplicationUrl}images/sortup.gif" border="0"></a>&nbsp;<a href='{$AdminUrl}&Action=Stats&SortBy='><img src="{$ApplicationUrl}images/sortdown.gif" border="0"></a>
						</td>
						<td width="5%">
							%%LNG_Action%%
						</td>
					</tr>
					{foreach from=$Statistics key=statid item=statsDetails}
						<tr class="GridRow">
							<td>
								<input type="checkbox" value="{$statsDetails.jobid}" name="jobids[]" class="UICheckboxToggleRows statsSelection jobid" />
								<input type="hidden" id="statid_{$statsDetails.jobid}" name="statids[]" value="{$statsDetails.reengageid}" class="statid" />
							</td>
							<td>
								<img src="{$ApplicationUrl}addons/reengagement/images/sendreengageemail.png" height="16" width="16" border="0" />
							</td>
							<td>
								{$statsDetails.reengagename}
							</td>
							<td>
								{$statsDetails.reengage_typeof}
							</td>
							<td>
								{foreach from=$statsDetails.lists item=list id=sequence}
									{$list.name}{if !$sequence.last}, {/if}
								{/foreach}
							</td>
							<td>
								{$statsDetails.campaign_names}
							</td>
							<td>
								{if $statsDetails.finishtime == 0}
									{$lang.Addon_reengagement_Stats_FinishTime_NotFinished}
								{else}
									{$statsDetails.finishtime|dateformat,$DateFormat}
								{/if}
							</td>
							<td align="center">
								{* total recipients *}
								{* $statsDetails.sendsize *}
								{$statsDetails.total_recipient_count}
							</td>
                            <td nowrap="nowrap">
								<!-- actions here -->
								{if $statsDetails.finishtime == 0}
									{capture name=active_status}disabledlink{/capture}
								{/if}
								<a class="{$active_status}" href="index.php?Page=Addons&Addon=reengagement&Action=Stats&reengageid={$statsDetails.reengageid}&jobid={$statsDetails.jobid}">{$lang.Addon_reengagement_Stats_View}</a>
								
								&nbsp;<a class="{$active_status} StatsDisplayExportStat" id="hrefStatsDisplayExportJob_{$statsDetails.jobid}" reengageid="hrefStatsDisplayExportJob_{$statsDetails.reengageid}" href="#">{$lang.Addon_reengagement_Stats_Export}</a>
								
								&nbsp;<a class="{$active_status} thickbox" href="addons/reengagement/print_stats_options.php?height=290&width=420&overflow=none&statstype=reengagement&Action=print&statids={$statsDetails.reengageid}&jobids={$statsDetails.jobid}&path=addons/reengagement/" title="{$lang.Addon_reengagement_PrintReEngagementStatistics}">{$lang.Addon_reengagement_Stats_Print}</a>
								
								&nbsp;<a id="hrefStatsDisplayDeleteJob_{$statsDetails.jobid}" reengageid="hrefStatsDisplayDeleteJob_{$statsDetails.reengageid}" class="StatsDisplayDeleteStat" href="#">{$lang.Addon_reengagement_Stats_Delete}</a>
								&nbsp;
								{capture name=active_status}{/capture}
							</td>
						</tr>
					{/foreach}
				</table>
			</form>
		</td>
	</tr>
</table>
