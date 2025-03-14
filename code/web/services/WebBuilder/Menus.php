<?php
require_once ROOT_DIR . '/services/Admin/ObjectEditor.php';
require_once ROOT_DIR . '/sys/WebBuilder/WebBuilderMenu.php';

class WebBuilder_Menus extends ObjectEditor {
	function getObjectType(): string {
		return 'WebBuilderMenu';
	}

	function getToolName(): string {
		return 'Menus';
	}

	function getModule(): string {
		return 'WebBuilder';
	}

	function getPageTitle(): string {
		return 'WebBuilder Menus';
	}

	function getAllObjects($page, $recordsPerPage): array {
		global $library;
		$object = new WebBuilderMenu();
		$object->parentMenuId = -1;
		$object->libraryId = $library->libraryId;
		$this->applyFilters($object);
		$object->orderBy($this->getSort());
		$object->limit(($page - 1) * $recordsPerPage, $recordsPerPage);
		$object->find();
		$objectList = [];
		while ($object->fetch()) {
			$objectList[$object->id] = clone $object;
			$subMenu = new WebBuilderMenu();
			$subMenu->parentMenuId = $object->id;
			$subMenu->libraryId = $library->libraryId;
			$subMenu->orderBy($this->getSort());
			$subMenu->find();
			while ($subMenu->fetch()) {
				$subMenu->label = "--- " . $subMenu->label;
				$objectList[$subMenu->id] = clone $subMenu;
			}
		}
		return $objectList;
	}

	function getDefaultSort(): string {
		return 'weight asc';
	}

	function canSort(): bool {
		return false;
	}

	function getObjectStructure(): array {
		return WebBuilderMenu::getObjectStructure();
	}

	function getPrimaryKeyColumn(): string {
		return 'id';
	}

	function getIdKeyColumn(): string {
		return 'id';
	}

	function getAdditionalObjectActions($existingObject): array {
		return [];
	}

	function getInstructions(): string {
		return '';
	}

	function getBreadcrumbs(): array {
		$breadcrumbs = [];
		$breadcrumbs[] = new Breadcrumb('/Admin/Home', 'Administration Home');
		$breadcrumbs[] = new Breadcrumb('/Admin/Home#web_builder', 'Web Builder');
		$breadcrumbs[] = new Breadcrumb('/WebBuilder/Menus', 'Menus');
		return $breadcrumbs;
	}

	function canView(): bool {
		return UserAccount::userHasPermission([
			'Administer All Menus',
			'Administer Library Menus',
		]);
	}

	function getActiveAdminSection(): string {
		return 'web_builder';
	}
}