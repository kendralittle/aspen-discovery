<?php
require_once __DIR__ . '/../bootstrap.php';

//Update all tickets based on status
require_once ROOT_DIR . '/sys/Support/TicketStatusFeed.php';
require_once ROOT_DIR . '/sys/Support/TicketComponentFeed.php';
require_once ROOT_DIR . '/sys/Support/Ticket.php';
require_once ROOT_DIR . '/sys/Greenhouse/GreenhouseSettings.php';
require_once ROOT_DIR . '/sys/Development/ComponentTicketLink.php';

$greenhouseSettings = new GreenhouseSettings();
$rtAuthToken = null;
$baseRtUrl = null;
if ($greenhouseSettings->find(true)) {
	$rtAuthToken = $greenhouseSettings->requestTrackerAuthToken;
	$baseRtUrl = $greenhouseSettings->requestTrackerBaseUrl;
}

$openTicketsFound = [];
$ticketStatusFeeds = new TicketStatusFeed();
$ticketStatusFeeds->find();
while ($ticketStatusFeeds->fetch()) {
	$ticketsInFeed = getTicketInfoFromFeed('Status ' . $ticketStatusFeeds->name, $ticketStatusFeeds->rssFeed);
	foreach ($ticketsInFeed as $ticketInfo) {
		$ticket = getTicket($ticketInfo);
		$ticket->status = $ticketStatusFeeds->name;
		try {
			$ticket->update();
			$openTicketsFound[$ticket->ticketId] = $ticket->ticketId;
		} catch (PDOException $e) {
			echo("Could not update ticket $ticket->ticketId " . $ticket->getLastError());
		}
	}
}
//There are too many closed tickets to get an RSS feed, we need to just mark anything closed we don't see.
$ticket = new Ticket();
$ticket->whereAdd("status <> 'Closed'");
$ticket->find();
while ($ticket->fetch()) {
	if (!in_array($ticket->ticketId, $openTicketsFound)) {
		$ticket->status = 'Closed';
		$ticket->dateClosed = time();
		try {
			$ticket->update();
		} catch (PDOException $e) {
			echo("Could not update ticket $ticket->ticketId " . $ticket->getLastError());
		}
	}
}

//Update all tickets based on their queues
require_once ROOT_DIR . '/sys/Support/TicketQueueFeed.php';
$ticketQueueFeeds = new TicketQueueFeed();
$ticketQueueFeeds->find();
while ($ticketQueueFeeds->fetch()) {
	$ticketsInFeed = getTicketInfoFromFeed('Queue ' . $ticketQueueFeeds->name, $ticketQueueFeeds->rssFeed);
	foreach ($ticketsInFeed as $ticketInfo) {
		$ticket = getTicket($ticketInfo);
		$ticket->queue = $ticketQueueFeeds->name;
		try {
			$ticket->update();
		} catch (PDOException $e) {
			echo("Could not update ticket $ticket->ticketId " . $ticket->getLastError());
		}
	}
}

//Update all tickets based on their severity
require_once ROOT_DIR . '/sys/Support/TicketSeverityFeed.php';
$ticketSeverityFeeds = new TicketSeverityFeed();
$ticketSeverityFeeds->find();
while ($ticketSeverityFeeds->fetch()) {
	$ticketsInFeed = getTicketInfoFromFeed('Severity ' . $ticketSeverityFeeds->name, $ticketSeverityFeeds->rssFeed);
	foreach ($ticketsInFeed as $ticketInfo) {
		$ticket = getTicket($ticketInfo);
		$ticket->severity = $ticketSeverityFeeds->name;
		try {
			$ticket->update();
		} catch (PDOException $e) {
			echo("Could not update ticket $ticket->ticketId " . $ticket->getLastError());
		}
	}
}

//Update all tickets based on assigned component
$tmpTicket = new Ticket();
$tmpTicket->whereAdd("status <> 'Closed'");
/** @var Ticket[] $allOpenTickets */
$allOpenTickets = $tmpTicket->fetchAll();

//Get a list of all components
$allComponents = new TicketComponentFeed();
$allComponents->find();
$allComponentsByName = [];
while ($allComponents->fetch()) {
	$allComponentsByName[$allComponents->name] = clone $allComponents;
}

