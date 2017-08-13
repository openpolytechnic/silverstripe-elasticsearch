<?php

class ESUpdateAllPagesIndexTask extends BuildTask {

	protected $title = 'Elastic Search Update All Page Index Task';
	protected $description = 'Updates the index (adds/removes) for all pages (good if index name has changed)';
	private $dodelete = false;
	private $deleteIndex = false;

	public function run($request) {
		if (!Director::is_cli() && !Permission::check('ADMIN')) {
			echo 'You need to be admin for this or run it from command line.';
			return;
		}
		$delimiter = "<br />";
		if (Director::is_cli()) {
			$delimiter = "\n";
		}
		if(isset($_GET['full']) && trim($_GET['full']) != ''){
			$this->dodelete = true;
		}

		if(isset($_GET['clean']) && trim($_GET['clean']) != ''){
			$this->deleteIndex = true;
		}

		$siteConfig = SiteConfig::current_site_config();
		if($this->deleteIndex){
			$client = new SSElasticSearch();
			$client->deleteIndex();
			echo "Index " . $siteConfig->ESIndexName . " deleted.$delimiter";
		}
		$DataObjectProperites = Config::inst()->get('ESSearchSetting', 'ExcludeDataObject');
		echo "Starting Re-Index.$delimiter";
		if(isset($_GET['onlyfor']) && trim($_GET['onlyfor']) != ''){
			$classes = explode(',', $_GET['onlyfor']);
			$allPages = SiteTree::get("SiteTree", '"ClassName" IN (\'' . implode("','", $classes) . '\')');
		}
		else {
			$allPages = SiteTree::get("SiteTree", '"ClassName" NOT IN (\'' . implode("','", $DataObjectProperites) . '\')');
		}
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
			} else if($this->dodelete) {
				$pageType = new ESPageType();
				$pageType->deleteByID($page->ID);
				echo "$index) Page id" . $page->ID . " title " . $page->Title . " removed from index (if it was in it).$delimiter";

			}
		}
		echo $delimiter."DONE".$delimiter.$delimiter;
	}

}
