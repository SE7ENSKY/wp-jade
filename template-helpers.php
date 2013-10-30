<?php
/**
 * Get full path to given file in template dir
 * @param string $filename 
 * @return string
 */
function td() {
	return get_template_directory_uri();
}

function st($string) {
	return icl_t('template', $string, $string);
}

function tf($name, $params = "") {
	// return types_render_field($name, wp_parse_args($params));
	return get_post_meta(get_the_ID(), "wpcf-$name", true);
}

function qstart($query) {
	return new WP_Query($query);
}
function qreset() {
	wp_reset_postdata();
}

/**
 * Get WP-views field from post with given ID  
 * @param string $post_id 
 * @param string $fieldName without "wcpf-" prefix
 * @param array $args 
 * @return string html output
 */
function field() {
	global $post;
	$oldPost = null;
	switch (func_num_args()) {
		case 1:
			list($fieldName) = func_get_args();
			$args = array();
			break;
		case 2:
			list($fieldName, $args) = func_get_args();
			break;
		case 3:
			list($id, $fieldName, $args) = func_get_args();
			$oldPost = $post;
			$post = get_post($id);
	}

	if (is_string($args))
		$args = wp_parse_args($args);
	
	$value = types_render_field($fieldname, $args);
	if ($oldPost)
		$post = $oldpost;

	return $value;
}