$curlConnection = new CurlWrapper();
foreach ($allOpenTickets as $openTicket) {
	$ticketInfoUrl = $baseRtUrl . '/REST/2.0/ticket/' . $openTicket->ticketId . "?token=$rtAuthToken";
	$response = $curlConnection->curlGetPage($ticketInfoUrl);
	$json = json_decode($response);
	$customFields = $json->CustomFields;
	/** @var ComponentTicketLink $relatedComponents */
	$relatedComponents = [];
	$existingComponents = $openTicket->getRelatedComponents();
	foreach ($customFields as $customField) {
		if ($customField->name == 'Aspen Discovery Components') {
			foreach ($customField->values as $value) {
				//Get the right component object
				if (array_key_exists($value, $allComponentsByName)) {
					$relatedComponent = $allComponentsByName[$value];
				} else {
					//This is a new component we haven't seen, add it
					$ticketComponent = new TicketComponentFeed();
					$ticketComponent->name = $value;
					$ticketComponent->insert();
					$allComponentsByName[$ticketComponent->name] = clone $ticketComponent;
					$relatedComponent = $ticketComponent;
				}
				//Check to see if we are already linked to that component
				$foundExistingLink = false;
				foreach ($existingComponents as $existingComponent) {
					if ($existingComponent->componentId == $relatedComponent->id) {
						$relatedComponents[] = $existingComponent;
						$foundExistingLink = true;
					}
				}
				if (!$foundExistingLink) {
					$componentLink = new ComponentTicketLink();
					$componentLink->ticketId = $openTicket->id;
					$componentLink->componentId = $relatedComponent->id;
					$relatedComponents[] = $componentLink;
				}
			}
			break;
		}
	}
	$openTicket->setRelatedComponents($relatedComponents);
	$openTicket->update();
}

//Update all tickets from partner feeds

//Update partner priorities
require_once ROOT_DIR . '/sys/Greenhouse/AspenSite.php';
$aspenSite = new AspenSite();
$aspenSite->siteType = "0";
$aspenSite->find();
while ($aspenSite->fetch()) {
	if (!empty($aspenSite->baseUrl)) {
		$priority1Ticket = -1;
		$priority2Ticket = -1;
		$priority3Ticket = -1;
		$prioritiesUrl = $aspenSite->baseUrl . '/API/SystemAPI?method=getDevelopmentPriorities';
		$prioritiesData = file_get_contents($prioritiesUrl);
		if ($prioritiesData) {
			$prioritiesData = json_decode($prioritiesData);
			//Get existing priorities for the partner
			if ($prioritiesData->result->success) {
				$priority1Ticket = $prioritiesData->result->priorities->priority1->id;
				$priority2Ticket = $prioritiesData->result->priorities->priority2->id;
				$priority3Ticket = $prioritiesData->result->priorities->priority3->id;
			}
		}
		//Get a list of all tickets for the partner
		if (!empty($aspenSite->activeTicketFeed)) {
			$ticketsInFeed = getTicketInfoFromFeed($aspenSite->name, $aspenSite->activeTicketFeed);
			foreach ($ticketsInFeed as $ticketInfo) {
				$ticket = getTicket($ticketInfo);
				$ticket->requestingPartner = $aspenSite->id;
				$newPriority = -1;
				if ($ticket->ticketId == $priority1Ticket) {
					$newPriority = 1;
				} elseif ($ticket->ticketId == $priority2Ticket) {
					$newPriority = 2;
				} elseif ($ticket->ticketId == $priority3Ticket) {
					$newPriority = 3;
				}
				if ($newPriority != $ticket->partnerPriority) {
					$ticket->partnerPriority = $newPriority;
					$ticket->partnerPriorityChangeDate = time();
				}
				try {
					$ticket->update();
				} catch (PDOException $e) {
					echo("Could not update ticket $ticket->ticketId " . $ticket->getLastError());
				}
			}
		} else {
			if ($priority1Ticket != -1) {
				$ticket = new Ticket();
				$ticket->ticketId = $priority1Ticket;
				if ($ticket->find(true)) {
					$ticket->requestingPartner = $aspenSite->id;
					if ($ticket->partnerPriority != 1) {
						$ticket->partnerPriority = 1;
						$ticket->partnerPriorityChangeDate = time();
					}
					try {
						$ticket->update();
					} catch (PDOException $e) {
						echo("Could not update ticket $ticket->ticketId " . $ticket->getLastError());
					}
				}
			}
			if ($priority2Ticket != -1) {
				$ticket = new Ticket();
				$ticket->ticketId = $priority2Ticket;
				if ($ticket->find(true)) {
					$ticket->requestingPartner = $aspenSite->id;
					if ($ticket->partnerPriority != 2) {
						$ticket->partnerPriority = 2;
						$ticket->partnerPriorityChangeDate = time();
					}
					try {
						$ticket->update();
					} catch (PDOException $e) {
						echo("Could not update ticket $ticket->ticketId " . $ticket->getLastError());
					}
				}
			}
			if ($priority3Ticket != -1) {
				$ticket = new Ticket();
				$ticket->ticketId = $priority3Ticket;
				if ($ticket->find(true)) {
					$ticket->requestingPartner = $aspenSite->id;
					if ($ticket->partnerPriority != 3) {
						$ticket->partnerPriority = 3;
						$ticket->partnerPriorityChangeDate = time();
					}
					try {
						$ticket->update();
					} catch (PDOException $e) {
						echo("Could not update ticket $ticket->ticketId " . $ticket->getLastError());
					}
				}
			}
		}
	}
}

