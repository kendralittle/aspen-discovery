<?php

require_once ROOT_DIR . '/sys/DB/DataObject.php';
class AspenLiDAQuickSearch extends DataObject
{
	public $__table = 'aspen_lida_quick_searches';
	public $id;
	public $weight;
	public $aspenLidaSettingId;
	public $searchTerm;
	public $label;

	static function getObjectStructure() : array {
		$structure = array(
			'id'           => array('property' => 'id', 'type'=> 'label', 'label'=> 'Id', 'description'=> 'The unique id'),
			'label'        => array('property' => 'label', 'type'=> 'text', 'label'=> 'Label', 'description'=> 'The label for quick search', 'required' => true),
			'searchTerm'   => array('property' => 'searchTerm', 'type'=> 'text', 'label'=> 'Search Term', 'description'=> 'The term to use for the quick search', 'required' => true),
		);
		return $structure;
	}
}