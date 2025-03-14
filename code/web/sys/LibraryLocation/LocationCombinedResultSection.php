<?php

require_once ROOT_DIR . '/sys/LibraryLocation/CombinedResultSection.php';

class LocationCombinedResultSection extends CombinedResultSection {
	public $__table = 'location_combined_results_section';    // table name
	public $locationId;

	static function getObjectStructure(): array {
		$location = new Location();
		$location->orderBy('displayName');
		if (!UserAccount::userHasPermission('Administer All Locations')) {
			$homeLibrary = Library::getPatronHomeLibrary();
			$location->libraryId = $homeLibrary->libraryId;
		}
		$location->find();
		$locationList = [];
		while ($location->fetch()) {
			$locationList[$location->locationId] = $location->displayName;
		}

		$structure = parent::getObjectStructure();
		$structure['locationId'] = [
			'property' => 'locationId',
			'type' => 'enum',
			'values' => $locationList,
			'label' => 'Location',
			'description' => 'The id of a location',
		];

		return $structure;
	}
}