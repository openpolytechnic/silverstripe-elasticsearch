<?php

abstract class ESAbstractType {

	static protected $_ID_FIELD = 'id';
	// Array of items and data to be indexed / updated
	protected $_feedArray = array();
	// Type used in elasticsearch
	protected $_type = NULL;
	/* @var $_eClient Elastica\Client */
	protected $_eClient;
	/* @var $_eIndex Elastica\Index */
	protected $_eIndex;
	/* @var $_eType Elastica\Type */
	protected $_eType;
	/* @var $_eMap Elastica\Type\Mapping */
	protected $_eMapping;

	function __construct() {
		try {
			$this->_eClient = new SSElasticSearch();
			$this->_eIndex = $this->_eClient->getElasticaIndex();

			if (!$this->_type) {
				Debug::log('Type not set on current class "' . get_class($this) . '"');
				user_error('Type not set on current class "' . get_class($this) . '"', E_USER_ERROR);
			}

			$this->_eType = $this->_eIndex->getType($this->_type);
			if (!$this->indexTypeExists()) {
				$this->_eMap = new \Elastica\Type\Mapping($this->getType());
				$this->_eMap->setProperties($this->getMappingProps());
				$this->_eMap->send();
			}
		} catch (\Elastica\Exception\ClientException $e) {
			Debug::log($e->getMessage());
		} catch (Exception $e) {
			Debug::log($e->getMessage());
		}
	}
	
	/**
	 * Checks whether the current type exists in the current index
	 * @return boolean 
	 */

	public function indexTypeExists() {
		$exists = false;
		$map = $this->_eIndex->getMapping();
		if (array_key_exists($this->_type, $map)) {
			$exists = true;
		}
		return $exists;
	}

	/**
	 *
	 * @return Elastica\Client
	 */
	public function getClient() {
		return $this->_eClient;
	}

	/**
	 *
	 * @return Elastica\Index
	 */
	public function getIndex() {
		return $this->_eIndex;
	}

	/**
	 *
	 * @return Elastica\Type
	 */
	public function getType() {
		return $this->_eType;
	}

	/**
	 *
	 * @return Elastica\Type\Mapping
	 */
	public function getMapping() {
		if (!isset($this->_eMapping)) {
			try {
				$this->_eMapping = new \Elastica\Type\Mapping($this->getType());
			} catch (\Elastica\Exception\ClientException $e) {
				return Debug::log($e->getMessage());
			} catch (Exception $e) {
				return Debug::log($e->getMessage());
			}
		}
		return $this->_eMapping;
	}

	public function getTypeName() {
		return $this->_type;
	}

	public function getIndexName() {
		return $this->getIndex()->getName();
	}

	public function deleteByID($id) {
		try {
			$this->_eType->deleteByID((int)$id);
			$this->_eClient->refreshIndex();
		}
		catch(\Elastica\Exception\NotFoundException $e) {
			//Ingnore if the ID do not exist
		}
	}

	/**
	 * Returns array for type mapping 
	 */
	abstract public function getMappingProps();

	/**
	 * Method for actual indexing 
	 */
	abstract public function indexData();
}