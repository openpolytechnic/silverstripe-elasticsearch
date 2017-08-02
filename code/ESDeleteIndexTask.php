<?php

class ESDeleteIndexTask extends BuildTask {

	protected $title = 'Elastic Search Delete Index Task';
	protected $description = 'Deletes the current index set in site config';
	
	function run($request){
		if(!Director::is_cli() && !Permission::check('ADMIN')){
			echo 'You need to be admin for this.';
			return;
		}
		$siteConfig = SiteConfig::current_site_config();
		if($siteConfig->ESIndexName){
			$client = new SSElasticSearch();
			$client->deleteIndex();
			if (Director::is_cli()) {
				echo "index " . $siteConfig->ESIndexName . " deleted.\n";
			} else {
				echo "index " . $siteConfig->ESIndexName . " deleted.";
			}
		}
	}
}
