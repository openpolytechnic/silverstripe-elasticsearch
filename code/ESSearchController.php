<?php

class ESSearchController extends Controller {

    private $page_controller;

    public function __construct() {
        parent::__construct();
        $dataRecord = new Page();
		$dataRecord->IntroText = $this->getIntroText();
		$dataRecord->NoResultsText = $this->getNoResultsText();
		$dataRecord->NoSearchKeywordText = $this->getNoSearchKeywordText();
		$dataRecord->SearchOfflineText = $this->getSearchOfflineText();
        $this->page_controller = new Page_Controller($dataRecord);
    }

    public function init()
    {
        parent::init();
        $this->page_controller->setRequest($this->request);
        $this->page_controller->init();
    }

    public function index() {
		$siteConfig = SiteConfig::current_site_config();
		//USE AJAX
		$returnAsJSON = false;
		if($this->getRequest()->getVar('ajax')) {
			$this->response->addHeader("Content-type", "application/json");
			if(isset($siteConfig->ESSearchUseAjaxCache) && $siteConfig->ESSearchUseAjaxCache ) {
				$cache = SS_Cache::factory('esserachresult');
				$cachekey = md5(implode('_',
						array(
							serialize($this->getRequest()->getVars()),
							SiteTree::get()->max('LastEdited')
						)
					)
				);
				if ($cacheContent = unserialize($cache->load($cachekey))) {
					$this->response->setBody($cacheContent);
					return $this->response;
				}
			}
			$returnAsJSON = true;
		}

		if($this->getRequest()->getVar('Search')
            && trim(strip_tags($this->getRequest()->getVar('Search'))) != ''){
			$query = trim(strip_tags($this->getRequest()->getVar('Search')));
		}
		else {
			return $this->page_controller->customise(array(
				'SiteConfig' => $siteConfig,
				'ClassName' => 'SearchPage',
				'Title' => $siteConfig->ESSearchResultsTitle,
				'Search' => false,
				'Offset' => 0,
				'TotalHits' => 0,
				'Results' => null,
				'UseAjax' => $siteConfig->ESSearchUseAjax
			))->renderWith(array('ESSearchPage', 'Page'));
		}
		$filters = array();
		$sort = array('_score' => 'desc');
		$from = 0;
		$limit = isset(SiteConfig::current_site_config()->ESSearchResultsTitle) ? (int)SiteConfig::current_site_config()->ESSearchResultsTitle : 10;
		if($siteConfig->ESSearchResultsLimit) {
			$limit = $siteConfig->ESSearchResultsLimit;
		}
		if($this->getRequest()->getVar('start')) {
			$from = (int)$this->getRequest()->getVar('start');
		}
		if($this->getRequest()->getVar('limit')) {
			$limit = (int)$this->getRequest()->getVar('limit');
		}

		if($this->getRequest()->getVar('filters')) {
			$filters = $this->getRequest()->getVar('filters');
		}
		if($this->getRequest()->getVar('sort')) {
			$sort = array($this->getRequest()->getVar('sort') => 'desc');
		}
		$baseURL = $this->getBaseURL($query);

		if(!$returnAsJSON && $siteConfig->ESSearchUseAjax){
			return $this->page_controller->customise(array(
				'SiteConfig' => $siteConfig,
				'ClassName' => 'SearchPage',
				'Title' => $siteConfig->ESSearchResultsTitle,
				'SearchURL' => $this->getBaseURL(''),
				'UseAjax' => true,
				'Search' => trim($query),
				'Offset' => $from,
				'Filters' => $filters,
				'Sort' => $sort
			))->renderWith(array('ESSearchPage', 'Page'));
		}

		$client = new SSElasticSearch();
		$resultSet = array();
		$results = $client->search($query, $filters, $from, $limit, $sort);
		$SearchOffline = false;

		if($results->getResponse()){
			$data = $results->getResponse()->getData();
			if(isset($data['status']) && strtolower(trim($data['status'])) == 'error'){
				$SearchOffline = true;
			}
		}
		else {
			$SearchOffline = true;
		}

		if ($results) {
			if ($results->count() > 0) {
				foreach ($results->getResults() as $result) {
					$content = new HTMLText();
					$content->setValue(ESPageType::cleanString($result->Content));
					$result->Content = $content;
					$data = $result->getData();
					if($Highlights = $result->getHighlights()) {
						foreach ($Highlights as $key => $texts){
							if($returnAsJSON){
								$text = implode("\n", $texts);
							}
							else {
								$text = new HTMLText();
								$text->setValue(implode("\n", $texts));
							}
							if($text && trim($text) != '') {
								$data[$key] = $text;
							}
						}
					}
					if(strlen($data['Content']) > 300) {
						$data['Content'] = self::truncate($data['Content'], 300);
					}
					$data['Score'] = $result->getScore();
					if($returnAsJSON){
						$resultSet[] = $data;
					}
					else {
						$resultSet[] = new ArrayData($data);
					}
				}
			}
		}
		$outputdata = array(
			'Search' => trim($query),
			'Offset' => $from,
			'TotalHits' => $results->getTotalHits(),
			'Results' => $resultSet,
			'ResultStart' => ($from + 1),
			'ResultEnd' => min($from + 10, $results->getTotalHits()),
			'SearchURL' => $baseURL,
			'Filters' => $filters,
			'Sort' => $sort,
			'Offline' => $SearchOffline,
			'UseAjax' => $siteConfig->ESSearchUseAjax
		);

		if($returnAsJSON){
			$output = json_encode($outputdata);
			if(isset($cache)){
				$cache->save(serialize($output));
			}
			$this->response->setBody($output);
			return $this->response;
		}
		else {
			$outputdata['ClassName'] = 'SearchPage';
			$outputdata['Title'] = $siteConfig->ESSearchResultsTitle;
			$outputdata['Introduction'] = $siteConfig->ESSearchResultsIntro;
			$outputdata['Results'] = new ArrayList($resultSet);
			$outputdata['Pagination'] = $this->getPagination($results);
			$outputdata['SiteConfig'] = $siteConfig;
		}
		return $this->page_controller->customise($outputdata)->renderWith(array('ESSearchPage', 'Page'));
	}

