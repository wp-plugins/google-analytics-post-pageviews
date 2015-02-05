<?php
/*
Plugin Name: Google Analytics Post Pageviews
Plugin URI: http://maxime.sh/google-analytics-post-pageviews
Description: Retrieves and displays the pageviews for each post by linking to your Google Analytics account.
Author: Maxime VALETTE
Author URI: http://maxime.sh
Version: 1.3.5
*/

define('GAPP_SLUG', 'google-analytics-post-pageviews');
define('GAPP_TEXTDOMAIN', 'google-analytics-post-pageviews');

if (function_exists('load_plugin_textdomain')) {
	load_plugin_textdomain(GAPP_TEXTDOMAIN, false, dirname(plugin_basename(__FILE__)).'/languages' );
}

add_action('admin_menu', 'gapp_config_page');

function gapp_config_page() {

	if (function_exists('add_submenu_page')) {

        add_submenu_page('options-general.php',
            __('Post Pageviews', GAPP_TEXTDOMAIN),
            __('Post Pageviews', GAPP_TEXTDOMAIN),
            'manage_options', GAPP_SLUG, 'gapp_conf');

    }

}

function gapp_api_call($url, $params = array()) {

    $options = get_option('gapp');

	if (time() >= $options['gapp_expires']) {

		$options = gapp_refresh_token();

	}

    $qs = '?access_token='.urlencode($options['gapp_token']);

    foreach ($params as $k => $v) {

        $qs .= '&'.$k.'='.urlencode($v);

    }

	$request = new WP_Http;
	$result = $request->request($url.$qs);
	$json = new stdClass();

    $options['gapp_error'] = null;

	if ( is_array( $result ) && isset( $result['response']['code'] ) && 200 === $result['response']['code'] ) {

        $json = json_decode($result['body']);

        update_option('gapp', $options);

		return $json;

	} else {

        if ( is_array( $result ) && isset( $result['response']['code'] ) && 403 === $result['response']['code'] ) {

            $json = json_decode($result['body'], true);

            $options['gapp_error'] = $json['error']['errors'][0]['message'];

            $options['gapp_token'] = null;
            $options['gapp_token_refresh'] = null;
            $options['gapp_expires'] = null;
            $options['gapp_gid'] = null;

            update_option('gapp', $options);

        }

		return new stdClass();

	}

}

function gapp_refresh_token() {

	$options = get_option('gapp');

	/* If the token has expired, we create it again */

	if (!empty($options['gapp_token_refresh'])) {

		$request = new WP_Http;

		$result = $request->request('https://accounts.google.com/o/oauth2/token', array(
			'method' => 'POST',
			'body' => array(
				'client_id' => $options['gapp_clientid'],
				'client_secret' => $options['gapp_psecret'],
				'refresh_token' => $options['gapp_token_refresh'],
				'grant_type' => 'refresh_token',
			),
		));

        $options['gapp_error'] = null;

		if ( is_array( $result ) && isset( $result['response']['code'] ) && 200 === $result['response']['code'] ) {

			$tjson = json_decode($result['body']);

			$request = new WP_Http;
			$result = $request->request('https://www.googleapis.com/oauth2/v1/userinfo?access_token='.urlencode($tjson->access_token));

			if ( is_array( $result ) && isset( $result['response']['code'] ) && 200 === $result['response']['code'] ) {

				$ijson = json_decode($result['body']);

				$options['gapp_token'] = $tjson->access_token;

				if (isset($tjson->refresh_token) && !empty($tjson->refresh_token)) {
					$options['gapp_token_refresh'] = $tjson->refresh_token;
				}

				$options['gapp_expires'] = time() + $tjson->expires_in;
				$options['gapp_gid'] = $ijson->id;

				update_option('gapp', $options);

			} elseif ( is_array( $result ) && isset( $result['response']['code'] ) && 403 === $result['response']['code'] ) {

                $json = json_decode($result['body'], true);

                $options['gapp_error'] = $json['error']['errors'][0]['message'];

                $options['gapp_token'] = null;
                $options['gapp_token_refresh'] = null;
                $options['gapp_expires'] = null;
                $options['gapp_gid'] = null;

                update_option('gapp', $options);

            }

		} /* else {

			$options['gapp_token'] = null;
			$options['gapp_token_refresh'] = null;
			$options['gapp_expires'] = null;
			$options['gapp_gid'] = null;

			update_option('gapp', $options);

		} */

	}

	return $options;

}

