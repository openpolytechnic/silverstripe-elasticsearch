<?php

class ESUpdateAllPagesIndexTask extends BuildTask {

	protected $title = 'Elastic Search Update All Page Index Task';
	protected $description = 'Updates the index (adds/removes) for all pages (good if index name has changed)';

	function run($request) {
		if (!Director::is_cli() && !Permission::check('ADMIN')) {
			echo 'You need to be admin for this or run it from command line.';
			return;
		}
		echo "Starting Re-Index.\n";
		$DataObjectProperites = Config::inst()->get('ESSearchSetting', 'ExcludeDataObject');
		$allPages = SiteTree::get("SiteTree", '"ClassName" NOT IN (\'' . implode("','", $DataObjectProperites) . '\')');
		//$allPages = SiteTree::get("SiteTree", '"ClassName" IN( \'Programme\', \'Course\', \'SubjectPage\', \'HomePage\')');
		echo "Number of page to index :: ".$allPages->count().".\n";
		foreach ($allPages as $index => $page) {
			if (!in_array($page->ClassName, $DataObjectProperites)
				&& $page->ESIndexThis
				&& $page->isPublished()
				&& $page->canView()) {
				$pageType = new ESPageType();
				$pageType->prepareData($page);
				$pageType->indexData();
				if(Director::is_cli()){
					echo "$index) Page id" . $page->ID . " title " . $page->Title . " (re)added to index.\n";
				}
				else {
					echo "$index) Page id" . $page->ID . " title " . $page->Title . " (re)added to index <br>";
				}
			} else {
				/*
				$pageType = new ESPageType();
				$pageType->deleteByID($page->ID);
				*/
				if(Director::is_cli()){
					echo "$index)Page id" . $page->ID . " title " . $page->Title . " removed from index (if it was in it).\n";
				}
				else {
					echo "$index)Page id" . $page->ID . " title " . $page->Title . " removed from index (if it was in it) <br>";
				}

			}
		}
		echo "Optimized the Index\n";
		//Optimized Index
		$searchClient = new SSElasticSearch();
		$searchClient->optimizeIndex();

		echo "\nDONE\n\n";
	}

}