	private function getBaseURL($search = '') {
		$currentURL = Director::makeRelative($_SERVER['REQUEST_URI']);
		$parts = parse_url($currentURL);
		$path = (isset($parts['path']) && $parts['path'] != '') ? $parts['path'] : '';
		// Recompile URI segments
		if($search && trim($search) != '') {
			return $path . '?Search=' . urlencode($search);
		}
		return $path;
	}

	public function getPagination($results, $start = 0, $limit = 10) {
		$total = $results->getTotalHits();
		$list = new ArrayList(range(0, $total - 1));
		$pagination = new PaginatedList($list, $this->getRequest());
		return $pagination;
	}

	public function SearchForm()
	{
		$searchText = "Enter a word or phrase";

		if ($this->owner->request) {
			$searchText = trim(strip_tags($this->owner->request->getVar('Search')));
		}

		$fields = new FieldList(
			new TextField('Search', false, $searchText)
		);

		$actions = new FieldList(
			new FormAction('results', _t('SearchForm.Search', 'Search'))
		);

		$form = new SearchForm($this->owner, 'SearchForm', $fields, $actions);
		if(Config::inst()->get('ESSearchSetting', 'SearchURL')){
			$form->setFormAction(Config::inst()->get('ESSearchSetting', 'SearchURL'));
		}
		else {
			$form->setFormAction('site/search');
		}

		return $form;
	}

