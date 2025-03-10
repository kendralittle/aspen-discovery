<?php

require_once ROOT_DIR . '/Action.php';
require_once ROOT_DIR . '/services/Admin/ObjectEditor.php';
require_once ROOT_DIR . '/sys/LocalEnrichment/BadWord.php';

class Admin_BadWords extends ObjectEditor {

	function getObjectType(): string {
		return 'BadWord';
	}

	function getToolName(): string {
		return 'BadWords';
	}

	function getPageTitle(): string {
		return 'Bad Words List';
	}

	function canDelete() {
		return UserAccount::userHasPermission(['Administer Bad Words']);
	}

	function getAllObjects($page, $recordsPerPage): array {
		$object = new BadWord();
		$object->orderBy($this->getSort());
		$this->applyFilters($object);
		$object->limit(($page - 1) * $recordsPerPage, $recordsPerPage);
		$object->find();
		$list = [];
		while ($object->fetch()) {
			$list[$object->id] = clone $object;
		}
		return $list;
	}

	function getDefaultSort(): string {
		return 'word asc';
	}

	function getObjectStructure(): array {
		return BadWord::getObjectStructure();
	}

	function getPrimaryKeyColumn(): string {
		return 'id';
	}

	function getIdKeyColumn(): string {
		return 'id';
	}

	function getInstructions(): string {
		return '';
	}

	function getBreadcrumbs(): array {
		$breadcrumbs = [];
		$breadcrumbs[] = new Breadcrumb('/Admin/Home', 'Administration Home');
		$breadcrumbs[] = new Breadcrumb('/Admin/Home#local_enrichment', 'Local Enrichment');
		$breadcrumbs[] = new Breadcrumb('/Admin/BadWords', 'Bad Words List');
		return $breadcrumbs;
	}

	function getActiveAdminSection(): string {
		return 'local_enrichment';
	}

	function canView(): bool {
		return UserAccount::userHasPermission(['Administer Bad Words']);
	}
}