<?php
/**
 * Plugin Name: HTTPS domain alias
 * Plugin URI: https://github.com/Seravo/wp-https-domain-alias
 * Description: Enable your site to have a different domains for HTTP and HTTPS. Useful e.g. if you have a wildcard SSL/TLS certificate for server but not for each site.
 * Version: 1.0
 * Author: Otto Kekäläinen (Seravo Oy) and Antti Kuosmanen (Seravo Oy)
 * Author URI: http://seravo.fi
 * License: GPLv3
 */


/** Copyright 2014  Otto Kekäläinen / Seravo Oy

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 3, as
  published by the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


/**
 * @package HTTPS_Domain_Alias
 *
 * Make sure a https capable domain is used {@see HTTPS_DOMAIN_ALIAS} if the
 * protocol is HTTPS.
 *
 * Make sure the wp-config.php defines the needed constants, e.g.
 *   define('FORCE_SSL_LOGIN', true);
 *   define('FORCE_SSL_ADMIN', true);
 *   define('HTTPS_DOMAIN_ALIAS', 'coss.seravo.fi');
 *
 * On a WordPress Network install HTTPS_DOMAIN_ALIAS can also be
 * defined with a wildcard, e.g. '*.seravo.fi'.
 * Compatible with WordPress MU Domain Mapping.
 *
 * Example:
 *  Redirect never points to https://coss.fi/..
 *  but instead always to https://coss.seravo.fi/...
 *
 * For more information see readme.txt
 *
 * @param string $url
 * @param string $status (optional, not used in this function)
 * @return string
 */
function _https_domain_rewrite($url, $status = 0) {

  //debug: error_log("status=$status");
  //debug: error_log("url-i=$url");

  // TODO: second parameter if ofen scheme,
  //       see http://codex.wordpress.org/Function_Reference/site_url#Parameters


  // Rewrite only if the request is https, or the user is logged in
  // to preserve cookie integrity
  if (substr($url, 0, 5) == 'https' || ( function_exists('is_user_logged_in') && is_user_logged_in() && !(defined('DISABLE_FRONTEND_SSL') && DISABLE_FRONTEND_SSL) ) ) {

      // Assume domain is always same for all calls to this function
      // during same request and thus define some variables as static.
      static $domain;
      if (!isset($domain)) {
        $domain = parse_url(get_option('home'), PHP_URL_HOST);
        //debug: error_log("domain=$domain");
      }

      static $domainAlias;
      if (!isset($domainAlias)) {
         if (substr(HTTPS_DOMAIN_ALIAS, -strlen($domain)) == $domain) {
          // Special case: $domainAlias ends with $domain,
          // which is possible in WP Network when requesting
          // the main site, don't rewrite urls as a https
          // certificate for sure exists for direct domain.
          // e.g. domain seravo.fi, domain alias *.seravo.fi
          $domainAlias = $domain;
        } else if (substr(HTTPS_DOMAIN_ALIAS, 0, 1) == '*') {
          $domainBase = substr($domain, 0, strpos($domain, '.'));
          $domainAliasBase = substr(HTTPS_DOMAIN_ALIAS, 1);
          $domainAlias = $domainBase . $domainAliasBase;
        } else {
          $domainAlias = HTTPS_DOMAIN_ALIAS;
        }
        //debug: error_log("domainAlias=$domainAlias");
      }

      // If $location does not include simple https domain alias, rewrite it.
      if ($domain != $domainAlias) {
        $url = str_ireplace($domain, $domainAlias, $url);
        $url = str_replace('http://', 'https://', $url);
        //debug: error_log("url-o=$url");
      }

  }

  return $url;
}


/**
 * Debug wrapper
 *
 *
 * @param string $url
 * @param string $path
 * @param string $plugins
 * @return string $url
 */
function _debug_rewrite($url, $path=false, $plugin=false, $extra=false) {
  error_log("url=$url");
  $url = _https_domain_rewrite($url);
  error_log("return=$url");
  return $url;
}


/*
 * Includes a patch for Polylang Language plugin, which redefines home_url in the back-end
 */
function _home_url_rewrite($url) {

  //don't rewrite urls for polylang settings
  if($_GET['page'] == 'mlang') {
    return $url;
  }

  $url = _https_domain_rewrite($url);
  return $url;
}


/*
 * Register filters only if HTTPS_DOMAIN_ALIAS defined
 */
if (defined('HTTPS_DOMAIN_ALIAS')) {

  // A redirect or link to https may happen from pages served via http
  add_filter('login_url', '_https_domain_rewrite');
  add_filter('logout_url', '_https_domain_rewrite');
  add_filter('admin_url', '_https_domain_rewrite');
  add_filter('wp_redirect', '_https_domain_rewrite');
  add_filter('plugins_url', '_https_domain_rewrite');
  add_filter('content_url', '_https_domain_rewrite');
  add_filter('site_url', '_https_domain_rewrite');
  add_filter('home_url', '_home_url_rewrite');

} else {

  error_log('Constant HTTPS_DOMAIN_ALIAS is not defined');

}


/*
 * Make sure this plugin loads as first so that the filters will be applied
 * to all plugins before they fetch or define any URLs.
 *
 * This function will only take effect at the time when some plugin is activated,
 * does not apply directly for old installs and in general is brittle to brake,
 * as something else might edit the plugin list in the database options table
 * and thus lower the priorty for this plugin.
 */
add_action('activated_plugin', 'https_domain_alias_must_be_first_plugin');
function https_domain_alias_must_be_first_plugin() {
  // ensure path to this file is via main wp plugin path
  $wp_path_to_this_file = preg_replace('/(.*)plugins\/(.*)$/', WP_PLUGIN_DIR."/$2", __FILE__);
  $this_plugin = plugin_basename(trim($wp_path_to_this_file));
  $active_plugins = get_option('active_plugins');
  $this_plugin_key = array_search($this_plugin, $active_plugins);

  if ($this_plugin_key) { // if it's 0 it's the first plugin already, no need to continue
    array_splice($active_plugins, $this_plugin_key, 1);
    array_unshift($active_plugins, $this_plugin);
    update_option('active_plugins', $active_plugins);
  }
}


/**
 * Create a readme page in the settings menu
 */
add_action('admin_menu', '_https_domain_alias_readme');
function _https_domain_alias_readme() {
  //Readme is only visible, when HTTPS_DOMAIN_ALIAS is not defined
  if(!defined('HTTPS_DOMAIN_ALIAS')) {
    add_options_page('HTTPS Domain Alias', 'HTTPS Domain Alias', 'administrator', __FILE__, '_build_readme_page', plugins_url('/images/icon.png', __FILE__));
  }
}

function _build_readme_page() {
?>

<div class="wrap">

  <h2>HTTPS Domain Alias</h2>

  <div id="message" class="error"><p><?php _e('This readme page is only visible when HTTPS_DOMAIN_ALIAS is not defined in wp-config.php. You will not see this once the constant is defined.');?> </p></div>

  <?php include('readme.html'); ?>

  <p>&nbsp;</p>

  <p><small>HTTPS Domain Alias is made by <a href="http://seravo.fi/">Seravo Oy</a>, which specializes in open source support services and among others is the only company in Finland to provide [WordPress Premium Hosting](http://seravo.fi/wordpress-palvelu).</small></p>

</div>
<?php
}
