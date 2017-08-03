<?php

class ESUpdateAllPagesIndexTask extends BuildTask {

	protected $title = 'Elastic Search Update All Page Index Task';
	protected $description = 'Updates the index (adds/removes) for all pages (good if index name has changed)';

	public function run($request) {
		if (!Director::is_cli() && !Permission::check('ADMIN')) {
			echo 'You need to be admin for this or run it from command line.';
			return;
		}
		$delimiter = "<br />";
		if (Director::is_cli()) {
			$delimiter = "\n";
		}

		echo "Starting Re-Index.$delimiter";
		$DataObjectProperites = Config::inst()->get('ESSearchSetting', 'ExcludeDataObject');

		$allPages = SiteTree::get("SiteTree", '"ClassName" NOT IN (\'' . implode("','", $DataObjectProperites) . '\')');
		//$allPages = SiteTree::get("SiteTree", '"ClassName" IN( \'Programme\', \'Course\', \'SubjectPage\', \'HomePage\')');
		echo "Number of page to index :: ".$allPages->count().".$delimiter";
		foreach ($allPages as $index => $page) {
			if (!in_array($page->ClassName, $DataObjectProperites)
				&& $page->ESIndexThis
				&& $page->isPublished()
				&& $page->canView()) {
				$pageType = new ESPageType();
				$pageType->prepareData($page);
				$pageType->indexData();
				echo "$index) Page id" . $page->ID . " title " . $page->Title . " (re)added to index.$delimiter";
			} else {
				/*
				$pageType = new ESPageType();
				$pageType->deleteByID($page->ID);
				*/
				echo "$index) Page id" . $page->ID . " title " . $page->Title . " removed from index (if it was in it).$delimiter";

			}
		}
		echo $delimiter."DONE".$delimiter.$delimiter;
	}

}
