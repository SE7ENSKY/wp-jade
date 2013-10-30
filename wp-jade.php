<?php
/*
  Plugin Name: WP Jade by Se7enSky
  Description: Use Jade for templating!
  Author: Se7enSky studio
  Author URI: http://www.se7ensky.com/
  Version: 1.2
 */

namespace nodizity;

require __DIR__ . '/template-helpers.php';

// admin side menu section

add_action('admin_menu', 'nodizity\\ndzt_add_menu');

/**
 * Create admin panel menu item Nodizity
 */
function ndzt_add_menu() {
	add_options_page('Template settings', 'Template settings', 'manage_options', 'template-settings', 'nodizity\\ndzt_toplevel_page' );
}

// post editor widget aka meta box
add_action('add_meta_boxes', 'nodizity\\ndzt_add_custom_box');

/**
 * Bind custom box generator to post types
 *
 */
function ndzt_add_custom_box() {
	$args=array(
		'public'   => true
	);
	$post_types=get_post_types($args, 'objects');
	foreach ($post_types as $post_type ) {
		add_meta_box(
			'ndzt_sectionid',
			__( 'Template settings', 'nodizity' ),
			'nodizity\\ndzt_inner_custom_box',
			$post_type->name
		);
	}
}

/**
 * Generate Nodizity template input for given post
 * @param $post
 */
function ndzt_inner_custom_box( $post ) {
	wp_nonce_field( plugin_basename( __FILE__ ), 'ndzt_noncename' );

	// value from DB
	//$value = get_post_meta( $post->ID, $key = '_ndzt_template_id', $single = true );
	// ToDo: echo select
	//echo '<input type="text" id="ndzt_template_id" name="ndzt_template_id" value="'.$value.'" size="25" />';
	htmlPostTemplateSelect($post);
}

add_action( 'save_post', 'nodizity\\ndzt_save_postdata' );

/**
 * Save action handler
 * @param $post_id
 */
function ndzt_save_postdata( $post_id ) {
	// dont clear custom fields on autosave
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( !wp_verify_nonce( $_POST['ndzt_noncename'], plugin_basename( __FILE__ ) ) )
		return;

	$template_id = sanitize_text_field( $_POST['ndzt_template_id'] );
	update_post_meta( $post_id, '_ndzt_template_id', $template_id);
}

function ndzt_toplevel_page() {
	if (is_array($_POST)) {
		if (isset($_POST["ndzt_update_settings"])) {
			update_option("ndzt_disable_cache", isset($_POST["ndzt_disable_cache"]));
		}

		if (isset($_POST["ndzt_update_mappings"])) {
			foreach ($_POST as $k => $v) {
				$p = "/ndzt.*template/";
				if (preg_match($p, $k) === 1) {
					if (isset($_POST[$k])) {
						update_option($k, sanitize_text_field($v));
					}
				}
			}
		}
	}

	$value_default_template = get_option("ndzt_default_template");
	$value_404_template = get_option("ndzt_404_template");
	$value_home_template = get_option("ndzt_home_template");
	$value_disable_cache = get_option("ndzt_disable_cache");

	echo '
			<div class="wrap">
				<h2>Template settings</h2>
				<form action="" method="POST">
					<input type="hidden" name="ndzt_update_settings"/>
					<p>
						<input type="checkbox" name="ndzt_disable_cache" id="ndzt_disable_cache" '. ($value_disable_cache ? 'checked="checked"' : '') .'/>
						<label for="ndzt_disable_cache">disable template cache</label>
					</p>
					<input class="button-primary" type="submit" value="Save settings">
				</form>

				<br>
				<hr>

				<h2>Template mappings</h2>
				<form action="" method="POST">
					<input type="hidden" name="ndzt_update_mappings"/>
					<h3>Global required templates</h3>
					<table class="form-table">
						<tbody>
							<tr valign="top">
								<th scope="row"><label for="ndzt_default_template">Default template:</label></th>
								<td>' . htmlTemplateSelect("ndzt_default_template", $value_default_template, false) . '</td>
							</tr>
							<tr valign="top">
								<th scope="row"><label for="ndzt_404_template">404 template:</label></th>
								<td>' . htmlTemplateSelect("ndzt_404_template", $value_404_template, false) . '</td>
							</tr>
						</tbody>
					</table>

					<h3>Homepage template</h3>
					<table class="form-table">
						<tbody>
							<tr valign="top">
								<th scope="row"><label for="ndzt_home_template">Homepage template:</label></th>
								<td>' . htmlTemplateSelect("ndzt_home_template", $value_home_template, false) . '</td>
							</tr>
						</tbody>
					</table>

					<h3>Post Type template mappings</h3>

					<table class="form-table">
						<tbody>
							' . htmlPostTypesSelects() . '
						</tbody>
					</table>

					<br/>
					<input class="button-primary" type="submit" id="ndzt-submit-settings" name="ndzt-submit-settings" value="Save mappings">
				</form>
			</div>
		';
}

/**
 * Load vendor Jade compiler
 *
 * @return \Jade\Jade|null|string
 */
function loadJade() {
	global $jade;
	if (isset($jade)) return $jade;

	require __DIR__ . '/vendor/autoload.php';
	$jade = new \Jade\Jade(true);
	return $jade;
}

/**
 * Generate .php template file from given .jade template file
 * @param $template .jade template file
 * @return string path to .php template file
 */
function jade2php($template) {
	$jade = loadJade();
	return $jade->render($template);
}

