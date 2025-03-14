<?php

require_once ROOT_DIR . '/Action.php';
require_once ROOT_DIR . '/services/Admin/ObjectEditor.php';
require_once ROOT_DIR . '/sys/Email/SendGridSetting.php';

class Admin_SendGridSettings extends ObjectEditor {
	function getObjectType(): string {
		return 'SendGridSetting';
	}

	function getToolName(): string {
		return 'SendGridSettings';
	}

	function getModule(): string {
		return 'Admin';
	}

	function getPageTitle(): string {
		return 'SendGrid Settings';
	}

	function getAllObjects($page, $recordsPerPage): array {
		$object = new SendGridSetting();
		$object->limit(($page - 1) * $recordsPerPage, $recordsPerPage);
		$this->applyFilters($object);
		$object->find();
		$objectList = [];
		while ($object->fetch()) {
			$objectList[$object->id] = clone $object;
		}
		return $objectList;
	}

	function getDefaultSort(): string {
		return 'id asc';
	}

	function canSort(): bool {
		return false;
	}

	function getObjectStructure(): array {
		return SendGridSetting::getObjectStructure();
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
		$breadcrumbs[] = new Breadcrumb('/Admin/Home#system_admin', 'System Administration');
		$breadcrumbs[] = new Breadcrumb('/Admin/SendGridSettings', 'Send Grid Settings');
		return $breadcrumbs;
	}

	function getActiveAdminSection(): string {
		return 'system_admin';
	}

	function canView(): bool {
		return UserAccount::userHasPermission('Administer SendGrid');
	}


}