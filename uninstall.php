<?php

/**
 * Citatly – Daily Quote
 * Uninstall routine — removes all plugin data.
 *
 * Deletes all quote posts (CPT: citatly_quote), their associated
 * meta fields, and the transient cache. Use the export function
 * before deleting the plugin if you want to keep your quotes.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

$citatly_post_ids = get_posts([
	'post_type'      => 'citatly_quote',
	'post_status'    => 'any',
	'posts_per_page' => -1,
	'fields'         => 'ids',
	'no_found_rows'  => true,
]);

foreach ($citatly_post_ids as $citatly_post_id) {
	wp_delete_post($citatly_post_id, true);
}

delete_transient('citatly_quote_ids');
