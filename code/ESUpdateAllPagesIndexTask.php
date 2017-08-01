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
		$allPages = SiteTree::get();
		echo "Number of page to index :: ".$allPages->count().".\n";
		foreach ($allPages as $page) {
			Debug::dump("Indexing the page :: ".$page->Title."(".$page->ESIndexThis.")");
			if ($page->ESIndexThis && $page->isPublished()) {
				$pageType = new ESPageType();
				$pageType->prepareData($page);
				Debug::dump($pageType);
				$pageType->indexData();
				if(Director::is_cli()){
					echo "Page id" . $page->ID . " title " . $page->Title . " (re)added to index.\n";
				}
				else {
					echo "Page id" . $page->ID . " title " . $page->Title . " (re)added to index <br>";
				}
			} else {
				$pageType = new ESPageType();
				$pageType->deleteByID($page->ID);
				if(Director::is_cli()){
					echo "Page id" . $page->ID . " title " . $page->Title . " removed from index (if it was in it).\n";
				}
				else {
					echo "Page id" . $page->ID . " title " . $page->Title . " removed from index (if it was in it) <br>";
				}
			}
		}
		echo "\nDONE\n\n";
	}

}
