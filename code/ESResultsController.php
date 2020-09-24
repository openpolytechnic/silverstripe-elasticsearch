<?php

class ESResultsController extends Page_Controller {

	function init() {
		parent::init();
	}

	function index() {
		return $this->renderWith(array('ESPage_results', 'Page'));
	}

	protected function _fetchResults() {
		$results = NULL;
		if (isset($_GET['q'])) {
			$client = new SSElasticSearch();
			$query = trim((string) $_GET['q']);
			$from = 0;
			if(isset($_GET['start'])){
				$from = (int) $_GET['start'];
			}
			$results = $client->search($query, $from);
		}
		return $results;
	}

	function Results() {
		$results = $this->_fetchResults();
		if ($results) {
			if ($results->count() > 0) {
				$resultSet = new ArrayList();
				foreach ($results->getResults() as $result) {
					$content = new HTMLText();
					$content->setValue($result->Content);
					$result->Content = $content;
					$data = $result->getData();
					if($Highlights = $result->getHighlights()) {
						foreach ($Highlights as $key => $texts){
							$text = new HTMLText();
							$text->setValue(implode("\n", $texts));
							$data[$key] = $text;
						}
					}
					$resultSet->push(new ArrayData(($data)));
				}
				return new ArrayData(array(
					'TotalHits' => isset($results) ? $results->getTotalHits() : 0,
					'Results' => $resultSet
				));
			}
		}
	}

	function NextResults() {
		$results = $this->_fetchResults($this->_getNextFrom());
		if ($results && $results->count() > 0) {
			return true;
		}
		return false;
	}

	function PreviousResults() {
		if (!isset($_GET['start']) || (int) $_GET['start'] == 0) {
			return false;
		}
		$results = $this->_fetchResults($this->_getPreviousFrom());
		if ($results && $results->count() > 0) {
			return true;
		}
		return false;
	}

	function getPrevLink() {
		return HTTP::setGetVar('start', $this->_getPreviousFrom());
	}

	function getNextLink() {
		return HTTP::setGetVar('start', $this->_getNextFrom());
	}

	protected function _getNextFrom() {
		$from = 0;
		if (isset($_GET['start'])) {
			$from = (int) $_GET['start'];
		}
		$from = $from + (int) SiteConfig::current_site_config()->ESSearchResultsLimit;
		return $from;
	}

	protected function _getPreviousFrom() {
		$from = 0;
		if (isset($_GET['start'])) {
			$from = (int) $_GET['start'];
		}
		$limit = (int) SiteConfig::current_site_config()->ESSearchResultsLimit;
		if ($from < $limit) {
			$from = 0;
		} else {
			$from = $from - $limit;
		}
		return $from;
	}

}