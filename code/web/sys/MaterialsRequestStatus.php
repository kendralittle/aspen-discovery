<?php

require_once ROOT_DIR . '/sys/DB/DataObject.php';

class MaterialsRequestStatus extends DataObject {
	public $__table = 'materials_request_status';   // table name

	public $id;
	public $description;
	public $isDefault;
	public $sendEmailToPatron;
	public $emailTemplate;
	public $isOpen;
	public $isPatronCancel;
	public $libraryId;

	public function getUniquenessFields(): array {
		return [
			'libraryId',
			'description',
		];
	}

	static function getObjectStructure(): array {
		$library = new Library();
		$library->orderBy('displayName');
		$homeLibrary = Library::getPatronHomeLibrary();
		if (is_null($homeLibrary)) {
			//User does not have a home library, this is likely an admin account.  Use the active library
			global $library;
			$homeLibrary = $library;
		}
		$library->libraryId = $homeLibrary->libraryId;

		$library->find();
		while ($library->fetch()) {
			$libraryList[$library->libraryId] = $library->displayName;
		}

		return [
			'id' => [
				'property' => 'id',
				'type' => 'label',
				'label' => 'Id',
				'description' => 'The unique id of the libary within the database',
			],
			'description' => [
				'property' => 'description',
				'type' => 'text',
				'size' => 80,
				'label' => 'Description',
				'description' => 'A unique name for the Status',
			],
			'isDefault' => [
				'property' => 'isDefault',
				'type' => 'checkbox',
				'label' => 'Default Status?',
				'description' => 'Whether or not this status is the default status to apply to new requests',
			],
			'isPatronCancel' => [
				'property' => 'isPatronCancel',
				'type' => 'checkbox',
				'label' => 'Set When Patron Cancels?',
				'description' => 'Whether or not this status should be set when the patron cancels their request',
			],
			'isOpen' => [
				'property' => 'isOpen',
				'type' => 'checkbox',
				'label' => 'Open Status?',
				'description' => 'Whether or not this status needs further processing',
			],
			'sendEmailToPatron' => [
				'property' => 'sendEmailToPatron',
				'type' => 'checkbox',
				'label' => 'Send Email To Patron?',
				'description' => 'Whether or not an email should be sent to the patron when this status is set',
			],
			'emailTemplate' => [
				'property' => 'emailTemplate',
				'type' => 'textarea',
				'rows' => 6,
				'cols' => 60,
				'label' => 'Email Template',
				'description' => 'The template to use when sending emails to the user',
				'hideInLists' => true,
			],
			'libraryId' => [
				'property' => 'libraryId',
				'type' => 'enum',
				'values' => $libraryList,
				'label' => 'Library',
				'description' => 'The id of a library',
			],
		];
	}

	public function okToExport(array $selectedFilters): bool {
		$okToExport = parent::okToExport($selectedFilters);
		if (in_array($this->libraryId, $selectedFilters['libraries'])) {
			$okToExport = true;
		}
		return $okToExport;
	}

	public function toArray($includeRuntimeProperties = true, $encryptFields = false): array {
		$return = parent::toArray($includeRuntimeProperties, $encryptFields);
		unset ($return['libraryId']);

		return $return;
	}

	public function getLinksForJSON(): array {
		$links = parent::getLinksForJSON();
		//library
		$allLibraries = Library::getLibraryListAsObjects(false);
		if (array_key_exists($this->libraryId, $allLibraries)) {
			$library = $allLibraries[$this->libraryId];
			$links['library'] = empty($library->subdomain) ? $library->ilsCode : $library->subdomain;
		}
		return $links;
	}

	public function loadEmbeddedLinksFromJSON($jsonData, $mappings, $overrideExisting = 'keepExisting') {
		parent::loadEmbeddedLinksFromJSON($jsonData, $mappings, $overrideExisting = 'keepExisting');

		if (isset($jsonData['library'])) {
			$allLibraries = Library::getLibraryListAsObjects(false);
			$subdomain = $jsonData['library'];
			if (array_key_exists($subdomain, $mappings['libraries'])) {
				$subdomain = $mappings['libraries'][$subdomain];
			}
			foreach ($allLibraries as $tmpLibrary) {
				if ($tmpLibrary->subdomain == $subdomain || $tmpLibrary->ilsCode == $subdomain) {
					$this->libraryId = $tmpLibrary->libraryId;
					break;
				}
			}
		}
	}
}