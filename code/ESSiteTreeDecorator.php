<?php

class ESSiteTreeDecorator extends DataExtension {

	private static $db = array(
		'ESIndexThis' => 'Boolean',
		'ESScoreBoost' => 'Int'
	);

	private static $defaults = array(
		'ESIndexThis' => 1,
		'ESScoreBoost' => 1
	);

	public function updateCMSFields(FieldList $fields) {
		$DataObjectProperites = Config::inst()->get('ESSearchSetting', 'ExcludeDataObject');
		if(!in_array($this->owner->ClassName, $DataObjectProperites)) {
			$options = array(
				"0.1" => "Move to Bottom",
				"0.5" => "Move close to Bottom",
				"1" => "Normal( Use Search score )",
				"1.5" => "Move close to Top ",
				"2" => "Move to Top"
			);
			$fields->addFieldsToTab('Root.Main', array(
				new HeaderField("ESSearchHeading", "Search Setting", 3),
				new CheckboxField('ESIndexThis', 'Show this page in site(elastic) search? (on publish)'),
				new DropdownField('ESScoreBoost', 'Boost this page in search', $options)
			));

		}
		else {
			$fields->addFieldsToTab('Root.Main', array(
				new HiddenField('ESIndexThis', 'ESIndexThis', 0),
				new HiddenField('ESScoreBoost', 'ESScoreBoost', 0)
			));
		}
	}

	function onAfterPublish(&$original) {
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

	function onAfterUnpublish() {
		$pageType = new ESPageType();
		$pageType->deleteByID($this->owner->ID);
	}

}