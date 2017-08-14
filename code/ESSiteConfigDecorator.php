<?php

class ESSiteConfigDecorator extends DataExtension {

	private static $db = array(
		'ESIndexName' => 'Varchar(255)',
		'ESAPIEndPoint' => 'Varchar(255)',
		'ESHost' => 'Varchar(150)',
		'ESPort' => 'Varchar',
		'ESTransport' => 'Varchar',
		'ESSearchUseAjax' => 'Boolean',
		'ESSearchUseAjaxCache' => 'Boolean',
		'ESSearchResultsLimit' => 'Int(10)',
		'ESSearchResultsTitle' => 'Varchar(255)',
		'ESSearchResultsIntro' => 'HTMLText',
		'ESSearchResultsNoResults' => 'HTMLText',
		'ESSearchResultsNotWorking' => 'HTMLText',
		'ESSearchResultsNoText' => 'HTMLText'
	);

	private static $defaults = array(
		'ESIndexName' => 'ss',
		'ESSearchResultsLimit' => 10
	);

	public function updateCMSFields(FieldList $fields) {
		$fields->addFieldsToTab('Root.ElasticSearch', array(
			new HeaderField("ESSearchResultHeader", "Search Result"),
			new CheckboxField('ESSearchUseAjax', 'Search To Use Ajax'),
			new CheckboxField('ESSearchUseAjaxCache', 'Search To Use Ajax Cache'),
			new TextField('ESSearchResultsTitle', 'Search Results Title'),
			$ESSearchResultsIntro = new HtmlEditorField('ESSearchResultsIntro', 'Search Results Introduction'),
			$ESSearchResultsNoResults = new HtmlEditorField('ESSearchResultsNoResults', 'Search No Results'),
			$ESSearchResultsNotWorking = new HtmlEditorField('ESSearchResultsNotWorking', 'Search Not Working'),
			$ESSearchResultsNoText = new HtmlEditorField('ESSearchResultsNoText', 'Search No Text'),
			new TextField('ESSearchResultsLimit', 'Search results limit per page'),
			new HeaderField("ESSearchElastic", "Elastic Search Server"),
			new TextField('ESIndexName', 'Index Name'),
			new TextField('ESAPIEndPoint', 'API End Point'),
			new LiteralField('EitherOrHeading', '<hr /><p>If Elastic serach server is run localy fill in the following detials. Only if API end point is not present.</p>'),
			new TextField('ESHost', 'Host (optional - default: localhost)'),
			new TextField('ESPort', 'Port (optional - default: 9200)'),
			new TextField('ESTransport', 'Transport (optional)'),
			new LiteralField('info', '<hr /><p>
				<a href="dev/tasks/ESEnableIndexingForAllPagesTask" target="new">Enable indexing for all pages</a>
				<br>
				<a href="dev/tasks/ESUpdateAllPagesIndexTask" target="new">Update index of all pages  (good if index name has changed or after index deletion)</a>
				<br>
				<br>			  
				<a href="dev/tasks/ESDeleteIndexTask" target="new">Delete index (careful!)</a>
				</p>')

		));
		$ESSearchResultsIntro->setRows(10);
		$ESSearchResultsNoResults->setRows(10);
		$ESSearchResultsNotWorking->setRows(10);
		$ESSearchResultsNoText->setRows(10);
	   
	}

}