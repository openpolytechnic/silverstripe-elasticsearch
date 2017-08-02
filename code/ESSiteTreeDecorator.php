<?php

class ESSiteTreeDecorator extends DataExtension {

	private static $db = array(
		'ESIndexThis' => 'Boolean'
	);

	private static $defaults = array(
		'ESIndexThis' => 1
	);

	/*
	public function extraStatics() {
		return array(
			'db' => array(
				'ESIndexThis' => 'Boolean'
			),
			'defaults' => array(
				'ESIndexThis' => 1
			)
		);
	}
	*/

	public function updateCMSFields(FieldList $fields) {
		$DataObjectProperites = Config::inst()->get('ESSearchSetting', 'ExcludeDataObject');
		if(!in_array($this->ClassName, $DataObjectProperites)) {
			$fields->addFieldsToTab('Root.ElasticSearch', array(
				new CheckboxField('ESIndexThis', 'Index this page in elastic search? (on publish)')
			));
		}
		else {
			$fields->addFieldsToTab('Root.Main', array(
				new HiddenField('ESIndexThis', 'ESIndexThis', 0)
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