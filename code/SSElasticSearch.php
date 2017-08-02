<?php

/*
 *  wrapper class for all interaction from ss <-> elastica
 */

class SSElasticSearch {

	protected $eClient;
	protected $eIndex;
	/* @var $_eIndex Elastica\Index */
	protected $_defaultAnalysis = array(
		"analyzer" => array(
			"edgengram" => array(
				"type" => "custom",
				"tokenizer" => "left_tokenizer",
				"filter" => array("standard", "lowercase", "stop")
			),
			"ngram" => array(
				"type" => "custom",
				"tokenizer" => "n_tokenizer",
				"filter" => array("standard", "lowercase", "stop")
			)
		),
		"tokenizer" => array(
			"left_tokenizer" => array(
				"type" => "edgeNGram",
				"side" => "front",
				"max_gram" => 20
			),
			"n_tokenizer" => array(
				"type" => "nGram",
				"max_gram" => 20
			)
		)
	);

	public function __construct() {
		return $this->getElasticaClient();
	}

	public function getElasticaClient() {
		if (!isset($this->eClient)) {
			try {
				$siteConfig = SiteConfig::current_site_config();
				$params = array();
				if(isset($siteConfig->ESAPIEndPoint) && trim($siteConfig->ESAPIEndPoint) != '') {
					$params['url'] = $siteConfig->ESAPIEndPoint;
				}
				else {
					$host = $siteConfig->ESHost;
					$port = $siteConfig->ESPort;
					$transport = $siteConfig->ESTransport;

					if ($host) {
						$params['host'] = $host;
					}
					if ($port) {
						$params['port'] = $port;
					}
					if ($transport) {
						$params['transport'] = $transport;
					}
				}
				$this->eClient = new \Elastica\Client(array(
					'url' => $siteConfig->ESAPIEndPoint
				));

			} catch (\Elastica\Exception\ClientException $e) {
				return Debug::log($e->getMessage());
			} catch (Exception $e) {
				return Debug::log($e->getMessage());
			}
		}
		return $this->eClient;
	}

	public function getElasticaIndex() {
		$siteConfig = SiteConfig::current_site_config();
		$indexName = $siteConfig->ESIndexName;
		if (!$indexName) {
			Debug::log('User error: Index name not set');
			return user_error('User error: Index name not set', E_USER_ERROR);
		}
		$domain = parse_url(@Director::absoluteURL(), PHP_URL_HOST);
		if($domain){
			$indexName = str_replace('.', '_', $domain).'_'.$indexName;
		}
		$this->eIndex = $this->getElasticaClient()->getIndex($indexName);
		// Check index exists, else create it and set default settings
		try {
			if (!$this->eIndex->exists()) {
				$this->eIndex->create($this->getIndexSettings());
			}
		} catch (\Elastica\Exception\ClientException $e) {
			Debug::log($e->getMessage());
		} catch (Exception $e) {
			Debug::log($e->getMessage());
		}

		return $this->eIndex;
	}

	public function indexExists($indexName) {
		$index = $this->getElasticaClient()->getIndex($indexName);
		$exists = false;
		if ($index->exists()) {
			$exists = true;
		}
		return $exists;
	}

	public function refreshIndex() {
		$index = $this->getElasticaIndex();
		$response = '';
		try {
			$response = $index->refresh();
		} catch (\Elastica\Exception\ClientException $e) {
			return Debug::log($e->getMessage());
			//throw new \Elastica\Exception\ClientException($e->getError());
		} catch (Exception $e) {
			return Debug::log($e->getMessage());
		}
		return $response;
	}

	/**
	 * Deletes current index
	 * 
	 * @return Elastica\Response
	 * @throws \Elastica\Exception\ClientException
	 * @throws Mage_Core_Exception
	 */
	public function deleteIndex() {
		try {
			$index = $this->getElasticaIndex();
			$response = $index->delete();
		} catch (\Elastica\Exception\ClientException $e) {
			$message = $e->getMessage();
			Debug::log($message);
			return user_error($message, E_USER_ERROR);
		} catch (Exception $e) {
			Debug::log($e->getMessage());
			return user_error('Error deleting index', E_USER_ERROR);
		}
		return $response;
	}

