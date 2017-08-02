<?php

class ESEnableIndexingForAllPagesTask extends BuildTask {

	protected $title = 'Enable Indexing For All Pages Task';
	protected $description = "Checks the 'index this page' checkbox for all pages. Does NOT index on its own.";

	function run($request) {
		if (!Director::is_cli() && !Permission::check('ADMIN')) {
			echo 'You need to be admin for this.';
			return;
		}
		$allPages = DataObject::get('SiteTree', '"ESIndexThis" = 0 AND "ShowInSearch" = 1');
		$DataObjectProperites = Config::inst()->get('ESSearchSetting', 'ExcludeDataObject');
		if ($allPages) {
			foreach ($allPages as $page) {
				if ($page->ObsoleteClassName != NULL && !class_exists($page->ObsoleteClassName)) {
					continue;
				}
				if(in_array($page->ClassName, $DataObjectProperites)) {
					continue;
				}
				$page->ESIndexThis = 1;
				$page->writeToStage('Stage');
				if ($page->isPublished()) {
					$page->publish('Stage', 'Live');
				}
				if (Director::is_cli()) {
					echo "Page id" . $page->ID . " title " . $page->Title . " indexing enabled \n";
				} else {
					echo "Page id" . $page->ID . " title " . $page->Title . " indexing enabled <br>";
				}
			}
		}
		else{
			echo 'No pages to be enabled.';
		}
	}

}