<?php

class ESSiteConfigDecorator extends DataExtension {

	private static $db = array(
		'ESIndexName' => 'Varchar(255)',
		'ESAPIEndPoint' => 'Varchar(255)',
		'ESHost' => 'Varchar(150)',
		'ESPort' => 'Varchar',
		'ESTransport' => 'Varchar',
		'ESSearchResultsLimit' => 'Int(10)'
	);

	private static $defaults = array(
		'ESIndexName' => 'ss',
		'ESSearchResultsLimit' => 10
	);

	public function updateCMSFields(FieldList $fields) {
		$fields->addFieldsToTab('Root.ElasticSearch', array(
			new TextField('ESIndexName', 'Index Name'),
			new TextField('ESSearchResultsLimit', 'Search results limit per page'),
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
	   
	}

}