	/**
	 * Gets settings for the index. Uses a combination of the default settings in
	 * $_defaultAnalysis
	 * 
	 * @return array 
	 */
	public function getIndexSettings() {
		$this->_settings = array(
			"analysis" => $this->_defaultAnalysis
		);
		$siteConfig = SiteConfig::current_site_config();
		$customSettings = $siteConfig->ESIndexCustomSettings;
		if ($customSettings) {
			$this->_processCustomSettings();
		}
		$this->_settings = array("settings" => $this->_settings);
		return $this->_settings;
	}

	/**
	 * Get type in current index by name
	 * 
	 * @param string $name
	 * @return Elastica\Type
	 */
	public function getIndexType($name) {
		$index = $this->getElasticaIndex();
		$type = false;
		try {
			$type = $index->getType((string) $name);
		} catch (\Elastica\Exception\ClientException $e) {
			Debug::log($e->getMessage());
			//throw new \Elastica\Exception\ClientException($e->getError());
		} catch (Exception $e) {
			Debug::log($e->getMessage());
		}
		return $type;
	}

	/**
	 * Search in the set indices, types
	 *
	 * @param mixed $query
	 * @param int  $from OPTIONAL
	 * @param int   $limit OPTIONAL
	 * @return Elastica\ResultSet
	 */
	public function search($query, $filters = array(),
						   $from = null, $limit = null,
						   $sort = array('_score' => 'desc', 'LastEdited' => 'desc')) {

		$eQuery = new \Elastica\Query(new \Elastica\Query\QueryString($query));
		//$eQuery->addScriptField()
		$siteConfig = SiteConfig::current_site_config();

		if (is_null($limit)) {
			$limit = (int) $siteConfig->ESSearchResultsLimit;
		}
		$eQuery->setLimit($limit);
		if (!is_null($from)) {
			$eQuery->setFrom((int) $from);
		}
		//$eQuery->setRawQuery(array("match" => $query));
		$eQuery->setSort($sort);
		$eQuery->setHighlight($this->getHighlightSetting());

		$functionScoreQuery = new \Elastica\Query\FunctionScore();
		//$ScoreScript = new \Elastica\Script\Script('_score + 1');
		$functionScoreQuery->setParam('query', $eQuery->getQuery());

		$functionScoreQuery->addFunction('script_score', array(
			'script' => '_score + doc[\'ScoreBoost\'].value'
		));
		/*
		$functionScoreQuery->addFunction('script_score', array(
			'script' => '_score - 1'
		));*/
		$eQuery->setQuery($functionScoreQuery);

		try {
			$index = $this->getElasticaIndex()->getName();
			$path = $index . '/_search';
			Debug::dump($eQuery->toArray());
			$response = $this->getElasticaClient()->request($path, Elastica\Request::GET, $eQuery->toArray());
			$rset = new \Elastica\ResultSet($response, $eQuery);
			return $rset;
			//  return $this->processResultSet($rset);
		} catch (Exception $e) {
			Debug::dump($e->getMessage());
			return Debug::log($e->getMessage());
		}
	}

	public function optimizeIndex() {
		try {
			$index = $this->getElasticaIndex()->getName();
			$path = $index . '/_optimize';
			$response = $this->getElasticaClient()->request($path, Elastica\Request::POST);
		} catch (Exception $e) {
			return Debug::log($e->getMessage());
		}
	}

