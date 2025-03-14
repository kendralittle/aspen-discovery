<?php


class Module extends DataObject {
	public $__table = 'modules';
	public $id;
	public $name;
	public $enabled;
	public $indexName;
	public $backgroundProcess;
	public $logClassPath;
	public $logClassName;
	public $settingsClassPath;
	public $settingsClassName;

	static function getObjectStructure(): array {
		return [
			'id' => [
				'property' => 'id',
				'type' => 'label',
				'label' => 'Id',
				'description' => 'The unique id',
			],
			'name' => [
				'property' => 'name',
				'type' => 'text',
				'label' => 'Name',
				'description' => 'The name of the module',
				'canBatchUpdate' => false,
				'readOnly' => true,
			],
			'enabled' => [
				'property' => 'enabled',
				'type' => 'checkbox',
				'label' => 'Enabled?',
				'description' => 'Whether or not the module is enabled',
				'default' => '0',
			],
			'indexName' => [
				'property' => 'indexName',
				'type' => 'text',
				'label' => 'Index Name',
				'description' => 'The name of the associated solr index if any',
				'canBatchUpdate' => false,
				'readOnly' => true,
			],
			'backgroundProcess' => [
				'property' => 'backgroundProcess',
				'type' => 'text',
				'label' => 'Background Process',
				'description' => 'The name of the background process being run if any',
				'canBatchUpdate' => false,
				'readOnly' => true,
			],
			'logClassPath' => [
				'property' => 'logClassPath',
				'type' => 'text',
				'label' => 'Log Class Path',
				'description' => 'The path to the class where logs are stored',
				'canBatchUpdate' => false,
				'readOnly' => true,
			],
			'logClassName' => [
				'property' => 'logClassName',
				'type' => 'text',
				'label' => 'Log Class Name',
				'description' => 'The name of the class that does logging',
				'canBatchUpdate' => false,
				'readOnly' => true,
			],
			'settingsClassPath' => [
				'property' => 'settingsClassPath',
				'type' => 'text',
				'label' => 'Settings Class Path',
				'description' => 'The path of the class that stores settings for the module',
				'canBatchUpdate' => false,
				'readOnly' => true,
			],
			'settingsClassName' => [
				'property' => 'settingsClassName',
				'type' => 'text',
				'label' => 'Settings Class Name',
				'description' => 'The name of the class that stores settings for the module',
				'canBatchUpdate' => false,
				'readOnly' => true,
			],
		];
	}
}