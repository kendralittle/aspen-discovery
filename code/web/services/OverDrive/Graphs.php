<?php

require_once ROOT_DIR . '/services/Admin/Admin.php';
require_once ROOT_DIR . '/sys/OverDrive/UserOverDriveUsage.php';
require_once ROOT_DIR . '/sys/OverDrive/OverDriveRecordUsage.php';
require_once ROOT_DIR . '/sys/OverDrive/OverDriveStats.php';

class OverDrive_Graphs extends Admin_Admin {
	function launch() {
		global $interface;
		$title = 'OverDrive Usage Graph';
		if (!empty($_REQUEST['instance'])) {
			$instanceName = $_REQUEST['instance'];
		} else {
			$instanceName = '';
		}

		$dataSeries = [];
		$columnLabels = [];

		$dataSeries['Total Usage'] = [
			'borderColor' => 'rgba(255, 99, 132, 1)',
			'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
			'data' => [],
		];
		$dataSeries['Unique Users'] = [
			'borderColor' => 'rgba(54, 162, 235, 1)',
			'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
			'data' => [],
		];
		$dataSeries['Records Used'] = [
			'borderColor' => 'rgba(255, 159, 64, 1)',
			'backgroundColor' => 'rgba(255, 159, 64, 0.2)',
			'data' => [],
		];
		$dataSeries['Total Holds'] = [
			'borderColor' => 'rgba(0, 255, 55, 1)',
			'backgroundColor' => 'rgba(0, 255, 55, 0.2)',
			'data' => [],
		];
		$dataSeries['Total Failed Holds'] = [
			'borderColor' => 'rgba(255, 55, 0, 1)',
			'backgroundColor' => 'rgba(255, 55, 0, 0.4)',
			'data' => [],
		];
		$dataSeries['Total Checkouts'] = [
			'borderColor' => 'rgba(154, 75, 244, 1)',
			'backgroundColor' => 'rgba(154, 75, 244, 0.2)',
			'data' => [],
		];
		$dataSeries['Total Failed Checkouts'] = [
			'borderColor' => 'rgba(75, 154, 244, 1)',
			'backgroundColor' => 'rgba(75, 154, 244, 0.4)',
			'data' => [],
		];
		$dataSeries['Total Renewals'] = [
			'borderColor' => 'rgba(255, 206, 86, 1)',
			'backgroundColor' => 'rgba(255, 206, 86, 0.2)',
			'data' => [],
		];
		$dataSeries['Total Early Returns'] = [
			'borderColor' => 'rgba(75, 192, 192, 1)',
			'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
			'data' => [],
		];
		$dataSeries['Total Holds Cancelled'] = [
			'borderColor' => 'rgba(153, 102, 255, 1)',
			'backgroundColor' => 'rgba(153, 102, 255, 0.2)',
			'data' => [],
		];
		$dataSeries['Total Holds Frozen'] = [
			'borderColor' => 'rgba(165, 42, 42, 1)',
			'backgroundColor' => 'rgba(165, 42, 42, 0.2)',
			'data' => [],
		];
		$dataSeries['Total Holds Thawed'] = [
			'borderColor' => 'rgba(50, 205, 50, 1)',
			'backgroundColor' => 'rgba(50, 205, 50, 0.2)',
			'data' => [],
		];
		$dataSeries['Total Downloads'] = [
			'borderColor' => 'rgba(205, 205, 50, 1)',
			'backgroundColor' => 'rgba(205, 205, 50, 0.4)',
			'data' => [],
		];
		$dataSeries['Total Previews'] = [
			'borderColor' => 'rgba(50, 205, 205, 1)',
			'backgroundColor' => 'rgba(50, 205, 205, 0.4)',
			'data' => [],
		];
		$dataSeries['Total Options Updates'] = [
			'borderColor' => 'rgba(220, 20, 60, 1)',
			'backgroundColor' => 'rgba(220, 20, 60, 0.4)',
			'data' => [],
		];
		$dataSeries['Total API Errors'] = [
			'borderColor' => 'rgba(220, 60, 20, 1)',
			'backgroundColor' => 'rgba(220, 60, 20, 0.2)',
			'data' => [],
		];
		$dataSeries['Total Connection Failures'] = [
			'borderColor' => 'rgba(255, 165, 0, 1)',
			'backgroundColor' => 'rgba(255, 165, 0, 0.2)',
			'data' => [],
		];
		$userUsage = new UserOverDriveUsage();
		$userUsage->groupBy('year, month');
		if (!empty($instanceName)) {
			$userUsage->instance = $instanceName;
		}
		$userUsage->selectAdd();
		$userUsage->selectAdd('year');
		$userUsage->selectAdd('month');
		$userUsage->selectAdd('COUNT(*) as numUsers');
		$userUsage->selectAdd('SUM(usageCount) as sumUsage');
		$userUsage->orderBy('year, month');
		$userUsage->find();
		while ($userUsage->fetch()) {
			$curPeriod = "{$userUsage->month}-{$userUsage->year}";
			$columnLabels[] = $curPeriod;
			/** @noinspection PhpUndefinedFieldInspection */
			$dataSeries['Total Usage']['data'][$curPeriod] = $userUsage->sumUsage;
			/** @noinspection PhpUndefinedFieldInspection */
			$dataSeries['Unique Users']['data'][$curPeriod] = $userUsage->numUsers;

			//Make sure we have default values for all the other series
			$dataSeries['Records Used']['data'][$curPeriod] = 0;
			$dataSeries['Total Holds']['data'][$curPeriod] = 0;
			$dataSeries['Total Failed Holds']['data'][$curPeriod] = 0;
			$dataSeries['Total Checkouts']['data'][$curPeriod] = 0;
			$dataSeries['Total Failed Checkouts']['data'][$curPeriod] = 0;
			$dataSeries['Total Early Returns']['data'][$curPeriod] = 0;
			$dataSeries['Total Renewals']['data'][$curPeriod] = 0;
			$dataSeries['Total Holds Cancelled']['data'][$curPeriod] = 0;
			$dataSeries['Total Holds Frozen']['data'][$curPeriod] = 0;
			$dataSeries['Total Holds Thawed']['data'][$curPeriod] = 0;
			$dataSeries['Total Downloads']['data'][$curPeriod] = 0;
			$dataSeries['Total Previews']['data'][$curPeriod] = 0;
			$dataSeries['Total Options Updates']['data'][$curPeriod] = 0;
			$dataSeries['Total API Errors']['data'][$curPeriod] = 0;
			$dataSeries['Total Connection Failures']['data'][$curPeriod] = 0;
		}

		//Load Record Stats
		$stats = new OverDriveStats();
		$stats->groupBy('year, month');
		if (!empty($instanceName)) {
			$stats->instance = $instanceName;
		}
		$stats->selectAdd();
		$stats->selectAdd('year');
		$stats->selectAdd('month');
		$stats->selectAdd('SUM(numHoldsPlaced) as numHoldsPlaced');
		$stats->selectAdd('SUM(numFailedHolds) as numFailedHolds');
		$stats->selectAdd('SUM(numCheckouts) as numCheckouts');
		$stats->selectAdd('SUM(numFailedCheckouts) as numFailedCheckouts');
		$stats->selectAdd('SUM(numEarlyReturns) as numEarlyReturns');
		$stats->selectAdd('SUM(numRenewals) as numRenewals');
		$stats->selectAdd('SUM(numHoldsCancelled) as numHoldsCancelled');
		$stats->selectAdd('SUM(numHoldsFrozen) as numHoldsFrozen');
		$stats->selectAdd('SUM(numHoldsThawed) as numHoldsThawed');
		$stats->selectAdd('SUM(numDownloads) as numDownloads');
		$stats->selectAdd('SUM(numPreviews) as numPreviews');
		$stats->selectAdd('SUM(numOptionsUpdates) as numOptionsUpdates');
		$stats->selectAdd('SUM(numApiErrors) as numApiErrors');
		$stats->selectAdd('SUM(numConnectionFailures) as numConnectionFailures');
		$stats->orderBy('year, month');
		$stats->find();
		while ($stats->fetch()) {
			$curPeriod = "{$stats->month}-{$stats->year}";
			$dataSeries['Total Holds']['data'][$curPeriod] = $stats->numHoldsPlaced;
			$dataSeries['Total Failed Holds']['data'][$curPeriod] = $stats->numFailedHolds;
			$dataSeries['Total Checkouts']['data'][$curPeriod] = $stats->numCheckouts;
			$dataSeries['Total Failed Checkouts']['data'][$curPeriod] = $stats->numFailedCheckouts;
			$dataSeries['Total Early Returns']['data'][$curPeriod] = $stats->numEarlyReturns;
			$dataSeries['Total Renewals']['data'][$curPeriod] = $stats->numRenewals;
			$dataSeries['Total Holds Cancelled']['data'][$curPeriod] = $stats->numHoldsCancelled;
			$dataSeries['Total Holds Frozen']['data'][$curPeriod] = $stats->numHoldsFrozen;
			$dataSeries['Total Holds Thawed']['data'][$curPeriod] = $stats->numHoldsThawed;
			$dataSeries['Total Downloads']['data'][$curPeriod] = $stats->numDownloads;
			$dataSeries['Total Previews']['data'][$curPeriod] = $stats->numPreviews;
			$dataSeries['Total Options Updates']['data'][$curPeriod] = $stats->numOptionsUpdates;
			$dataSeries['Total API Errors']['data'][$curPeriod] = $stats->numApiErrors;
			$dataSeries['Total Connection Failures']['data'][$curPeriod] = $stats->numConnectionFailures;
		}

		$recordUsage = new OverDriveRecordUsage();
		$recordUsage->groupBy('year, month');
		if (!empty($instanceName)) {
			$recordUsage->instance = $instanceName;
		}
		$recordUsage->selectAdd();
		$recordUsage->selectAdd('year');
		$recordUsage->selectAdd('month');
		$recordUsage->selectAdd('COUNT(*) as numRecordsUsed');
		$recordUsage->selectAdd('SUM(timesHeld) as numHoldsPlaced');
		$recordUsage->selectAdd('SUM(timesCheckedOut) as numCheckouts');
		$recordUsage->orderBy('year, month');
		$recordUsage->find();
		while ($recordUsage->fetch()) {
			$curPeriod = "{$stats->month}-{$stats->year}";
			$dataSeries['Records Used']['data'][$curPeriod] = $recordUsage->numRecordsUsed;
			$dataSeries['Total Holds']['data'][$curPeriod] = $recordUsage->numHoldsPlaced;
			$dataSeries['Total Checkouts']['data'][$curPeriod] = $recordUsage->numCheckouts;
		}

		$interface->assign('columnLabels', $columnLabels);
		$interface->assign('dataSeries', $dataSeries);

		$interface->assign('graphTitle', $title);
		$this->display('../Admin/usage-graph.tpl', $title);
	}

	function getBreadcrumbs(): array {
		$breadcrumbs = [];
		$breadcrumbs[] = new Breadcrumb('/Admin/Home', 'Administration Home');
		$breadcrumbs[] = new Breadcrumb('/Admin/Home#overdrive', 'OverDrive');
		$breadcrumbs[] = new Breadcrumb('/OverDrive/Dashboard', 'Usage Dashboard');
		$breadcrumbs[] = new Breadcrumb('', 'Usage Graph');
		return $breadcrumbs;
	}

	function getActiveAdminSection(): string {
		return 'overdrive';
	}

	function canView(): bool {
		return UserAccount::userHasPermission([
			'View System Reports',
			'View Dashboards',
		]);
	}
}