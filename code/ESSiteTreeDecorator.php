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
		if(!in_array($this->ClassName, $DataObjectProperites)) {
			$fields->addFieldsToTab('Root.Main', array(
				new CheckboxField('ESIndexThis', 'Show this page in site(elastic) search? (on publish)'),
				new NumericField('ESScoreBoost', 'Boost this page in search')
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
		if (!in_array($this->ClassName, $DataObjectProperites)
			&& $this->owner->ESIndexThis
			&& $this->canView()) {
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