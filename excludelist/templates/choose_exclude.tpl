<script>
	var ExcludePage = {	init:						function() {
							$('.SendFilteringOption').click(function() { ExcludePage.selectSendingOption(this.value); });
						},
						selectSendingOption:	function(sendingOption) {
							if(sendingOption == 2) this.showSegment();
							else this.showMailingList();
						},
						showSegment:			function(transition) {
							$('#FilteringOptions').hide();
							$('#SegmentOptions').show();
						},
						showMailingList:		function(transition) {
							$('#SegmentOptions').hide(transition? 'slow' : '');
							$('#FilteringOptions').show(transition? 'slow' : '');
						}
					};
	$(function() { ExcludePage.init(); });
						
</script>
<table border="0" cellspacing="0" cellpadding="2" width="100%" class="Panel">
    <tr>
        <td colspan="2" class="Heading2">
            &nbsp;&nbsp;%%LNG_FilterOptions_Send%%
        </td>
    </tr>
    <tr>
        <td class="FieldLabel">
            {template="Not_Required"}
            %%LNG_ShowFilteringOptions_Send%%&nbsp;
        </td>
        <td valign="top">
            <table width="100%" cellspacing="0" cellpadding="0">
                <tr>
                    <td>
                        <label class="SendFilteringOption_Label" for="DoNotShowFilteringOptions"><input type="radio" name="ShowExcludeOptions" id="DoNotShowFilteringOptions" class="SendFilteringOption" value="1" checked="checked" />%%LNG_NotSendDoNotShowFilteringOptionsExplain%%</label>
                    </td>
                </tr>
                <tr>
                    <td>
                        <label class="SendFilteringOption_Label" for="ShowExcludeOptions"><input type="radio" name="ShowExcludeOptions" id="ShowExcludeOptions" class="SendFilteringOption" value="2" />%%LNG_NotSendShowSegmentOptionsExplain%%</label>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
<div id="FilteringOptions" %%GLOBAL_FilteringOptions_Display%%>
    <table border="0" cellspacing="0" cellpadding="2" width="100%" class="Panel">
        <tr>
            <td colspan="2" class="Heading2">
                &nbsp;&nbsp;%%LNG_MailingListDetails%%
            </td>
        </tr>
        <tr>
            <td width="200" class="FieldLabel">
                {template="Not_Required"}
                %%LNG_SendMailingList%%:&nbsp;
            </td>
            <td>
                <select id="exclude_lists" name="exclude_lists[]" multiple="multiple" class="SelectedLists ISSelectReplacement ISSelectSearch">
                    %%GLOBAL_SelectList%%
                </select>&nbsp;%%LNG_HLP_SendMailingList%%
            </td>
        </tr>
    </table>
</div>
<div id="SegmentOptions" style="display:none;">
    <table border="0" cellspacing="0" cellpadding="2" width="100%" class="Panel">
        <tr>
            <td colspan="2" class="Heading2">
                &nbsp;&nbsp;%%LNG_SegmentDetails%%
            </td>
        </tr>
        <tr>
            <td width="200" class="FieldLabel">
                {template="Not_Required"}
                %%LNG_SendToSegment%%:&nbsp;
            </td>
            <td>
                <select id="exclude_segments" name="exclude_segments[]" multiple="multiple" class="SelectedSegments ISSelectReplacement">
                    %%GLOBAL_SelectSegment%%
                </select>&nbsp;%%LNG_HLP_SendToSegment%%
            </td>
        </tr>
    </table>
</div>