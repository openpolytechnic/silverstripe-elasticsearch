<?php

class ESPageType extends ESAbstractType {

	protected $_type = 'sspage';
	protected $_data;

	public function getMappingProps() {
		$props = array();
		$props[self::$_ID_FIELD] = array(
			'type' => 'integer',
			'index' => 'not_analyzed'
		);
		$props['SS_ID'] = array(
			'type' => 'integer',
			'index' => 'not_analyzed'
		);
		$props['ClassName'] = array(
			'type' => 'string',
			'fielddata' => true
		);
		$props['Title'] = array(
			'type' => 'string'
		);
		$props['MenuTitle'] = array(
			'type' => 'string'
		);
		$props['Content'] = array(
			'type' => 'text'
		);
		$props['Link'] = array(
			'type' => 'string',
			'index' => 'not_analyzed'
		);
		$props['Created'] = array(
			'type' => 'date',
			'format' => 'YYYY-MM-dd HH:mm:ss',
			'index' => 'not_analyzed'
		);
		$props['LastEdited'] = array(
			'type' => 'date',
			'format' => 'YYYY-MM-dd HH:mm:ss',
			'index' => 'not_analyzed'
		);
		$props['ScoreBoost'] = array(
			'type' => 'float',
			'index' => 'not_analyzed'
		);
		$ExtraProperites = Config::inst()->get('ESSearchProperties', 'Default');
		foreach ($ExtraProperites as $field => $def){
			$props[$field] = $def;
		}
		$DataObjectProperites = Config::inst()->get('ESSearchProperties', 'DataObject');

		foreach ($DataObjectProperites as $class => $fields){
			foreach ($fields as $field => $def) {
				$props[$class.'_'.$field] = $def;
			}
		}
		return $props;
	}

	public function prepareData($page) {
		$data = array();
		$data[self::$_ID_FIELD] = $page->ID;
		$data['SS_ID'] = $page->ID;
		$data['ClassName'] = $page->ClassName;
		$data['LastEdited'] = $page->LastEdited;
		$data['Created'] = $page->Created;
		$data['Title'] = $page->Title;
		$data['MenuTitle'] = $page->MenuTitle ? $page->MenuTitle : $page->Title;
		$data['Link'] = $page->Link();
		$data['ScoreBoost'] = $page->ESScoreBoost;
		if($data['ScoreBoost'] == 0){
			$data['ScoreBoost'] = 1;
		}
		if(method_exists($page, "updateScoreBoost")){
			$data['ScoreBoost'] = $page->updateScoreBoost($data['ScoreBoost']);
		}
		$data['Content'] = self::cleanString($page->Content);
		$ExtraProperites = Config::inst()->get('ESSearchProperties', 'Default');
		foreach ($ExtraProperites as $field => $def){
			if(!isset($def['type']) || $def['type'] == 'string' || $def['type'] == 'text') {
				$data[$field] = self::convertToString(self::getValue($page, $field));
			}
			else {
				$data[$field] = self::getValue($page, $field);
			}
		}
		$DataObjectProperites = Config::inst()->get('ESSearchProperties', 'DataObject');
		if(isset($DataObjectProperites[$page->ClassName])) {
			foreach ($DataObjectProperites[$page->ClassName] as $field => $def) {
				if (!isset($def['type']) || $def['type'] == 'string' || $def['type'] == 'text' ) {
					$data[$page->ClassName . '_' . $field] = self::convertToString(self::getValue($page, $field));
				} else {
					$data[$page->ClassName . '_' . $field] = self::getValue($page, $field);
				}
			}
		}
		$Props = $this->getMappingProps();
		$DataObjectMapping = Config::inst()->get('ESSearchMapping', 'DataObject');
		if(isset($DataObjectMapping[$page->ClassName])) {
			foreach ($DataObjectMapping[$page->ClassName] as $field => $mappings) {
				if(!isset($data[$field]) && !isset($data[$page->ClassName.'_'.$field])){
					continue;
				}

				$key = $field;
				if(isset($data[$page->ClassName.'_'.$field])){
					$key = $page->ClassName.'_'.$field;
				}

				$type = 'unknow';
				$content = array();
				if(isset($Props[$page->ClassName.'_'.$field])
					&& isset($Props[$page->ClassName.'_'.$field]['type'])) {
					$type = $Props[$page->ClassName.'_'.$field]['type'];
				}
				elseif (isset($Props[$field])
					&& isset($Props[$field]['type'])) {
					$type = $Props[$field]['type'];
				}

				foreach ($mappings as $mfield) {
					if($type == 'string' || $type == 'text') {
						$content[] = self::convertToString(self::getValue($page, $mfield));
					}
					else {
						$content[] = self::getValue($page, $mfield);
					}
				}
				if($type == 'string' || $type == 'text') {
					$data[$key] = implode(' ', $content);
				}
				else {
					$data[$key] = implode('', $content);
				}
			}
		}
		$this->_data = $data;
	}

	public function indexData($createIndexIfMissing = false) {
		if (!isset($this->_data)) {
			return;
		}
		try {
			$pageDocument = new \Elastica\Document($this->_data[self::$_ID_FIELD], $this->_data);
			$this->_eType->addDocument($pageDocument);
			$this->_eClient->refreshIndex();
		} catch (Elastica\Exception\ClientException $e) {
			return Debug::log($e->getMessage());
		} catch (Exception $e) {
			return Debug::log($e->getMessage());
		}
	}


	private static function getValue($object, $field) {
		if(isset($object->{$field})){
			return $object->{$field};
		}
		if(method_exists($object, $field)){
			return $object->{$field}();
		}
		if(method_exists($object, 'get'.$field)){
			$field = 'get'.$field;
			return $object->{$field}();
		}
		return null;
	}

	private static function convertToString($object) {
		if(!isset($object)) {
			return null;
		}
		if(is_string($object) || is_bool($object) || is_string($object)){
			return self::cleanString($object);
		}
		if($object instanceof HTMLText || method_exists($object, 'forTemplate')){
			return self::cleanString($object->forTemplate());
		}
		return self::cleanString(serialize($object));
	}

	public static function cleanString($Content) {
		return trim(preg_replace('/\s+/', ' ', strip_tags($Content)));
	}

}