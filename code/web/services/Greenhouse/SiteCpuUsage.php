<?php

require_once ROOT_DIR . '/sys/Greenhouse/AspenSite.php';
require_once ROOT_DIR . '/sys/Greenhouse/AspenSiteCpuUsage.php';
require_once ROOT_DIR . '/services/Admin/Admin.php';

class SiteCpuUsage extends Admin_Admin {
	function launch() {
		global $interface;
		$aspenSite = new AspenSite();
		$aspenSite->orderBy('name');
		$allSites = [];
		$aspenSite->find();
		$selectedSite = '';
		while ($aspenSite->fetch()) {
			$allSites[$aspenSite->id] = $aspenSite->name;
			if ($selectedSite == '') {
				$selectedSite = $aspenSite->id;
			}
		}
		$interface->assign('allSites', $allSites);

		if (!empty($_REQUEST['site'])) {
			$selectedSite = $_REQUEST['site'];
		}
		$interface->assign('selectedSite', $selectedSite);

		//Get stats
		if (!empty($selectedSite)) {
			$dataSeries = [];
			$columnLabels = [];

			$dataSeries['CPU Usage'] = [
				'borderColor' => 'rgba(255, 99, 132, 1)',
				'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
				'data' => [],
			];

			$aspenSiteCpuStats = new AspenSiteCpuUsage();
			$aspenSiteCpuStats->aspenSiteId = $selectedSite;
			$aspenSiteCpuStats->orderBy('timestamp');

			$aspenSiteCpuStats->find();
			while ($aspenSiteCpuStats->fetch()) {
				$columnLabel = date('m/d/y h:i', $aspenSiteCpuStats->timestamp);
				$columnLabels[] = $columnLabel;
				$dataSeries['CPU Usage']['data'][$aspenSiteCpuStats->timestamp] = $aspenSiteCpuStats->loadPerCpu * 100;
			}

			$interface->assign('columnLabels', $columnLabels);
			$interface->assign('dataSeries', $dataSeries);
		}


		$this->display('siteCpu.tpl', 'Aspen Site CPU Dashboard', '');
	}

	function getBreadcrumbs(): array {
		$breadcrumbs = [];
		$breadcrumbs[] = new Breadcrumb('/Greenhouse/Home', 'Greenhouse Home');
		$breadcrumbs[] = new Breadcrumb('/Greenhouse/Sites', 'Sites');
		$breadcrumbs[] = new Breadcrumb('', 'CPU Usage');
		return $breadcrumbs;
	}

	function getActiveAdminSection(): string {
		return 'greenhouse';
	}

	function canView(): bool {
		if (UserAccount::isLoggedIn()) {
			if (UserAccount::getActiveUserObj()->source == 'admin' && UserAccount::getActiveUserObj()->cat_username == 'aspen_admin') {
				return true;
			}
		}
		return false;
	}
}