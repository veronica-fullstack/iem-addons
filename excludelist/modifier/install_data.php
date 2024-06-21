<?php

#COPY_BLOCK_MULTILINKS_RUN1#

		$typeOfExclude = isset($this->jobdetails['ExcludeType']) ? $this->jobdetails['ExcludeType'] : 0;
		$isExclude = false;
		$excludeArray = [];
		if ($typeOfExclude == 1) {
			$excludeArray = $this->jobdetails['ExcludeList'];
		}

		if ($typeOfExclude == 2) {
			$excludeArray = $this->jobdetails['ExcludeSegment'];
		}

		if ($typeOfExclude == 4) {
			$excludeArray = $this->jobdetails['ExcludeRecency'];
		}

		$addon_system = new Interspire_Addons();
		$folderview_enabled = $addon_system->isEnabled("excludelist");

		if ($folderview_enabled) {
			$addonApiObj = $addon_system->Process("excludelist", "GetApi", "excludelist");
			$isExclude = $addonApiObj->getExcludeState($subscriberinfo['emailaddress'], $typeOfExclude, $excludeArray);
		}

		if ($isExclude && count($excludeArray) > 0) {
			$mail_result['success'] = 1;
			$this->jobdetails['EmailResults']['exclude']++;
		} else {
			$mail_result = $this->Email_API->Send(true, $disconnect);
		}
		
#FINISH_BLOCK_MULTILINKS_RUN1#

#COPY_BLOCK_MULTILINKS_RUN1_REPLACE#

$mail_result = $this->Email_API->Send(true, $disconnect);

#FINISH_BLOCK_MULTILINKS_RUN1_REPLACE#