/**
 * Manage template loading
 *
 * Checks if nodizity is set up OK <br/>
 * Gets cached .php or creates new from .jade <br/>
 * Returns 404 template if .jade is not found
 * @param $templateRoot
 * @param $cacheRoot nodizity cache dir
 * @param $templateName ,jade file name
 * @return null|string null if not set up, filename otherwise
 */
function renderJade($templateRoot, $cacheRoot, $templateName) {
	// check if plugin is set up: default and 404 are set and valid
	$templatesAreSet = (get_option("ndzt_default_template") !== "") && (get_option("ndzt_404_template") !== "");
	$templatesExist =   file_exists($templateRoot . '/' . get_option("ndzt_default_template") . '.jade') &&
						file_exists($templateRoot . '/' . get_option("ndzt_404_template") . '.jade');
	if (!$templatesExist || !$templatesAreSet) {
		header("HTTP/1.0 500 Internal Server Error");
		return null;
	}

	// check if template file exists
	$jadeFile = $templateRoot . '/' . $templateName . '.jade';
	if (!file_exists($jadeFile)) {
		$templateName = get_option("ndzt_404_template");
		$jadeFile = $templateRoot . '/' . $templateName . '.jade';
	}
	// cache management
	$cacheDisabled = get_option("ndzt_disable_cache");
	$phpFile = $cacheRoot . '/' . $templateName . '.php';
	if ($cacheDisabled || !file_exists($phpFile) || filemtime($phpFile) < filemtime($jadeFile)) {
		@mkdir(dirname($phpFile), 0770, true);
		file_put_contents($phpFile, jade2php($jadeFile));
	}
	include $phpFile;
	return $phpFile;
}

/**
 * Get template file name for current wp_query
 *
 * @return mixed template file name
 */
function route() {
	if (is_singular() /* any post type */) {
		global $post;
		
		// from post
		$t = get_post_meta($post->ID, '_ndzt_template_id', true);
		if (!empty($t)) return $t;

		// from post_type
		$t = get_option("ndzt_". $post->post_type . "_template");
		if (!empty($t)) return $t;
	} elseif (is_404()) {
		return get_option("ndzt_404_template");
	} elseif (is_home()) {
		$t = get_option("ndzt_home_template");
		if (!empty($t)) return $t;
	} else {
		global $wp_query;
		echo '<pre>'; print_r($wp_query); die('</pre>');
	}

	// default
	return get_option("ndzt_default_template");
}

/**
 * Call this from template index.php to use nodizity
 * @param $templateRoot template dir
 */
function jade($templateRoot) {
	$return = renderJade($templateRoot, __DIR__ . '/cache', route());
	echo ($return === null) ? "Template rendering error." : "";
}

/**
 * get all available templates from theme directory
 *
 * @param bool $exludeSpecials set false to include default and 404 theme templates to list
 * @return array names of template files
 */
function getTemplatesList($excludeSpecials = true) {
	$jadeFiles = glob(get_stylesheet_directory() . "/*.jade");
	$return = array();
	foreach($jadeFiles as $jadeFile)
	{
		$item = basename($jadeFile, ".jade");
		if (!($excludeSpecials && ($item === get_option("ndzt_default_template") || $item === get_option("ndzt_404_template")))) {
				$return[] = $item;
		}
	}
	return($return);
}

/**
 * generate template selectors for all public post types
 * @return string
 */
function htmlPostTypesSelects() {
	$args=array(
		'public'   => true
	);
	$post_types = get_post_types($args, 'objects');

	ob_start();
	foreach ($post_types as $post_type ) {
		echo '<tr><th scope="row">';
		echo $post_type->label;
		echo '</th>';
		$htmlName = 'ndzt_' . $post_type->name . '_template';
		echo '<td>' . htmlTemplateSelect($htmlName, get_option($htmlName)) . '</td></tr>';
	}
	$return = ob_get_contents();
	ob_end_clean();
	return $return;
}

/**
 * generate HTML Select for available templates
 *
 * @param $htmlName html tag params: name & id
 * @param string $selectedOption mark saved option as Selected
 * @param bool $exludeSpecials set false to include default and 404 theme templates to list
 * @return string generated HTML
 */
function htmlTemplateSelect($htmlName, $selectedOption = "", $exludeSpecials = true) {
	ob_start();
	echo '<select id="' . $htmlName . '" name="' . $htmlName . '">';
	echo '<option value="">No template</option>';
	foreach(getTemplatesList($exludeSpecials) as $option) {
		$selectedHTML = "";
		if($option === $selectedOption) {
			$selectedHTML = 'selected="selected"';
		}
		echo '<option value="' . $option . '" ' . $selectedHTML .'>' . $option . '</option>';
	}
	echo '</select>';
	$return = ob_get_contents();
	ob_end_clean();
	return $return;
}

function htmlPostTemplateSelect($post) {
	echo '<select id="ndzt_template_id" name="ndzt_template_id" style="width:100%;">';
	echo '	<option value="">-- default template --</option>';
	foreach(getTemplatesList() as $option) {
		$selectedHTML = "";
		if($option === get_post_meta( $post->ID, $key = '_ndzt_template_id', $single = true )) {
			$selectedHTML = 'selected="selected"';
		}
		if($option === get_option('ndzt_' . $post->post_type . '_template')) {
			continue;
		}
		echo '	<option value="' . $option . '" ' . $selectedHTML .'>' . $option . '</option>';
	}
	echo '</select>';
}


