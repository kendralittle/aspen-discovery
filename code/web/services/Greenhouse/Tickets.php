<?php

require_once ROOT_DIR . '/services/Admin/ObjectEditor.php';
require_once ROOT_DIR . '/sys/Support/Ticket.php';

class Greenhouse_Tickets extends ObjectEditor {

	function getBreadcrumbs(): array {
		$breadcrumbs = [];
		$breadcrumbs[] = new Breadcrumb('/Greenhouse/Home', 'Greenhouse Home');
		$breadcrumbs[] = new Breadcrumb('/Greenhouse/Tickets', 'Tickets');
		return $breadcrumbs;
	}

	function canView() {
		if (UserAccount::isLoggedIn()) {
			if (UserAccount::getActiveUserObj()->source == 'admin' && UserAccount::getActiveUserObj()->cat_username == 'aspen_admin') {
				return true;
			}
		}
		return false;
	}

	public function getFilterFields($structure) {
		$filterFields = parent::getFilterFields($structure);

		$filterFields['showClosedTickets'] = [
			'property' => 'showClosedTickets',
			'type' => 'checkbox',
			'label' => 'Show Closed Tickets',
			'description' => 'Whether or not closed tickets are shown',
			'readOnly' => true,
		];
		ksort($filterFields);
		return $filterFields;
	}

	function applyFilter(DataObject $object, string $fieldName, array $filter) {
		if ($fieldName == 'showClosedTickets') {
			if ($filter['filterValue'] == false) {
				$object->whereAdd('status <> "Closed"');
			}
		} else {
			parent::applyFilter($object, $fieldName, $filter);
		}
	}

	function getDefaultFilters(array $filterFields): array {
		return [
			'showClosedTickets' => [
				'fieldName' => 'showClosedTickets',
				'filterType' => 'checkbox',
				'filterValue' => false,
				'filterValue2' => null,
				'field' => $filterFields['showClosedTickets'],
			],
			'requestingPartner' => [
				'fieldName' => 'requestingPartner',
				'filterType' => 'enum',
				'filterValue' => 'all_values',
				'filterValue2' => null,
				'field' => $filterFields['requestingPartner'],
			],
		];
	}

	function getActiveAdminSection(): string {
		return 'greenhouse';
	}

	function getObjectType(): string {
		return 'Ticket';
	}

	function getModule(): string {
		return 'Greenhouse';
	}

	function getToolName(): string {
		return 'Tickets';
	}

	function getPageTitle(): string {
		return 'Tickets';
	}

	function getAllObjects($page, $recordsPerPage): array {
		$object = new Ticket();
		$object->limit(($page - 1) * $recordsPerPage, $recordsPerPage);
		$this->applyFilters($object);
		$object->orderBy($this->getSort());
		$object->find();
		$objectList = [];
		while ($object->fetch()) {
			$objectList[$object->id] = clone $object;
		}
		return $objectList;
	}

	function getObjectStructure(): array {
		return Ticket::getObjectStructure();
	}

	function getPrimaryKeyColumn(): string {
		return 'id';
	}

	function getIdKeyColumn(): string {
		return 'id';
	}

	function getDefaultSort(): string {
		return 'ticketId desc';
	}

	public function display($mainContentTemplate, $pageTitle, $sidebarTemplate = 'Development/development-sidebar.tpl', $translateTitle = true) {
		parent::display($mainContentTemplate, $pageTitle, $sidebarTemplate, $translateTitle);
	}
}