function gapp_conf() {

	/** @var $wpdb WPDB */
	global $wpdb;

	$options = get_option('gapp');

	if (!isset($options['gapp_clientid'])) {
		if (isset($options['gapp_pnumber'])) {
			$options['gapp_clientid'] = $options['gapp_pnumber'] . '.apps.googleusercontent.com';
		} else {
			$options['gapp_clientid'] = null;
		}
	}

	if (isset($options['gapp_pnumber'])) unset($options['gapp_pnumber']);
    if (!isset($options['gapp_psecret'])) $options['gapp_psecret'] = null;
    if (!isset($options['gapp_gid'])) $options['gapp_gid'] = null;
    if (!isset($options['gapp_gmail'])) $options['gapp_gmail'] = null;
    if (!isset($options['gapp_token'])) $options['gapp_token'] = null;
    if (!isset($options['gapp_token_refresh'])) $options['gapp_token_refresh'] = null;
    if (!isset($options['gapp_expires'])) $options['gapp_expires'] = null;
    if (!isset($options['gapp_wid'])) $options['gapp_wid'] = null;
    if (!isset($options['gapp_cache'])) $options['gapp_cache'] = 60;
    if (!preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/', $options['gapp_startdate'])) $options['gapp_startdate'] = '2007-09-29';

	$updated = false;

    if (isset($_GET['state']) && $_GET['state'] == 'init' && $_GET['code']) {

	    $request = new WP_Http;

	    $result = $request->request('https://accounts.google.com/o/oauth2/token', array(
		    'method' => 'POST',
		    'body' => array(
			    'code' => $_GET['code'],
			    'client_id' => $options['gapp_clientid'],
			    'client_secret' => $options['gapp_psecret'],
			    'redirect_uri' => admin_url('options-general.php?page=' . GAPP_SLUG),
			    'grant_type' => 'authorization_code',
		    )
	    ));

	    if ( !is_array( $result ) || !isset( $result['response']['code'] ) && 200 !== $result['response']['code'] ) {

            echo '<div id="message" class="error"><p>';
            _e('There was something wrong with Google.', GAPP_TEXTDOMAIN);
            echo "</p></div>";

		    var_dump($result);

        }

        $tjson = json_decode($result['body']);

        $options['gapp_token'] = $tjson->access_token;
        $options['gapp_token_refresh'] = $tjson->refresh_token;
        $options['gapp_expires'] = time() + $tjson->expires_in;

        update_option('gapp', $options);

        $ijson = gapp_api_call('https://www.googleapis.com/oauth2/v1/userinfo', array());

        $options['gapp_gid'] = $ijson->id;
        $options['gapp_gmail'] = $ijson->email;

        update_option('gapp', $options);

        if (!empty($options['gapp_token']) && !empty($options['gapp_gmail'])) {

            echo '<script>window.location = \''.admin_url('options-general.php?page=' . GAPP_SLUG).'\';</script>';
			exit;

        }

    } elseif (isset($_GET['state']) && $_GET['state'] == 'reset') {

        $options['gapp_gid'] = null;
        $options['gapp_gmail'] = null;
        $options['gapp_token'] = null;
        $options['gapp_token_refresh'] = null;
        $options['gapp_expires'] = null;

        update_option('gapp', $options);

        $updated = true;

    } elseif (isset($_GET['state']) && $_GET['state'] == 'clear') {

	    $options['gapp_clientid'] = null;
        $options['gapp_psecret'] = null;

        update_option('gapp', $options);

        $updated = true;

    } elseif (isset($_GET['refresh'])) {

	    gapp_refresh_token();

	    $options = get_option('gapp');

	    $updated = true;

    } elseif (isset($_GET['reset'])) {

	    $wpdb->query("DELETE FROM `wp_options` WHERE `option_name` LIKE '_transient_gapp-transient-%'");

	    $updated = true;

    }

	if (isset($_POST['submit'])) {

		check_admin_referer('gapp', 'gapp-admin');

		if (isset($_POST['gapp_clientid'])) {
            $options['gapp_clientid'] = $_POST['gapp_clientid'];
		}

        if (isset($_POST['gapp_psecret'])) {
            $options['gapp_psecret'] = $_POST['gapp_psecret'];
        }

        if (isset($_POST['gapp_wid'])) {
            $options['gapp_wid'] = $_POST['gapp_wid'];
        }

		if (isset($_POST['gapp_cache'])) {
			$options['gapp_cache'] = $_POST['gapp_cache'];
		}

		if (isset($_POST['gapp_startdate'])) {
			$options['gapp_startdate'] = $_POST['gapp_startdate'];
		}

		update_option('gapp', $options);

		$updated = true;

	}

    echo '<div class="wrap">';

    if ($updated) {

	    echo '<div id="message" class="updated fade"><p>';
	    _e('Configuration updated.', GAPP_TEXTDOMAIN);
	    echo '</p></div>';

    }

    if (!empty($options['gapp_token'])) {

        echo '<h2>'.__('Post Pageviews Usage', GAPP_TEXTDOMAIN).'</h2>';

        echo '<p>'.__('To display the pageviews number of a particular post, insert this PHP code in your template:', GAPP_TEXTDOMAIN).'</p>';

        echo '<input type="text" class="regular-text code" value="&lt;?php echo gapp_get_post_pageviews(); ?&gt;"/>';

        echo '<p>'.__('This code must be placed in The Loop. If not, you can specify the post ID.', GAPP_TEXTDOMAIN).'</p>';

    }

    echo '<h2>'.__('Post Pageviews Settings', GAPP_TEXTDOMAIN).'</h2>';

    if (empty($options['gapp_token'])) {

        if (empty($options['gapp_clientid']) || empty($options['gapp_psecret'])) {

            echo '<p>'.__('In order to connect to your Google Analytics Account, you need to create a new project in the <a href="https://console.developers.google.com/project" target="_blank">Google API Console</a> and activate the Analytics API in "APIs &amp; auth &gt; APIs".', GAPP_TEXTDOMAIN).'</p>';

            echo '<form action="'.admin_url('options-general.php?page=' . GAPP_SLUG).'" method="post" id="gapp-conf">';

            echo '<p>'.__('Then, create an OAuth Client ID in "APIs &amp; auth &gt; Credentials". Enter this URL for the Redirect URI field:', GAPP_TEXTDOMAIN).'<br/>';
            echo admin_url('options-general.php?page=' . GAPP_SLUG);
            echo '</p>';

	        echo '<p>'.__('You also have to fill the Product Name field in "APIs & auth" -> "Consent screen" — you need to select e-mail address as well.').'</p>';

            echo '<h3><label for="gapp_clientid">'.__('Client ID:', GAPP_TEXTDOMAIN).'</label></h3>';
            echo '<p><input type="text" id="gapp_clientid" name="gapp_clientid" value="'.$options['gapp_clientid'].'" style="width: 400px;" /></p>';

            echo '<h3><label for="gapp_psecret">'.__('Client secret:', GAPP_TEXTDOMAIN).'</label></h3>';
            echo '<p><input type="text" id="gapp_psecret" name="gapp_psecret" value="'.$options['gapp_psecret'].'" style="width: 400px;" /></p>';

            echo '<p class="submit" style="text-align: left">';
            wp_nonce_field('gapp', 'gapp-admin');
            echo '<input type="submit" name="submit" value="'.__('Save', GAPP_TEXTDOMAIN).' &raquo;" /></p></form></div>';

        } else {

            $url_auth = 'https://accounts.google.com/o/oauth2/auth?client_id='.$options['gapp_clientid'].'&redirect_uri=';
            $url_auth .= admin_url('options-general.php?page=' . GAPP_SLUG);
            $url_auth .= '&scope=https://www.googleapis.com/auth/analytics.readonly+https://www.googleapis.com/auth/userinfo.email+https://www.googleapis.com/auth/userinfo.profile&response_type=code&access_type=offline&state=init&approval_prompt=force';

            echo '<p><a href="'.$url_auth.'">'.__('Connect to Google Analytics', GAPP_TEXTDOMAIN).'</a></p>';

            echo '<p><a href="'.admin_url('options-general.php?page=' . GAPP_SLUG).'&state=clear">'.__('Clear the API keys').' &raquo;</a></p>';

        }

    } else {

        echo '<p>'.__('You are connected to Google Analytics with the e-mail address:', GAPP_TEXTDOMAIN).' '.$options['gapp_gmail'].'.</p>';

        echo '<p>'.__('Your token expires on:', GAPP_TEXTDOMAIN).' '.date_i18n( 'Y/m/d \a\t g:ia', $options['gapp_expires'] + ( get_option( 'gmt_offset' ) * 3600 ) , 1 ).'.</p>';

	    echo '<p><a href="'.admin_url('options-general.php?page=' . GAPP_SLUG . '&state=reset').'">'.__('Disconnect from Google Analytics', GAPP_TEXTDOMAIN).' &raquo;</a></p>';

        echo '<p><a href="'.admin_url('options-general.php?page=' . GAPP_SLUG . '&refresh').'">'.__('Refresh Google API token', GAPP_TEXTDOMAIN).' &raquo;</a></p>';

	    echo '<p><a href="'.admin_url('options-general.php?page=' . GAPP_SLUG . '&reset').'">'.__('Empty pageviews cache', GAPP_TEXTDOMAIN).' &raquo;</a></p>';

        echo '<form action="'.admin_url('options-general.php?page=' . GAPP_SLUG).'" method="post" id="gapp-conf">';

        echo '<h3><label for="gapp_wid">'.__('Use this website to retrieve pageviews numbers:', GAPP_TEXTDOMAIN).'</label></h3>';
        echo '<p><select id="gapp_wid" name="gapp_wid" style="width: 400px;" />';

        echo '<option value=""';
        if (empty($options['gapp_wid'])) echo ' SELECTED';
        echo '>'.__('None', GAPP_TEXTDOMAIN).'</option>';

        $wjson = gapp_api_call('https://www.googleapis.com/analytics/v3/management/accounts/~all/webproperties/~all/profiles', array());
		
        if (is_array($wjson->items)) {

            foreach ($wjson->items as $item) {

                if ($item->type != 'WEB') {
                    continue;
                }

                echo '<option value="'.$item->id.'"';
                if ($options['gapp_wid'] == $item->id) echo ' SELECTED';
                echo '>'.$item->name.' ('.$item->websiteUrl.')</option>';

            }

        }

        echo '</select></p>';

        echo '<h3><label for="gapp_cache">'.__('Cache time:', GAPP_TEXTDOMAIN).'</label></h3>';
        echo '<p><select id="gapp_cache" name="gapp_cache">';

        echo '<option value="60"';
        if ($options['gapp_cache'] == 60) echo ' SELECTED';
        echo '>'.__('One hour', GAPP_TEXTDOMAIN).'</option>';

        echo '<option value="360"';
        if ($options['gapp_cache'] == 360) echo ' SELECTED';
        echo '>'.__('Six hours', GAPP_TEXTDOMAIN).'</option>';

        echo '<option value="720"';
        if ($options['gapp_cache'] == 720) echo ' SELECTED';
        echo '>'.__('12 hours', GAPP_TEXTDOMAIN).'</option>';

        echo '<option value="1440"';
        if ($options['gapp_cache'] == 1440) echo ' SELECTED';
        echo '>'.__('One day', GAPP_TEXTDOMAIN).'</option>';

        echo '<option value="10080"';
        if ($options['gapp_cache'] == 10080) echo ' SELECTED';
        echo '>'.__('One week', GAPP_TEXTDOMAIN).'</option>';

        echo '<option value="20160"';
        if ($options['gapp_cache'] == 20160) echo ' SELECTED';
        echo '>'.__('Two weeks', GAPP_TEXTDOMAIN).'</option>';

        echo '</select></p>';

        echo '<h3><label for="gapp_startdate">'.__('Start date for the analytics:', GAPP_TEXTDOMAIN).'</label></h3>';
        echo '<p><input type="text" id="gapp_startdate" name="gapp_startdate" value="'.$options['gapp_startdate'].'" /></p>';

        echo '<p class="submit" style="text-align: left">';
        wp_nonce_field('gapp', 'gapp-admin');
        echo '<input type="submit" name="submit" value="'.__('Save', GAPP_TEXTDOMAIN).' &raquo;" /></p></form></div>';

    }

}

function gapp_get_post_pageviews($ID = null, $format = true) {

    $options = get_option('gapp');

	if ($ID) {

		$gaTransName = 'gapp-transient-'.$ID;
		$permalink = '/' . basename(get_permalink($ID));

	} else {

		$gaTransName = 'gapp-transient-'.get_the_ID();
		$permalink = '/' . basename(get_permalink());

	}

    $totalResult = get_transient($gaTransName);

    if ($totalResult !== false) {

	    return ($format) ? number_format_i18n($totalResult) : $totalResult;

    } else {

        if (empty($options['gapp_token'])) {

            return 0;

        }

	    if ($ID) {

		    $status = get_post_status($ID);

	    } else {

		    $status = get_post_status(get_the_ID());

	    }

	    if ($status !== 'publish') {

		    set_transient($gaTransName, '0', 60 * $options['gapp_cache']);

		    return 0;

	    }

        $json = gapp_api_call('https://www.googleapis.com/analytics/v3/data/ga',
            array('ids' => 'ga:'.$options['gapp_wid'],
                'start-date' => $options['gapp_startdate'],
                'end-date' => date('Y-m-d'),
                'metrics' => 'ga:pageviews',
                'filters' => 'ga:pagePath=@' . $permalink,
                'max-results' => 1000)
        );

	    if ( isset( $json->totalsForAllResults->{'ga:pageviews'} ) ) {

		    $totalResult = $json->totalsForAllResults->{'ga:pageviews'};

	    } else {

		    $totalResult = 0;

	    }

        if (is_numeric($totalResult) && $totalResult > 0) {

            set_transient($gaTransName, $totalResult, 60 * $options['gapp_cache']);

            return ($format) ? number_format_i18n($totalResult) : $totalResult;

        } else {

            set_transient($gaTransName, '0', 60 * $options['gapp_cache']);

            return 0;

        }

    }

}

// Add a column in Posts list (Optional)

add_filter('manage_posts_columns', 'gapp_column_views');
add_action('manage_posts_custom_column', 'gapp_custom_column_views', 6, 2);

function gapp_column_views($defaults) {

	$options = get_option('gapp');

	if (!empty($options['gapp_token'])) {

		$defaults['post_views'] = __('Views');

	}

	return $defaults;

}

function gapp_custom_column_views($column_name, $id) {

	if ($column_name === 'post_views') {

		echo gapp_get_post_pageviews(get_the_ID());

	}

}

function gapp_admin_notice() {

	$options = get_option('gapp');

	if (current_user_can('manage_options')) {

		if (isset($options['gapp_token']) && empty($options['gapp_token'])) {

			echo '<div class="error"><p>'.__('Google Post Pageviews Warning: You have to (re)connect the plugin to your Google account.') . '<br><a href="'.admin_url('options-general.php?page=' . GAPP_SLUG).'">'.__('Update settings', GAPP_TEXTDOMAIN).' &rarr;</a></p></div>';

		} elseif (isset($options['gapp_error']) && !empty($options['gapp_error'])) {

            echo '<div class="error"><p>'.__('Google Post Pageviews Error: ') . $options['gapp_error'] . '<br><a href="'.admin_url('options-general.php?page=' . GAPP_SLUG).'">'.__('Update settings', GAPP_TEXTDOMAIN).' &rarr;</a></p></div>';

        }

	}

}

// Admin notice
add_action('admin_notices', 'gapp_admin_notice');