//Update Ticket Components, we will loop through all open tickets


//Update stats for today
//require_once ROOT_DIR . '/sys/Support/TicketStats.php';
//$ticketStats = new TicketStats();
//$ticketStats->year = date('Y');
//$ticketStats->month = date('n');
//$ticketStats->day = date('d');


die;

function getTicketInfoFromFeed($name, $feedUrl): array {
	$rssDataRaw = @file_get_contents($feedUrl);
	fwrite(STDOUT, "Loading $name - $feedUrl \n");
	if ($rssDataRaw == false) {
		echo("Could not load data from $feedUrl \r\n");
		fwrite(STDOUT, " No data found \n");
		return [];
	} else {
		$activeTickets = [];
		try {
			$rssData = new SimpleXMLElement($rssDataRaw);
			$ns = $rssData->getNamespaces(true);
			if (!empty($rssData->item)) {
				foreach ($rssData->item as $item) {
					$matches = [];
					preg_match('/.*id=(\d+)/', $item->link, $matches);
					$dcData = $item->children($ns['dc']);
					$activeTickets[$matches[1]] = [
						'id' => $matches[1],
						'title' => (string)$item->title,
						'description' => (string)$item->description,
						'link' => (string)$item->link,
						'dateCreated' => (string)$dcData->date,
					];
				}
				fwrite(STDOUT, "  Found " . count($rssData->item) . " \n");
			} else {
				fwrite(STDOUT, "  Found 0 \n");
			}
		} catch (Exception $e) {
			fwrite(STDOUT, " Could not parse data \n");
		}
		return $activeTickets;
	}
}

function getTicket($ticketInfo): Ticket {
	$ticket = new Ticket();
	$ticket->ticketId = $ticketInfo['id'];
	if ($ticket->find(true)) {
		return $ticket;
	} else {
		$ticket = new Ticket();
		$ticket->ticketId = $ticketInfo['id'];
		$ticket->title = $ticketInfo['title'];
		$ticket->description = $ticketInfo['description'];
		$ticket->displayUrl = $ticketInfo['link'];
		$ticket->dateCreated = strtotime($ticketInfo['dateCreated']);
		try {
			if (!$ticket->insert()) {
				echo("Could not create ticket $ticket->ticketId " . $ticket->getLastError());
				fwrite(STDOUT, "Could not create ticket $ticket->ticketId " . $ticket->getLastError() . "\n");
			}
		} catch (PDOException $e) {
			echo("Could not create ticket $ticket->ticketId " . $e);
			fwrite(STDOUT, "Could not create ticket $ticket->ticketId " . $e . "\n");
		}
		return $ticket;
	}
}