#COPY_BLOCK_MULTILINKS_RUN2#

				$addon_system = new Interspire_Addons();
				$folderview_enabled = $addon_system->isEnabled("excludelist");
				if ($folderview_enabled) {
					$addonApiObj = $addon_system->Process("excludelist", "GetApi", "excludelist");
					$addonApiObj->ChooseListSendCampaign('Send', 'step2', false);
				}
				if ($filteringOption == 3 && !$user->HasAccess('Segments', 'Send')) {

#FINISH_BLOCK_MULTILINKS_RUN2#

#COPY_BLOCK_MULTILINKS_RUN2_REPLACE#

if ($filteringOption == 3 && !$user->HasAccess('Segments', 'Send')) {

#FINISH_BLOCK_MULTILINKS_RUN2_REPLACE#



#COPY_BLOCK_MULTILINKS_RUN3#

				$newsletter_chosen = $_POST['newsletter'];

				$addon_system = new Interspire_Addons();

				$folderview_enabled = $addon_system->isEnabled("excludelist");

				if ($folderview_enabled) {
					$addonApiObj = $addon_system->Process("excludelist", "GetApi", "excludelist");
					$addonApiObj->setExcludeSendCampaginSession();
				}

#FINISH_BLOCK_MULTILINKS_RUN3#

#COPY_BLOCK_MULTILINKS_RUN52#

		$addon_system = new Interspire_Addons(); 
		$folderview_enabled = $addon_system->isEnabled("excludelist"); 
		if ($folderview_enabled) {
			$addonApiObj = $addon_system->Process("excludelist", "GetApi", "excludelist"); 
			$addonApiObj->ChooseListSendCampaign('Send', 'step2', false); 
		} 
		$user = IEM::getCurrentUser();
		
#FINISH_BLOCK_MULTILINKS_RUN52#



#COPY_BLOCK_MULTILINKS_RUN52_REPLACE# 

		$user = IEM::getCurrentUser();

#FINISH_BLOCK_MULTILINKS_RUN52_REPLACE# 

#COPY_BLOCK_MULTILINKS_RUN3_REPLACE#

$newsletter_chosen = $_POST['newsletter'];

#FINISH_BLOCK_MULTILINKS_RUN3_REPLACE#



#COPY_BLOCK_MULTILINKS_RUN4#

				$importinfo = [];

				$excludeType = (isset($_POST['ShowExcludeOptions'])) ? (int)$_POST['ShowExcludeOptions'] : 0;

				$importinfo['ExcludeType'] = $excludeType;

				if ($excludeType == 1) {
					$excludeLists = (isset($_POST['exclude_lists'])) ? $_POST['exclude_lists'] : [];
					$importinfo['ExcludeList'] = $excludeLists;
				}

				if ($excludeType == 2) {
					$excludeSegments = (isset($_POST['exclude_segments'])) ? $_POST['exclude_segments'] : [];
					$importinfo['ExcludeSegment'] = $excludeSegments;
				}

				if ($excludeType == 4) {
					$excludeRecency = (isset($_POST['numofdays'])) ? $_POST['numofdays'] : [];
					$importinfo['ExcludeRecency'] = $excludeRecency;
				}

#FINISH_BLOCK_MULTILINKS_RUN4#

#COPY_BLOCK_MULTILINKS_RUN4_REPLACE#

$importinfo = [];

#FINISH_BLOCK_MULTILINKS_RUN4_REPLACE#



#COPY_BLOCK_MULTILINKS_RUN5#

				$addon_system = new Interspire_Addons();
				$folderview_enabled = $addon_system->isEnabled("excludelist");

				if ($folderview_enabled) {
					$addonApiObj = new Interspire_Addons();
					$isExclude = $addonApiObj->Process("excludelist", "GetApi", "excludelist")->ChooseListImportSubscribers('Import', 'step2', false);
				}			

				$this->ChooseList('Import', 'Step2');

#FINISH_BLOCK_MULTILINKS_RUN5#

#COPY_BLOCK_MULTILINKS_RUN5_REPLACE#

$this->ChooseList('Import', 'Step2');

#FINISH_BLOCK_MULTILINKS_RUN5_REPLACE#



#COPY_BLOCK_MULTILINKS_RUN6#

		$typeOfExclude = isset($importinfo['ExcludeType']) ? $importinfo['ExcludeType'] : 0;
		$isExclude = false;
		if ($typeOfExclude == 1) {
			$excludeArray = $importinfo['ExcludeList'];
		}
		if ($typeOfExclude == 2) {
			$excludeArray = $importinfo['ExcludeSegment'];
		}
		if ($typeOfExclude == 4) {
			$excludeArray = $importinfo['ExcludeRecency'];
		}
		$addon_system = new Interspire_Addons();
		$folderview_enabled = $addon_system->isEnabled("excludelist");
		if ($folderview_enabled) {
			$addonApiObj = $addon_system->Process("excludelist", "GetApi", "excludelist");
			$isExclude = $addonApiObj->getExcludeState($email, $typeOfExclude, $excludeArray);
		}
		if ($isExclude == true && count($excludeArray) > 0) {
			$importresults['failures']++;
			$importresults['failedemails'][] = $email;
			IEM::sessionSet('ImportResults', $importresults);
			return;
		}
		list($banned, $msg) = $SubscriberApi->IsBannedSubscriber($email, $list, false);

#FINISH_BLOCK_MULTILINKS_RUN6#

#COPY_BLOCK_MULTILINKS_RUN6_REPLACE#

list($banned, $msg) = $SubscriberApi->IsBannedSubscriber($email, $list, false);

#FINISH_BLOCK_MULTILINKS_RUN6_REPLACE#



#COPY_BLOCK_MULTILINKS_RUN7#

						$addonSystem = new Interspire_Addons();

						$folderviewEnabled = $addonSystem->isEnabled("excludelist");

						if ($folderviewEnabled) {
							$addonApiObj = $addonSystem->Process("excludelist", "GetApi", "excludelist");
							$addonApiObj->setExcludeSendAutoresponderSession();
						}

						$this->EditAutoresponderStep4();

#FINISH_BLOCK_MULTILINKS_RUN7#

#COPY_BLOCK_MULTILINKS_RUN7_REPLACE#

$this->EditAutoresponderStep4();

#FINISH_BLOCK_MULTILINKS_RUN7_REPLACE#



#COPY_BLOCK_MULTILINKS_RUN8#

						$addon_system = new Interspire_Addons();

						$folderview_enabled = $addon_system->isEnabled("excludelist");

						if ($folderview_enabled) {
							$addonApiObj = $addon_system->Process("excludelist", "GetApi", "excludelist");
							$addonApiObj->ChooseListSendCampaign('Send', 'step2', false);
						}

						$this->EditAutoresponderStep3();

#FINISH_BLOCK_MULTILINKS_RUN8#

#COPY_BLOCK_MULTILINKS_RUN8_REPLACE#

$this->EditAutoresponderStep3();

#FINISH_BLOCK_MULTILINKS_RUN8_REPLACE#



#COPY_BLOCK_MULTILINKS_RUN53#

						$addon_system = new Interspire_Addons();
						$folderview_enabled = $addon_system->isEnabled("excludelist");
						if ($folderview_enabled) {
							$addonApiObj = $addon_system->Process("excludelist", "GetApi", "excludelist");
							$addonApiObj->ChooseListSendCampaign('Send', 'step2', false);
						}
						$this->EditAutoresponderStep3($sessionauto['autoresponderid']);

#FINISH_BLOCK_MULTILINKS_RUN53# 

#COPY_BLOCK_MULTILINKS_RUN53_REPLACE#

$this->EditAutoresponderStep3($sessionauto['autoresponderid']);

#FINISH_BLOCK_MULTILINKS_RUN53_REPLACE# 



#COPY_BLOCK_MULTILINKS_RUN56# 

$addon_system = new Interspire_Addons();
$folderview_enabled = $addon_system->isEnabled("excludelist");
if ($folderview_enabled) {
	$addonApiObj = $addon_system->Process("excludelist", "GetApi", "excludelist");
	$addonApiObj->setExcludeSendAutoresponderSession();
}
$this->EditAutoresponderStep4($sessionauto['autoresponderid']);

#FINISH_BLOCK_MULTILINKS_RUN56# 

#COPY_BLOCK_MULTILINKS_RUN56_REPLACE#

$this->EditAutoresponderStep4($sessionauto['autoresponderid']);

#FINISH_BLOCK_MULTILINKS_RUN56_REPLACE# 



#COPY_BLOCK_MULTILINKS_RUN57#

$searchcriteria = $autoresponderapi->Get('searchcriteria');
$GLOBALS['donotexcludecontacts'] = 'checked="checked"';
$GLOBALS['excludeusingcontactlist'] = '';
$GLOBALS['excludeusingsegment'] = '';
$GLOBALS['excludebasedonrecency'] = '';
$GLOBALS['ExcludeRecency'] = '';
if (isset($searchcriteria['ExcludeType'])) {
	if ($searchcriteria['ExcludeType'] != 3) {
		$GLOBALS['donotexcludecontacts'] = '';
	}
	if ($searchcriteria['ExcludeType'] == 1) {
		$GLOBALS['excludeusingcontactlist'] = 'checked="checked"';
	}
	if ($searchcriteria['ExcludeType'] == 2) {
		$GLOBALS['excludeusingsegment'] = 'checked="checked"';
	}
	if ($searchcriteria['ExcludeType'] == 4) {
		$GLOBALS['excludebasedonrecency'] = 'checked="checked"';
	}
	if (isset($searchcriteria['ExcludeRecency'])) {
		if ((int)$searchcriteria['ExcludeRecency'][0] > 0) {
			$GLOBALS['ExcludeRecency'] = $searchcriteria['ExcludeRecency'][0];
		}
	}
	if (isset($searchcriteria['ExcludeList'])) {
		if ((int)count($searchcriteria['ExcludeList']) > 0) {
			$GLOBALS['ExcludeList'] = urlencode(json_encode($searchcriteria['ExcludeList']));
		}
	}
	if (isset($searchcriteria['ExcludeSegment'])) {
		if ((int)count($searchcriteria['ExcludeSegment']) > 0) {
			$GLOBALS['ExcludeSegment'] = urlencode(json_encode($searchcriteria['ExcludeSegment']));
		}
	}
	$GLOBALS['ExcludeType'] = $searchcriteria['ExcludeType'];
}
$charset = $autoresponderapi->Get('charset');

#FINISH_BLOCK_MULTILINKS_RUN57#

#COPY_BLOCK_MULTILINKS_RUN57_REPLACE# 

$charset =	$autoresponderapi->Get('charset');

#FINISH_BLOCK_MULTILINKS_RUN57_REPLACE#



#COPY_BLOCK_MULTILINKS_RUN9#

		$typeOfExclude = isset($search_criteria['ExcludeType']) ? $search_criteria['ExcludeType'] : 0;

		$isExclude = false;

		$excludeArray = [];

		if ($typeOfExclude == 1) {
			$excludeArray = $search_criteria['ExcludeList'];
		}

		if ($typeOfExclude == 2) {
			$excludeArray = $search_criteria['ExcludeSegment'];
		}

		if ($typeOfExclude == 4) {
			$excludeArray = $search_criteria['ExcludeRecency'];
		}

		$addon_system = new Interspire_Addons();
		$folderview_enabled = $addon_system->isEnabled("excludelist");

		if ($folderview_enabled) {
			$newSearchInfo = array('List' => $this->listid, 'Subscriber' => $recipient);
			$getEmailInfo = $this->Subscriber_API->GetSubscribers($searchinfo, array(), false);
			$currentEmailInfo = $getEmailInfo['subscriberlist'];

			if (count($currentEmailInfo) > 0) {
				$currentEmailAddress = $currentEmailInfo[0]['emailaddress'];
				$addonApiObj = $addon_system->Process("excludelist", "GetApi", "excludelist");
				$isExclude = $addonApiObj->getExcludeState($currentEmailAddress, $typeOfExclude, $excludeArray);

				if ($isExclude) {
					return false;
				}
			}
		}

		$check = $this->Subscriber_API->GetSubscribers($searchinfo, array(), true);

#FINISH_BLOCK_MULTILINKS_RUN9#

#COPY_BLOCK_MULTILINKS_RUN9_REPLACE#

$check = $this->Subscriber_API->GetSubscribers($searchinfo, array(), true);

#FINISH_BLOCK_MULTILINKS_RUN9_REPLACE#


?>

#COPY_BLOCK_MULTILINKS_RUN51#

<script>

					var ExcludePage = {	init:						function() {

						                    this.donotshowMailingList(); 

											$('.SendFilteringOption').click(function() { ExcludePage.selectSendingOption(this.value); });

											ExcludePage.selectPreselected();

										},

										selectSendingOption:	function(sendingOption) {

											if(sendingOption == 2) this.showSegment();

											if(sendingOption == 1) this.showMailingList();

											if(sendingOption == 3) this.donotshowMailingList();

											if(sendingOption == 4) this.showRecencyOptions();

										},

										showSegment:			function(transition) {

											$('#RecencyOptions').hide();

											$('#FilteringOptions').hide();

											$('#SegmentOptions').show();

										},

										showMailingList:		function(transition) {

											$('#RecencyOptions').hide(transition? 'slow' : '');

											$('#SegmentOptions').hide(transition? 'slow' : '');

											$('#FilteringOptions').show(transition? 'slow' : '');

										},

										donotshowMailingList:	function(transition) {

											$('#SegmentOptions').hide(transition? 'slow' : '');

											$('#FilteringOptions').hide(transition? 'slow' : '');

											$('#RecencyOptions').hide(transition? 'slow' : '');

										},

										showRecencyOptions: function(transition) {

											$('#SegmentOptions').hide(transition? 'slow' : '');

											$('#FilteringOptions').hide(transition? 'slow' : '');

											$('#RecencyOptions').show(transition? 'slow' : '');

										},

										selectPreselected: function() {

											var currSelected = parseInt('%%GLOBAL_ExcludeType%%');

											if( parseInt(currSelected) > 0 ) {

												$.each($('.SendFilteringOption'),function(indx,obj) { if( parseInt(obj.value) == parseInt(currSelected) ) obj.click(); } );

												if( currSelected == 1 )

												{

													var excludeList = JSON.parse(decodeURIComponent('%%GLOBAL_ExcludeList%%'));

													var exListsObj  = $('#exclude_lists input[type=checkbox]');

													$.each(exListsObj,function(indx,obj) {

														 $.each(excludeList,function(index,exlobj) {

														 	if( parseInt(obj.value) == parseInt(exlobj) ) obj.click();

														 });

													} );

												}

												if( currSelected == 2 )

												{

													var excludeSegment = JSON.parse(decodeURIComponent('%%GLOBAL_ExcludeSegment%%'));

													var exSegmentObj  = $('#exclude_segments input[type=checkbox]');

													$.each(exSegmentObj,function(indx,obj) {

														 $.each(excludeSegment,function(index,exsobj) {

														 	if( parseInt(obj.value) == parseInt(exsobj) ) obj.click();

														 });

													} );

												}

											}

										}

									};

					$(function() { ExcludePage.init(); });

				</script>

<table border="0" cellspacing="0" cellpadding="2" width="100%"
	class="Panel">

	<tr>

		<td colspan="2" class="Heading2">&nbsp;&nbsp;Choose a Exclude option</td>

	</tr>

	<tr>

		<td class="FieldLabel">{template="Not_Required"} I Want to:&nbsp;</td>

		<td valign="top">

			<table width="100%" cellspacing="0" cellpadding="0">

				<tr>

					<td><label class="SendFilteringOption_Label"
						for="DonotShowExcludeOptions"><input type="radio"
							name="ShowExcludeOptions" id="DonotShowExcludeOptions"
							class="SendFilteringOption" value="3" %%GLOBAL_donotexcludecontacts%% />Do
							not exclude contacts</label></td>

				</tr>

				<tr>

					<td><label class="SendFilteringOption_Label"
						for="DoNotShowFilteringOptions"><input type="radio"
							name="ShowExcludeOptions" id="DoNotShowFilteringOptions"
							class="SendFilteringOption" value="1" %%GLOBAL_excludeusingcontactlist%% />Exclude
							using contact list</label></td>

				</tr>

				<tr>

					<td><label class="SendFilteringOption_Label"
						for="ShowExcludeOptions"><input type="radio"
							name="ShowExcludeOptions" id="ShowExcludeOptions"
							class="SendFilteringOption" value="2" %%GLOBAL_excludeusingsegment%% />Exclude
							using segment</label></td>

				</tr>

				<tr>

					<td><label class="SendFilteringOption_Label"
						for="ShowExcludeRecencyOptions"><input type="radio"
							name="ShowExcludeOptions" id="ShowExcludeRecencyOptions"
							class="SendFilteringOption" value="4" %%GLOBAL_excludebasedonrecency%% />Exclude
							based on recency</label></td>

				</tr>

			</table>

		</td>

	</tr>

</table>

<div id="FilteringOptions"%%GLOBAL_FilteringOptions_Display%%>

	<table border="0" cellspacing="0" cellpadding="2" width="100%"
		class="Panel">

		<tr>

			<td colspan="2" class="Heading2">&nbsp;&nbsp;Exclude a Contact
				List(s)</td>

		</tr>

		<tr>

			<td width="200" class="FieldLabel">{template="Not_Required"} Exclude
				List :&nbsp;</td>

			<td><select id="exclude_lists" name="exclude_lists[]"
				multiple="multiple"
				class="SelectedLists ISSelectReplacement ISSelectSearch">

					%%GLOBAL_SelectList%%

			</select></td>

		</tr>

	</table>

</div>

<div id="SegmentOptions" style="display: none;">

	<table border="0" cellspacing="0" cellpadding="2" width="100%"
		class="Panel">

		<tr>

			<td colspan="2" class="Heading2">&nbsp;&nbsp;Exclude Segment(s)</td>

		</tr>

		<tr>

			<td width="200" class="FieldLabel">{template="Not_Required"} Exclude
				Segment:&nbsp;</td>

			<td><select id="exclude_segments" name="exclude_segments[]"
				multiple="multiple" class="SelectedSegments ISSelectReplacement">

					%%GLOBAL_SelectSegment%%

			</select></td>

		</tr>

	</table>

</div>

<div id="RecencyOptions" style="display: none;">

	<table border="0" cellspacing="0" cellpadding="2" width="100%"
		class="Panel">

		<tr>

			<td colspan="2" class="Heading2">&nbsp;&nbsp;Input Number of Day(s)</td>

		</tr>

		<tr>

			<td width="200" class="FieldLabel">{template="Not_Required"}

				Day(s):&nbsp;</td>

			<td><input type="text" name="numofdays[]" id="numofdays"
				value="%%GLOBAL_ExcludeRecency%%" /></td>

		</tr>

	</table>

</div>
#FINISH_BLOCK_MULTILINKS_RUN51#



#COPY_BLOCK_MULTILINKS_RUN51_REPLACE#
<table width="100%" cellspacing="0" cellpadding="2" border="0"
		class="PanelPlain">

#FINISH_BLOCK_MULTILINKS_RUN51_REPLACE#


		




		#COPY_BLOCK_MULTILINKS_RUN54#


			<table cellspacing="0" cellpadding="0" width="100%">

				<tr>

					<td>

						<div style="border: 6px solid rgb(223, 223, 223);">

							<div class="FlashSuccess"
								style="margin-top: 7px; margin-right: 5px;">

								<strong>Exclude List :</strong>

							</div>

							<div>%%GLOBAL_ExcludeSegmentMsg%%</div>

							<div>%%GLOBAL_ExcludeListMsg%%</div>

						</div>

					</td>

				</tr>

			</table> <BR /> #FINISH_BLOCK_MULTILINKS_RUN54#



			#COPY_BLOCK_MULTILINKS_RUN54_REPLACE#
		
		<td class="body" style="padding-top: 10px">

			#FINISH_BLOCK_MULTILINKS_RUN54_REPLACE# 
			
			#COPY_BLOCK_MULTILINKS_RUN55#





		
		
		<td class="body">

			<table cellspacing="0" cellpadding="0" width="100%">

				<tr>

					<td>

						<div style="border: 6px solid rgb(223, 223, 223);">

							<div class="FlashSuccess"
								style="margin-top: 7px; margin-right: 5px;">

								<strong>Exclude List :</strong>

							</div>

							<div>%%GLOBAL_ExcludeSegmentMsg%%</div>

							<div>%%GLOBAL_ExcludeListMsg%%</div>

						</div>

					</td>

				</tr>

			</table> <BR /> 
#FINISH_BLOCK_MULTILINKS_RUN55#



#COPY_BLOCK_MULTILINKS_RUN55_REPLACE#
		
		<td class="body">
			
#FINISH_BLOCK_MULTILINKS_RUN55_REPLACE#
