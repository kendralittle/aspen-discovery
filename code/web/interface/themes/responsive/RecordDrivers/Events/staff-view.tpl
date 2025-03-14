{strip}
	{if $recordDriver}
		<div class="row">
			<div class="col-xs-12">
				record driver present
{*				<a href="/Event/{$recordDriver->getPermanentId()}" class="btn btn-sm btn-default">Go To Event</a>
				<button onclick="return AspenDiscovery.GroupedWork.reloadCover('{$recordDriver->getPermanentId()}')" class="btn btn-sm btn-default">{translate text="Reload Cover" isAdminFacing=true}</button>
				{if $loggedIn && in_array('Upload Covers', $userPermissions)}
					<button onclick="return AspenDiscovery.GroupedWork.getUploadCoverForm('{$recordDriver->getPermanentId()}')" class="btn btn-sm btn-default">{translate text="Upload Cover by from Computer" isAdminFacing=true}</button>
					<button onclick="return AspenDiscovery.GroupedWork.getUploadCoverFormByURL('{$recordDriver->getPermanentId()}')" class="btn btn-sm btn-default">{translate text="Upload Cover by URL" isAdminFacing=true}</button>
					<button onclick="return AspenDiscovery.GroupedWork.clearUploadedCover('{$recordDriver->getPermanentId()}')" class="btn btn-sm btn-default">{translate text="Clear Uploaded Cover" isAdminFacing=true}</button>
				{/if}
				<button onclick="return AspenDiscovery.GroupedWork.reloadEnrichment('{$recordDriver->getPermanentId()}')" class="btn btn-sm btn-default" >{translate text="Reload Enrichment" isAdminFacing=true}</button>
				{if $loggedIn && in_array('Force Reindexing of Records', $userPermissions)}
					<button onclick="return AspenDiscovery.GroupedWork.forceReindex('{$recordDriver->getPermanentId()}')" class="btn btn-sm btn-default">{translate text="Force Reindex" isAdminFacing=true}</button>
				{/if}
				{if $loggedIn && in_array('Set Grouped Work Display Information', $userPermissions)}
					<button onclick="return AspenDiscovery.GroupedWork.getDisplayInfoForm('{$recordDriver->getPermanentId()}')" class="btn btn-sm btn-default">{translate text="Set Display Info" isAdminFacing=true}</button>
				{/if}
				{if $loggedIn && in_array('Manually Group and Ungroup Works', $userPermissions)}
					<button onclick="return AspenDiscovery.GroupedWork.getGroupWithForm(this, '{$recordDriver->getPermanentId()}')" class="btn btn-sm btn-default">{translate text="Group With Work" isAdminFacing=true}</button>
					<button onclick="return AspenDiscovery.GroupedWork.ungroupRecord(this, '{$recordDriver->getIdWithSource()}')" class="btn btn-sm btn-default">{translate text="Ungroup" isAdminFacing=true}</button>
				{/if}
*}
			</div>
		</div>
	{/if}

{*
	{include file="RecordDrivers/GroupedWork/grouping-information.tpl"}
*}

	{if $hooplaExtract}
		<h3>{translate text="Hoopla Extract Information" isPublicFacing=true}</h3>
		<pre>
		{$hooplaExtract|print_r}
	</pre>
	{/if}

{/strip}