	public static function truncate($text, $length = 100, $ending = '...', $exact = false, $considerHtml = true) {
		if(strlen($text) <= 100) {
			return $text;
		}
		if ($considerHtml) {
			// if the plain text is shorter than the maximum length, return the whole text
			if (strlen(preg_replace('/<.*?>/', '', $text)) <= $length) {
				return $text;
			}
			// splits all html-tags to scanable lines
			preg_match_all('/(<.+?>)?([^<>]*)/s', $text, $lines, PREG_SET_ORDER);
			$total_length = strlen($ending);
			$open_tags = array();
			$truncate = '';
			foreach ($lines as $line_matchings) {
				// if there is any html-tag in this line, handle it and add it (uncounted) to the output
				if (!empty($line_matchings[1])) {
					// if it's an "empty element" with or without xhtml-conform closing slash
					if (preg_match('/^<(\s*.+?\/\s*|\s*(img|br|input|hr|area|base|basefont|col|frame|isindex|link|meta|param)(\s.+?)?)>$/is', $line_matchings[1])) {
						// do nothing
						// if tag is a closing tag
					} else if (preg_match('/^<\s*\/([^\s]+?)\s*>$/s', $line_matchings[1], $tag_matchings)) {
						// delete tag from $open_tags list
						$pos = array_search($tag_matchings[1], $open_tags);
						if ($pos !== false) {
							unset($open_tags[$pos]);
						}
						// if tag is an opening tag
					} else if (preg_match('/^<\s*([^\s>!]+).*?>$/s', $line_matchings[1], $tag_matchings)) {
						// add tag to the beginning of $open_tags list
						array_unshift($open_tags, strtolower($tag_matchings[1]));
					}
					// add html-tag to $truncate'd text
					$truncate .= $line_matchings[1];
				}
				// calculate the length of the plain text part of the line; handle entities as one character
				$content_length = strlen(preg_replace('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|[0-9a-f]{1,6};/i', ' ', $line_matchings[2]));
				if ($total_length+$content_length> $length) {
					// the number of characters which are left
					$left = $length - $total_length;
					$entities_length = 0;
					// search for html entities
					if (preg_match_all('/&[0-9a-z]{2,8};|&#[0-9]{1,7};|[0-9a-f]{1,6};/i', $line_matchings[2], $entities, PREG_OFFSET_CAPTURE)) {
						// calculate the real length of all entities in the legal range
						foreach ($entities[0] as $entity) {
							if ($entity[1]+1-$entities_length <= $left) {
								$left--;
								$entities_length += strlen($entity[0]);
							} else {
								// no more characters left
								break;
							}
						}
					}
					$truncate .= substr($line_matchings[2], 0, $left+$entities_length);
					// maximum lenght is reached, so get off the loop
					break;
				} else {
					$truncate .= $line_matchings[2];
					$total_length += $content_length;
				}
				// if the maximum length is reached, get off the loop
				if($total_length>= $length) {
					break;
				}
			}
		} else {
			if (strlen($text) <= $length) {
				return $text;
			} else {
				$truncate = substr($text, 0, $length - strlen($ending));
			}
		}
		// if the words shouldn't be cut in the middle...
		if (!$exact) {
			// ...search the last occurance of a space...
			$spacepos = strrpos($truncate, ' ');
			if (isset($spacepos)) {
				// ...and cut the text in this position
				$truncate = substr($truncate, 0, $spacepos);
			}
		}
		// add the defined ending to the text
		$truncate .= $ending;
		if($considerHtml) {
			// close all unclosed html-tags
			foreach ($open_tags as $tag) {
				$truncate .= '</' . $tag . '>';
			}
		}
		return $truncate;
	}

	public function getIntroText() {
		return SiteConfig::current_site_config()->ESSearchResultsIntro;
	}

	public function getNoResultsText() {
		return SiteConfig::current_site_config()->ESSearchResultsNoResults;
	}

	public function getNoSearchKeywordText() {
		return SiteConfig::current_site_config()->ESSearchResultsNoText;
	}

	public function getSearchOfflineText() {
		return SiteConfig::current_site_config()->ESSearchResultsNotWorking;
	}

	public function getTitle() {
		return SiteConfig::current_site_config()->ESSearchResultsTitle;
	}

}