	/**
	 * Processes any custom settings set in the admin backend. If you prefix any key
	 * with a '-' it will unset it (useful for removing default settings 
	 * like in $_defaultAnalysis) or if you define the same key it will override it
	 * 
	 *  
	 * e.g. if your custom settings contained:
	 * 
	 * {
	 * 	 "analyzer" : {
	 *	 "ngram" : {
	 *	   "filter" : ["customFilter", "stop", "standard"]
	 *	 }
	 *   },
	 *   "tokenizer" : {
	 *	 "-left_tokenizer"
	 *   },
	 *   "newSetting" : {...}
	 * }
	 * 
	 * "ngram"'s setting "filter" inside $_defaultAnalysis would get overriden
	 * leaving all its other settings intact
	 * 
	 * The setting "left_tokenizer" inside $_defaultAnalysis would be completely 
	 * unset leaving the rest of the settings inside "tokenizer" intact
	 * 
	 * "newSettings" would be added including any subsettings
	 * 
	 */
	/*
	protected function _processCustomSettings() {

		$siteConfig = SiteConfig::current_site_config();
		$customSettings = $siteConfig->ESIndexCustomSettings;
		if ($customSettings) {
			$customSettings = json_decode($customSettings, TRUE);
			foreach ($customSettings as $name => $options) {
				// Remove field if name is pre-fixed with '-'
				if ($string = $this->_stringCheckForUnset($name)) {
					unset($this->_settings[$string]);
					continue;
				}
				// Prevent default analysis from getting nuked if user wants to add custom options in analysis
				if ($name == 'analysis') {
					foreach ($options as $optName => $optSettings) {
						if ($string = $this->_stringCheckForUnset($optName)) {
							unset($this->_settings[$name][$string]);
							continue;
						}
						foreach ($optSettings as $subName => $subOptions) {
							if ($string = $this->_stringCheckForUnset($subName)) {
								unset($this->_settings[$name][$optName][$string]);
								continue;
							}
							$this->_settings[$name][$optName][$subName] = $subOptions;
						}
					}
				} else {
					$this->_settings[$name] = $options;
				}
			}
		}
	}
	*/

	/**
	 * Takes a string and determines if it is prefixed with a '-'
	 * 
	 * e.g. '-Analyzer' returns TRUE
	 * 		'Analyzer' returns FALSE
	 * 
	 * @param string $string
	 * @param array $exceptions
	 * @return FALSE | string
	 */
	protected function _stringCheckForUnset($string, $exceptions = array('type')) {
		$string = trim($string);
		// If first character is '-'
		if (strstr($string, '-', true) === '') {
			// Remove first character form $string so we can 
			// match in original array and unset
			$real_string = substr($string, 1);
			if (!empty($exceptions)) {
				foreach ($exceptions as $exception) {
					// Not allowed to unset 'type' required by elasticsearch
					if ($real_string == $exception) {
						return FALSE;
					}
				}
			}
			return $real_string;
		}

		return FALSE;
	}

	protected function getHighlightSetting() {
		$highlightSetting = array("require_field_match" => false);
		if($fields = Config::inst()->get('ESSearchHighlight', 'Fields')) {
			$highlightSetting["fields"] = array();
			foreach($fields as $key => $value){
				if(is_array($value)){
					$highlightSetting["fields"][$key] = (object)$value;
				}
				else {
					$highlightSetting["fields"][$value] = (object)array();
				}
			}
			if (Config::inst()->get('ESSearchHighlight', 'StartTag')) {
				$tags = Config::inst()->get('ESSearchHighlight', 'StartTag');
				if(is_array($tags)) {
					$highlightSetting["pre_tags"] = $tags;
				}
				else {
					$highlightSetting["pre_tags"] = array($tags);
				}
			}
			if (Config::inst()->get('ESSearchHighlight', 'EndTag')) {
				$tags = Config::inst()->get('ESSearchHighlight', 'EndTag');
				if(is_array($tags)) {
					$highlightSetting["post_tags"] = $tags;
				}
				else {
					$highlightSetting["post_tags"] = array($tags);
				}

			}
		}
		return $highlightSetting;
	}
}