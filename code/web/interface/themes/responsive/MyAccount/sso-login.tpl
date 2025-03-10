<div id="ssoLoginRow" class="form-group">
    {if !(empty($ssoLoginHelpText))}
        <div class="col-xs-12">
            <p class="alert alert-info" id="loginHelpText">
                {$ssoLoginHelpText}
            </p>
        </div>
    {/if}
    <div class="col-xs-12" style="text-align: center">
    {if $oAuthGateway == "google"}
        <a href="/init_oauth.php" class="btn sso_oauth_google">{translate text="Sign in with Google" isPublicFacing=true}</a>
        {elseif $oAuthGateway == "custom"}
	        {if !empty($oAuthCustomGatewayIcon)}
	            <a href="/init_oauth.php" class="btn btn-default btn-lg"
	            style="
	                background-image: url('{$oAuthCustomGatewayIcon}');
	                background-position: left center;
	                background-repeat: no-repeat;
	                background-size: 50px;
	                padding-left: 60px;
	                background-color: {$oAuthButtonBackgroundColor};
	                color: {$oAuthButtonTextColor};
	                border-color: {$oAuthButtonBackgroundColor}
	                ">
	                {translate text="Sign in With %1%" 1=$oAuthCustomGatewayLabel isPublicFacing=true}
	            </a>
	        {else}
	            <a href="/init_oauth.php" class="btn btn-default btn-lg" style="background-color: {$oAuthButtonBackgroundColor}; color: {$oAuthButtonTextColor}">
	                {translate text="Sign in with %1%" 1=$oAuthCustomGatewayLabel isPublicFacing=true}
	            </a>
	        {/if}
    {/if}
    </div>
</div>