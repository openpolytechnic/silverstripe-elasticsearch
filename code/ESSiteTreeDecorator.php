<?php

class ESSiteTreeDecorator extends DataExtension {

	public static $SkipSearchIndexUpdate = false;

	private static $db = array(
		'ESIndexThis' => 'Boolean',
		'ESScoreBoost' => 'Int',
		'ESSearchKeywords' => 'Varchar(255)'
	);

	private static $defaults = array(
		'ESIndexThis' => 1,
		'ESScoreBoost' => 1,
		'ESSearchKeywords' => ''
	);

	public function updateCMSFields(FieldList $fields) {
		Requirements::customScript(<<<ESJS
(function($) {
    $.entwine('ss', function($) {
    	var checkEnableESSetting = function() {
    		if($('input[name=ESIndexThis]').is(':checked')) {
    			$('#ESScoreBoost select, #ESSearchKeywords input').removeAttr('disabled');
    			$('#ESScoreBoost #Form_ItemEditForm_ESScoreBoost_chzn, #ESIndexLinkButton').show();
    			$('#ESIndexLinkButton').removeClass('ui-state-disabled');
				$('#ESScoreBoost .middleColumn .readonly-field').remove();
    		}
    		else {
    			$('#ESScoreBoost select, #ESSearchKeywords input').attr('disabled', 'disabled');
    			$('#ESScoreBoost #Form_ItemEditForm_ESScoreBoost_chzn').hide();
    			$('#ESScoreBoost .middleColumn .readonly-field').remove();
    			$('#ESIndexLinkButton').addClass('ui-state-disabled');
				$('#ESScoreBoost .middleColumn').append("<span class='readonly-field'>"+$('#ESScoreBoost select option:selected').text()+"</span>");    			
    		}
    	};
    	$('.cms-edit-form').entwine({
            onadd: function () {
                checkEnableESSetting();
            }
        });
        $('.cms-container').on('change', 'input[name=ESIndexThis]', function() {
        	checkEnableESSetting();
        });
        $('.cms-container').on('click', '#ESIndexLinkButton', function(event) {
        	console.log("In here");
        	event.preventDefault();
        	if(!$(this).is('.ui-state-disabled')) {
        		window.open($(this).attr('href'), 'Searchly Index','status=no,height=500,width=600');
        	}
        	return false;
        });
        
    });
}(jQuery));
ESJS
		);

		$DataObjectProperites = Config::inst()->get('ESSearchSetting', 'ExcludeDataObject');
		if(!in_array($this->owner->ClassName, $DataObjectProperites)) {
			$options = array(
				"0.1" => "Move to Bottom",
				"0.5" => "Move close to Bottom",
				"1" => "Normal( Use Search score )",
				"1.5" => "Move close to Top ",
				"2" => "Move to Top"
			);
			$fields->addFieldToTab('Root.Main', new ToggleCompositeField("ESSearchSetting", "Search Setting",array(
				new LiteralField("ESIndexName", "<div class='message warning'>When this page is published changes will be update to search immediately. Site is pointing to the index <strong>'".SSElasticSearch::indexName()."'</strong>.</div>"),
				new CheckboxField('ESIndexThis', 'Show this page in site(elastic) search? (on publish)'),
				new DropdownField('ESScoreBoost', 'Boost this page in search', $options),
				$keywordField = new TextField('ESSearchKeywords', 'Search Keyword push this page to top of result'),
				new LiteralField("ESIndexLink", "<div class='field'><a id='ESIndexLinkButton' href='".SSElasticSearch::indexLink($this->owner->ID)."' class='ss-ui-button' target='_blank'>Raw Index</a></div>")
			)));

		}
		else {
			$fields->addFieldsToTab('Root.Main', array(
				new HiddenField('ESIndexThis', 'ESIndexThis', 0),
				new HiddenField('ESScoreBoost', 'ESScoreBoost', 0)
			));
		}
		$keywordField->setRightTitle("Use comma(,) as delimiter to enter multiple keywords.");
	}

	public function onAfterPublish(&$original) {
		if(!self::$SkipSearchIndexUpdate) {
			$DataObjectProperites = Config::inst()->get('ESSearchSetting', 'ExcludeDataObject');
			if (!in_array($this->owner->ClassName, $DataObjectProperites)
				&& $this->owner->ESIndexThis
				&& $this->owner->canView()) {
				$pageType = new ESPageType();
				$pageType->prepareData($this->owner);
				$pageType->indexData();
			} else {
				$pageType = new ESPageType();
				$pageType->deleteByID($this->owner->ID);
			}
		}
	}

	public function onAfterUnpublish() {
		if(!self::$SkipSearchIndexUpdate) {
			$pageType = new ESPageType();
			$pageType->deleteByID($this->owner->ID);
		}
	}

}