<?php

declare(strict_types=1);

/**
 * Plugin Name: Citatly - Daily Quote
 * Description: CPT for quotes + display as quote of the day (AJAX/REST, cache-safe).
 * Version: 1.3.5
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Dieter Geiling
 * Author URI: https://citatly.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: citatly-daily-quote
 */

if (!defined('ABSPATH')) {
	exit;
}
final class Citatly_Plugin
{
	private const VERSION = '1.3.5';
	private const CPT = 'citatly_quote';

	// Plaintext-Metafelder
	private const META_TEXT = '_citatly_text';
	private const META_AUTHOR = '_citatly_author';
	private const META_EXTRA = '_citatly_extra';

	private const REST_NAMESPACE = 'citatly/v1';
	private const REST_ROUTE = '/today';
	private const SHORTCODE = 'citatly';
	private const NONCE_ACTION = 'citatly_save_meta';
	private const NONCE_NAME = '_citatly_nonce';

	public static function init(): void
	{
		$instance = new self();

		add_action('init', function () {
			load_plugin_textdomain('citatly-daily-quote', false, dirname(plugin_basename(__FILE__)) . '/languages');
		});
		add_action('init', [$instance, 'register_cpt']);
		add_action('add_meta_boxes', [$instance, 'register_meta_boxes']);
		add_action('save_post_' . self::CPT, [$instance, 'save_meta'], 10, 2);
		add_action('trashed_post', [$instance, 'invalidate_ids_cache']);
		add_action('deleted_post', [$instance, 'invalidate_ids_cache']);

		// Titel automatisch füllen, wenn leer (aus dem Zitat-Text)
		add_filter('wp_insert_post_data', [$instance, 'autofill_title'], 10, 2);

		add_action('rest_api_init', [$instance, 'register_rest']);
		add_shortcode(self::SHORTCODE, [$instance, 'shortcode']);

		add_action('wp_enqueue_scripts', [$instance, 'register_assets']);
		add_action('init', [$instance, 'register_block']);
		add_action('enqueue_block_editor_assets', [$instance, 'set_block_translations']);

		add_filter('manage_' . self::CPT . '_posts_columns', [$instance, 'admin_columns']);
		add_action('manage_' . self::CPT . '_posts_custom_column', [$instance, 'admin_column_content'], 10, 2);

		// Stabile Sortierung in der Admin-Liste: post_date + ID verhindert Duplikate bei Paginierung
		add_action('pre_get_posts', [$instance, 'stable_admin_sort']);

		// Admin-Suche auf Metafelder erweitern (Autor, Zusatzinfo, Zitattext)
		add_action('pre_get_posts', [$instance, 'extend_admin_search']);

		add_action('admin_menu', [$instance, 'register_admin_menu']);
		add_action('admin_menu', [$instance, 'register_help_page']);
		add_action('admin_enqueue_scripts', [$instance, 'enqueue_admin_export_script']);
		add_action('admin_post_citatly_import', [$instance, 'handle_import']);

		add_action('edit_form_after_title', [$instance, 'render_title_hint']);

		// Perfmatters REST-API Ausnahme automatisch setzen, falls REST-API via Perfmatters eingeschränkt
		add_filter('perfmatters_rest_api_exceptions', [$instance, 'perfmatters_exceptions']);
	}

	public function register_cpt(): void
	{
		$labels = [
			'name'               => __('Quotes', 'citatly-daily-quote'),
			'singular_name'      => __('Quote', 'citatly-daily-quote'),
			'add_new'            => __('Add New', 'citatly-daily-quote'),
			'add_new_item'       => __('Add New Quote', 'citatly-daily-quote'),
			'edit_item'          => __('Edit Quote', 'citatly-daily-quote'),
			'new_item'           => __('New Quote', 'citatly-daily-quote'),
			'view_item'          => __('View Quote', 'citatly-daily-quote'),
			'search_items'       => __('Search Quotes', 'citatly-daily-quote'),
			'not_found'          => __('No quotes found', 'citatly-daily-quote'),
			'not_found_in_trash' => __('No quotes found in Trash', 'citatly-daily-quote'),
		];

		register_post_type(self::CPT, [
			'labels' => $labels,
			'public' => false,
			'show_ui' => true,
			'show_in_menu' => true,
			'menu_position' => 25,
			'menu_icon' => 'dashicons-format-quote',

			// Wichtig: KEIN Editor. Nur Titel + Metabox mit PlainText.
			'supports' => ['title'],

			'has_archive' => false,
			'rewrite' => false,
			'show_in_rest' => false,
			'capability_type' => 'post',
		]);
	}

	/**
	 * Wenn kein Titel gesetzt ist, generieren wir ihn aus dem Zitat (PlainText).
	 * So verschwindet "Automatisch gespeicherter Entwurf" aus der Liste.
	 */
	public function admin_columns(array $columns): array
	{
		$new = [];
		foreach ($columns as $key => $label) {
			$new[$key] = $label;
			if ($key === 'title') {
				$new['citatly_author'] = __('Author', 'citatly-daily-quote');
				$new['citatly_extra']  = __('Additional Info', 'citatly-daily-quote');
			}
		}
		return $new;
	}

	public function admin_column_content(string $column, int $post_id): void
	{
		if ($column === 'citatly_author') {
			$value = (string) get_post_meta($post_id, self::META_AUTHOR, true);
			echo esc_html($value !== '' ? $value : '—');
		} elseif ($column === 'citatly_extra') {
			$value = (string) get_post_meta($post_id, self::META_EXTRA, true);
			echo esc_html($value !== '' ? $value : '—');
		}
	}

	/**
	 * Stabile Sortierung in der Admin-Liste.
	 *
	 * Beim Import erhalten alle Zitate dasselbe post_date. Ohne sekundäres
	 * Sortierkriterium ist die Reihenfolge bei identischem Datum nicht
	 * deterministisch — MySQL kann Posts zwischen LIMIT-Abfragen (Seiten)
	 * verschieben, sodass ein Eintrag auf zwei Seiten erscheint.
	 */
	public function stable_admin_sort(\WP_Query $query): void
	{
		if (!is_admin() || !$query->is_main_query()) {
			return;
		}
		if ($query->get('post_type') !== self::CPT) {
			return;
		}

		$orderby = $query->get('orderby');

		// Nur eingreifen bei Standard-Sortierung (Datum) oder wenn nicht explizit anders gesetzt
		if ($orderby === '' || $orderby === 'date') {
			$order = $query->get('order') ?: 'DESC';
			$query->set('orderby', ['date' => $order, 'ID' => $order]);
		}
	}

	/**
	 * Admin-Suche auf Metafelder erweitern.
	 *
	 * WordPress durchsucht standardmäßig nur post_title und post_content.
	 * Da Zitate in Custom Fields gespeichert werden, erweitern wir die
	 * Suche auf _citatly_text, _citatly_author und _citatly_extra.
	 */
	public function extend_admin_search(\WP_Query $query): void
	{
		if (!is_admin() || !$query->is_main_query() || !$query->is_search()) {
			return;
		}
		if ($query->get('post_type') !== self::CPT) {
			return;
		}

		$search = $query->get('s');
		if ($search === '') {
			return;
		}

		// Suchbegriff aus der Hauptquery entfernen — wir decken alles über meta_query ab
		$query->set('s', '');

		$query->set('meta_query', [
			'relation' => 'OR',
			[
				'key'     => self::META_TEXT,
				'value'   => $search,
				'compare' => 'LIKE',
			],
			[
				'key'     => self::META_AUTHOR,
				'value'   => $search,
				'compare' => 'LIKE',
			],
			[
				'key'     => self::META_EXTRA,
				'value'   => $search,
				'compare' => 'LIKE',
			],
		]);
	}

	public function autofill_title(array $data, array $postarr): array
	{
		if (($data['post_type'] ?? '') !== self::CPT) {
			return $data;
		}

		$title = isset($data['post_title']) ? trim((string) $data['post_title']) : '';
		if ($title !== '') {
			return $data;
		}

		// Metabox-Feld kommt beim Speichern via POST.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in save_meta()
		$quote = '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in save_meta()
		if (isset($_POST['citatly_text']) && is_string($_POST['citatly_text'])) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce is verified in save_meta()
			$quote = sanitize_textarea_field(wp_unslash($_POST['citatly_text']));
		}

		$quote = trim((string) preg_replace('/\s+/', ' ', $quote));
		if ($quote === '') {
			$data['post_title'] = __('Quote', 'citatly-daily-quote') . ' ' . wp_date('Y-m-d H:i');
			return $data;
		}

		$max = 80;
		$short = function_exists('mb_substr') ? mb_substr($quote, 0, $max) : substr($quote, 0, $max);
		$len = function_exists('mb_strlen') ? mb_strlen($quote) : strlen($quote);
		if ($len > $max) {
			$short .= '…';
		}

		$data['post_title'] = $short;
		return $data;
	}

	public function register_meta_boxes(): void
	{
		add_meta_box(
			'citatly_meta',
			__('Quote Details', 'citatly-daily-quote'),
			[$this, 'render_meta_box'],
			self::CPT,
			'normal',
			'default'
		);
	}

	public function render_title_hint(\WP_Post $post): void
	{
		if ($post->post_type !== self::CPT) {
			return;
		}
		echo '<p class="description" style="margin-top:4px;">'
			. esc_html(__('Optional. If left empty, the title is generated automatically from the first 80 characters of the quote text.', 'citatly-daily-quote'))
			. '</p>';
	}

	public function render_meta_box(\WP_Post $post): void
	{
		$text = (string) get_post_meta($post->ID, self::META_TEXT, true);
		$author = (string) get_post_meta($post->ID, self::META_AUTHOR, true);
		$extra = (string) get_post_meta($post->ID, self::META_EXTRA, true);

		wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
?>
		<p>
			<label for="citatly_text"><strong><?php echo esc_html(__('Quote', 'citatly-daily-quote')); ?></strong> <?php echo esc_html(__('(plain text only, no formatting)', 'citatly-daily-quote')); ?></label><br>
			<textarea id="citatly_text" name="citatly_text" rows="6" style="width: 100%;" spellcheck="true"><?php echo esc_textarea($text); ?></textarea>
		</p>
		<p>
			<label for="citatly_author"><strong><?php echo esc_html(__('Author', 'citatly-daily-quote')); ?></strong></label><br>
			<input type="text" id="citatly_author" name="citatly_author" value="<?php echo esc_attr($author); ?>" style="width: 100%;" autocomplete="off">
		</p>
		<p>
			<label for="citatly_extra"><strong><?php echo esc_html(__('Additional Info', 'citatly-daily-quote')); ?></strong> <?php echo esc_html(__('(optional)', 'citatly-daily-quote')); ?></label><br>
			<input type="text" id="citatly_extra" name="citatly_extra" value="<?php echo esc_attr($extra); ?>" style="width: 100%;" autocomplete="off">
		</p>
		<p style="color:#666;">
			<?php echo esc_html(__('Note: Only plain text is stored and output (no links, no HTML).', 'citatly-daily-quote')); ?>
		</p>
	<?php
	}

	public function save_meta(int $post_id, \WP_Post $post): void
	{
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
			return;
		}
		if (wp_is_post_revision($post_id)) {
			return;
		}

		$nonce = isset($_POST[self::NONCE_NAME]) ? sanitize_text_field(wp_unslash($_POST[self::NONCE_NAME])) : '';
		if (!is_string($nonce) || !wp_verify_nonce($nonce, self::NONCE_ACTION)) {
			return;
		}

		if (!current_user_can('edit_post', $post_id)) {
			return;
		}

		$text = isset($_POST['citatly_text']) ? sanitize_textarea_field(wp_unslash((string) $_POST['citatly_text'])) : '';
		$author = isset($_POST['citatly_author']) ? sanitize_text_field(wp_unslash((string) $_POST['citatly_author'])) : '';
		$extra = isset($_POST['citatly_extra']) ? sanitize_text_field(wp_unslash((string) $_POST['citatly_extra'])) : '';

		update_post_meta($post_id, self::META_TEXT, $text);
		update_post_meta($post_id, self::META_AUTHOR, $author);
		update_post_meta($post_id, self::META_EXTRA, $extra);

		delete_transient('citatly_quote_ids');
	}

	/**
	 * Cache invalidieren wenn ein Zitat gelöscht oder in den Papierkorb verschoben wird.
	 */
	public function invalidate_ids_cache(int $post_id): void
	{
		if (get_post_type($post_id) === self::CPT) {
			delete_transient('citatly_quote_ids');
		}
	}

	public function register_rest(): void
	{
		register_rest_route(self::REST_NAMESPACE, self::REST_ROUTE, [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [$this, 'rest_today'],
			'permission_callback' => '__return_true',
		]);

		register_rest_route(self::REST_NAMESPACE, '/export', [
			'methods'             => \WP_REST_Server::READABLE,
			'callback'            => [$this, 'rest_export'],
			'permission_callback' => fn() => current_user_can('manage_options'),
		]);
	}

	public function rest_export(\WP_REST_Request $request): \WP_REST_Response
	{
		$quotes = [];

		foreach ($this->get_published_quote_ids() as $post_id) {
			$text = (string) get_post_meta($post_id, self::META_TEXT, true);
			if ($text === '') {
				continue;
			}

			$quotes[] = [
				'text'   => $text,
				'author' => (string) get_post_meta($post_id, self::META_AUTHOR, true),
				'extra'  => (string) get_post_meta($post_id, self::META_EXTRA,  true),
			];
		}

		return new \WP_REST_Response($quotes, 200);
	}

	public function rest_today(\WP_REST_Request $request): \WP_REST_Response
	{
		$quote = $this->get_quote_of_the_day();

		$now = new \DateTimeImmutable('now', wp_timezone());
		$tomorrow = $now->modify('tomorrow')->setTime(0, 0, 0);
		$max_age = max(60, $tomorrow->getTimestamp() - $now->getTimestamp());

		$response = new \WP_REST_Response($quote, 200);
		$response->header('Cache-Control', 'public, max-age=' . $max_age . ', must-revalidate');
		$response->header('Expires', gmdate('D, d M Y H:i:s', time() + $max_age) . ' GMT');
		return $response;
	}

	private function get_quote_of_the_day(): array
	{
		$ids = $this->get_published_quote_ids();

		if (empty($ids)) {
			return [
				'has_quote' => false,
				'text'      => '',
				'author'    => '',
				'extra'     => '',
			];
		}

		$tz        = wp_timezone();
		$count     = count($ids);
		$today     = (new \DateTimeImmutable('now', $tz))->format('Y-m-d');
		$yesterday = (new \DateTimeImmutable('yesterday', $tz))->format('Y-m-d');
		$site      = home_url();

		$index_today     = (int)(crc32($today     . '|' . $site) % $count);
		$index_yesterday = (int)(crc32($yesterday . '|' . $site) % $count);

		// Direkte Wiederholung verhindern: anderen Index per Fallback-Salt berechnen
		if ($count > 1 && $index_today === $index_yesterday) {
			$index_today = (int)(crc32($today . '|' . $site . '|fallback') % $count);
			// Äußerste Absicherung falls Fallback ebenfalls kollidiert
			if ($index_today === $index_yesterday) {
				$index_today = ($index_today + 1) % $count;
			}
		}

		$post_id = (int) $ids[$index_today];

		$post = get_post($post_id);
		if (!$post instanceof \WP_Post || $post->post_status !== 'publish') {
			return [
				'has_quote' => false,
				'text'      => '',
				'author'    => '',
				'extra'     => '',
			];
		}

		$text   = (string) get_post_meta($post_id, self::META_TEXT,   true);
		$author = (string) get_post_meta($post_id, self::META_AUTHOR, true);
		$extra  = (string) get_post_meta($post_id, self::META_EXTRA,  true);

		return [
			'has_quote' => true,
			'text'      => $text,
			'author'    => $author,
			'extra'     => $extra,
		];
	}

	private function get_published_quote_ids(): array
	{
		$cached = get_transient('citatly_quote_ids');
		if (is_array($cached) && !empty($cached)) {
			return array_values(array_filter(array_map('intval', $cached)));
		}

		$q = new \WP_Query([
			'post_type' => self::CPT,
			'post_status' => 'publish',
			'posts_per_page' => 5000,
			'fields' => 'ids',
			'no_found_rows' => true,
			'orderby' => 'ID',
			'order' => 'ASC',
		]);

		$ids = is_array($q->posts) ? array_values(array_filter(array_map('intval', $q->posts))) : [];
		set_transient('citatly_quote_ids', $ids, DAY_IN_SECONDS);
		return $ids;
	}

	// -------------------------------------------------------------------------
	// Import / Export
	// -------------------------------------------------------------------------

	public function register_admin_menu(): void
	{
		add_submenu_page(
			'edit.php?post_type=' . self::CPT,
			__('Import / Export', 'citatly-daily-quote'),
			__('Import / Export', 'citatly-daily-quote'),
			'manage_options',
			'citatly-import-export',
			[$this, 'render_import_export_page']
		);
	}

	public function render_import_export_page(): void
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html(__('No permission.', 'citatly-daily-quote')));
		}

		$redirect_base = admin_url('edit.php?post_type=' . self::CPT . '&page=citatly-import-export');

		// Erfolgs- / Fehlermeldung aus Redirect-Parametern aufbauen
		$notice = '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Integer-Cast, Werte kommen aus eigenem wp_safe_redirect
		if (isset($_GET['citatly_imported'])) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Integer-Cast, Werte kommen aus eigenem wp_safe_redirect
			$imported = (int) $_GET['citatly_imported'];
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Integer-Cast, Werte kommen aus eigenem wp_safe_redirect
			$skipped  = isset($_GET['citatly_skipped']) ? (int) $_GET['citatly_skipped'] : 0;

			$parts = [];
			$parts[] = esc_html(sprintf(
				// translators: %d: number of quotes imported
				_n('%d quote imported', '%d quotes imported', $imported, 'citatly-daily-quote'),
				$imported
			));
			if ($skipped > 0) {
				$parts[] = esc_html(sprintf(
					// translators: %d: number of duplicate quotes skipped
					_n('%d already exists (skipped)', '%d already exist (skipped)', $skipped, 'citatly-daily-quote'),
					$skipped
				));
			}
			$notice = '<div class="notice notice-success is-dismissible"><p>' . implode(', ', $parts) . '.</p></div>';
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sanitize_key angewendet, Werte kommen aus eigenem wp_safe_redirect
		} elseif (isset($_GET['citatly_import_error'])) {
			$messages = [
				'no_file'      => __('No file selected.', 'citatly-daily-quote'),
				'upload_error' => __('File upload error.', 'citatly-daily-quote'),
				'invalid_type' => __('Invalid file type – please upload a JSON file.', 'citatly-daily-quote'),
				'too_large'    => __('The file is too large (max. 2 MB).', 'citatly-daily-quote'),
				'parse_error'  => __('The JSON file could not be read or has an invalid format.', 'citatly-daily-quote'),
				'empty'        => __('The JSON file contains no entries.', 'citatly-daily-quote'),
			];
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- sanitize_key angewendet, Werte kommen aus eigenem wp_safe_redirect
			$key     = sanitize_key((string) ($_GET['citatly_import_error'] ?? ''));
			$message = $messages[$key] ?? __('Unknown error.', 'citatly-daily-quote');
			$notice  = '<div class="notice notice-error is-dismissible"><p>' . esc_html($message) . '</p></div>';
		}
	?>
		<div class="wrap">
			<h1><?php echo esc_html(__('Quotes Import / Export', 'citatly-daily-quote')); ?></h1>

			<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput -- vollständig escapet oben
			?>

			<div style="display:grid;grid-template-columns:1fr 1fr;gap:2em;margin-top:1.5em;max-width:900px;">

				<div class="postbox">
					<div class="postbox-header" style="padding: 0 14px;">
						<h2 class="hndle"><?php echo esc_html(__('Export', 'citatly-daily-quote')); ?></h2>
					</div>
					<div class="inside">
						<p><?php echo esc_html(__('Download all published quotes as a JSON file (fields: text, author, extra).', 'citatly-daily-quote')); ?></p>
						<button id="citatly-export-btn" class="button button-primary">
							<?php echo esc_html(__('Export JSON', 'citatly-daily-quote')); ?>
						</button>
						<span id="citatly-export-status" style="margin-left:8px;"></span>
					</div>
				</div>

				<div class="postbox">
					<div class="postbox-header" style="padding: 0 14px;">
						<h2 class="hndle"><?php echo esc_html(__('Import', 'citatly-daily-quote')); ?></h2>
					</div>
					<div class="inside">
						<p><?php echo esc_html(__('Upload a JSON file (array of objects with fields text, author, extra). Existing quotes (same text) will be skipped.', 'citatly-daily-quote')); ?></p>
						<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
							<?php wp_nonce_field('citatly_import', 'citatly_import_nonce'); ?>
							<input type="hidden" name="action" value="citatly_import">
							<p><input type="file" name="citatly_csv" accept=".json" required></p>
							<?php submit_button(__('Import JSON', 'citatly-daily-quote'), 'primary', 'submit', false); ?>
						</form>
					</div>
				</div>

			</div>
		</div>
	<?php
	}

	public function enqueue_admin_export_script(string $hook): void
	{
		// Nur auf der Import/Export-Seite laden.
		// Hook-Name für Untermenü unter einem CPT: {post_type}_page_{menu_slug}
		if ($hook !== 'citatly_quote_page_citatly-import-export') {
			return;
		}

		wp_enqueue_script(
			'citatly-admin-export',
			plugins_url('admin-export.js', __FILE__),
			[],
			self::VERSION,
			true
		);

		wp_localize_script('citatly-admin-export', 'CitatlyExport', [
			'endpoint'     => esc_url_raw(rest_url(self::REST_NAMESPACE . '/export')),
			'nonce'        => wp_create_nonce('wp_rest'),
			'filename'     => 'citatly-export-' . gmdate('Y-m-d') . '.json',
			'labelExport'  => __('Export JSON', 'citatly-daily-quote'),
			'labelLoading' => __('Exporting…', 'citatly-daily-quote'),
			'labelError'   => __('Export error:', 'citatly-daily-quote'),
		]);
	}

	public function handle_import(): void
	{
		check_admin_referer('citatly_import', 'citatly_import_nonce');

		if (!current_user_can('manage_options')) {
			wp_die(esc_html(__('No permission.', 'citatly-daily-quote')));
		}

		$base = admin_url('edit.php?post_type=' . self::CPT . '&page=citatly-import-export');

		// Upload-Fehler prüfen
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$upload_error = (int) ($_FILES['citatly_csv']['error'] ?? UPLOAD_ERR_NO_FILE);

		if ($upload_error === UPLOAD_ERR_NO_FILE) {
			wp_safe_redirect($base . '&citatly_import_error=no_file');
			exit;
		}
		if ($upload_error !== UPLOAD_ERR_OK) {
			wp_safe_redirect($base . '&citatly_import_error=upload_error');
			exit;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$tmp  = (string) ($_FILES['citatly_csv']['tmp_name'] ?? '');
		$name = sanitize_file_name(wp_unslash((string) ($_FILES['citatly_csv']['name'] ?? '')));
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$size = (int) ($_FILES['citatly_csv']['size'] ?? 0);

		// Größe (max. 2 MB)
		if ($size > 2 * 1024 * 1024) {
			wp_safe_redirect($base . '&citatly_import_error=too_large');
			exit;
		}

		// Dateiendung
		if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'json') {
			wp_safe_redirect($base . '&citatly_import_error=invalid_type');
			exit;
		}

		// Sicherstellen, dass es ein echter Upload ist
		if (!is_uploaded_file($tmp)) {
			wp_safe_redirect($base . '&citatly_import_error=upload_error');
			exit;
		}

		// JSON einlesen und dekodieren
		$raw = file_get_contents($tmp);
		if ($raw === false) {
			wp_safe_redirect($base . '&citatly_import_error=parse_error');
			exit;
		}

		$items = json_decode($raw, true);
		if (!is_array($items) || json_last_error() !== JSON_ERROR_NONE) {
			wp_safe_redirect($base . '&citatly_import_error=parse_error');
			exit;
		}

		if (empty($items)) {
			wp_safe_redirect($base . '&citatly_import_error=empty');
			exit;
		}

		// Vorhandene Texte für Duplikat-Prüfung vorladen
		$existing = $this->get_existing_quote_texts();

		$imported = 0;
		$skipped  = 0;

		foreach ($items as $item) {
			if (!is_array($item) || !array_key_exists('text', $item)) {
				continue;
			}

			$text   = sanitize_textarea_field(trim((string) $item['text']));
			$author = isset($item['author']) ? sanitize_text_field(trim((string) $item['author'])) : '';
			$extra  = isset($item['extra'])  ? sanitize_text_field(trim((string) $item['extra']))  : '';

			if ($text === '') {
				continue;
			}

			if (in_array($text, $existing, true)) {
				$skipped++;
				continue;
			}

			// Kurztitel aus dem Zitat-Text (identisch mit autofill_title-Logik)
			$max   = 80;
			$short = function_exists('mb_substr') ? mb_substr($text, 0, $max) : substr($text, 0, $max);
			$len   = function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
			$title = $len > $max ? $short . '…' : $short;

			$post_id = wp_insert_post([
				'post_type'   => self::CPT,
				'post_status' => 'publish',
				'post_title'  => $title,
			], true);

			if (is_wp_error($post_id)) {
				continue;
			}

			update_post_meta($post_id, self::META_TEXT,   $text);
			update_post_meta($post_id, self::META_AUTHOR, $author);
			update_post_meta($post_id, self::META_EXTRA,  $extra);

			// Innerhalb desselben Imports ebenfalls auf Duplikate prüfen
			$existing[] = $text;
			$imported++;
		}

		if ($imported > 0) {
			delete_transient('citatly_quote_ids');
		}

		wp_safe_redirect($base . '&citatly_imported=' . $imported . '&citatly_skipped=' . $skipped);
		exit;
	}

	public function perfmatters_exceptions(array $exceptions): array
	{
		$exceptions[] = 'citatly/v1/today';
		return $exceptions;
	}

	private function get_existing_quote_texts(): array
	{
		$texts = [];
		foreach ($this->get_published_quote_ids() as $post_id) {
			$text = (string) get_post_meta($post_id, self::META_TEXT, true);
			if ($text !== '') {
				$texts[] = $text;
			}
		}
		return $texts;
	}

	// -------------------------------------------------------------------------
	// Hilfeseite
	// -------------------------------------------------------------------------

	public function register_help_page(): void
	{
		add_submenu_page(
			'edit.php?post_type=' . self::CPT,
			__('Help', 'citatly-daily-quote'),
			__('Help', 'citatly-daily-quote'),
			'edit_posts',
			'citatly-help',
			[$this, 'render_help_page']
		);
	}

	public function render_help_page(): void
	{
		if (!current_user_can('edit_posts')) {
			wp_die(esc_html(__('No permission.', 'citatly-daily-quote')));
		}

		$doc_url = get_user_locale() === 'de_DE'
			? 'https://citatly.com/de/docs'
			: 'https://citatly.com/docs';
	?>
		<div class="notice notice-info">
			<p>
				<span class="dashicons dashicons-book-alt" style="vertical-align:middle;margin-right:4px;"></span>
				<?php
				echo wp_kses(
					sprintf(
						/* translators: 1: URL to documentation, 2: link label */
						__('The most up-to-date documentation is available at <a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>.', 'citatly-daily-quote'),
						esc_url($doc_url),
						'citatly.com/docs'   // EN-Fallback; DE-Übersetzung überschreibt den ganzen String
					),
					['a' => ['href' => [], 'target' => [], 'rel' => []]]
				);
				?>
			</p>
		</div>
		<?php

		$rest_url  = esc_url(rest_url(self::REST_NAMESPACE . self::REST_ROUTE));
		$pre_style = 'background:#f6f7f7;border:1px solid #dcdcde;border-radius:2px;'
			. 'padding:8px 10px;overflow-x:auto;font-size:13px;line-height:1.6;'
			. 'display:block;margin:4px 0 8px;white-space:pre;';
		?>
		<div class="wrap">
			<h1><?php echo esc_html(__('Citatly – Help', 'citatly-daily-quote')); ?></h1>
			<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5em;margin-top:1.5em;max-width:1100px;">

				<div class="postbox">
					<div class="postbox-header" style="padding:0 14px;">
						<h2 class="hndle"><?php echo esc_html(__('Quick Start', 'citatly-daily-quote')); ?></h2>
					</div>
					<div class="inside">
						<ol>
							<li><?php echo esc_html(__('Activate the plugin.', 'citatly-daily-quote')); ?></li>
							<li><?php echo esc_html(__('Go to Quotes → Add New and create your first quote.', 'citatly-daily-quote')); ?></li>
							<li><?php echo esc_html(__('Add the shortcode [citatly] to any page or post.', 'citatly-daily-quote')); ?></li>
							<li><?php echo esc_html(__('The quote of the day changes automatically at midnight.', 'citatly-daily-quote')); ?></li>
						</ol>
					</div>
				</div>

				<div class="postbox">
					<div class="postbox-header" style="padding:0 14px;">
						<h2 class="hndle"><?php echo esc_html(__('Shortcode', 'citatly-daily-quote')); ?></h2>
					</div>
					<div class="inside">
						<p><?php echo esc_html(__('Basic usage:', 'citatly-daily-quote')); ?></p>
						<pre style="<?php echo esc_attr($pre_style); ?>">[citatly]</pre>
						<p><?php echo esc_html(__('With a custom CSS class:', 'citatly-daily-quote')); ?></p>
						<pre style="<?php echo esc_attr($pre_style); ?>">[citatly class="my-style"]</pre>
						<p><?php echo esc_html(__('Generated HTML structure:', 'citatly-daily-quote')); ?></p>
						<pre style="<?php echo esc_attr($pre_style); ?>"><?php echo esc_html(
																				'<div class="citatly">
  <div class="citatly__text"></div>
  <div class="citatly__meta">
    <span class="citatly__separator"></span>
    <span class="citatly__author"></span>
    <span class="citatly__divider"></span>
    <span class="citatly__source"></span>
  </div>
</div>'
																			); ?></pre>
					</div>
				</div>

				<div class="postbox">
					<div class="postbox-header" style="padding:0 14px;">
						<h2 class="hndle"><?php echo esc_html(__('Gutenberg Block', 'citatly-daily-quote')); ?></h2>
					</div>
					<div class="inside">
						<p><?php echo esc_html(__('The Citatly block is available in the block editor. It requires the compiled /build directory inside the plugin folder.', 'citatly-daily-quote')); ?></p>
						<p><?php echo esc_html(__('The block supports the native WordPress "Additional CSS class(es)" field (className). The class is passed directly to the shortcode output.', 'citatly-daily-quote')); ?></p>
					</div>
				</div>

				<div class="postbox">
					<div class="postbox-header" style="padding:0 14px;">
						<h2 class="hndle"><?php echo esc_html(__('CSS Customization', 'citatly-daily-quote')); ?></h2>
					</div>
					<div class="inside">
						<p><?php echo esc_html(__('The following CSS classes are available for styling:', 'citatly-daily-quote')); ?></p>
						<ul style="margin:.5em 0 1em 1.5em;list-style:disc;">
							<li><code>.citatly</code> – <?php echo esc_html(__('outer container', 'citatly-daily-quote')); ?></li>
							<li><code>.citatly__text</code> – <?php echo esc_html(__('quote text', 'citatly-daily-quote')); ?></li>
							<li><code>.citatly__meta</code> – <?php echo esc_html(__('author and source wrapper', 'citatly-daily-quote')); ?></li>
							<li><code>.citatly__separator</code> – <?php echo esc_html(__('dash before author (default: "— ")', 'citatly-daily-quote')); ?></li>
							<li><code>.citatly__author</code> – <?php echo esc_html(__('author name', 'citatly-daily-quote')); ?></li>
							<li><code>.citatly__divider</code> – <?php echo esc_html(__('dot between author and source (default: " · ")', 'citatly-daily-quote')); ?></li>
							<li><code>.citatly__source</code> – <?php echo esc_html(__('source / additional info', 'citatly-daily-quote')); ?></li>
						</ul>
						<p><?php echo esc_html(__('Example:', 'citatly-daily-quote')); ?></p>
						<pre style="<?php echo esc_attr($pre_style); ?>"><?php echo esc_html(
																				'.citatly { max-width: 600px; }
.citatly__text { font-style: italic; font-size: 1.2em; }
.citatly__author { font-weight: bold; }
.citatly__source { color: #666; }

/* Hide separators */
.citatly__separator { display: none; }
.citatly__divider { display: none; }

/* Replace separators with custom text */
.citatly__separator { font-size: 0; }
.citatly__separator::after { content: "– "; font-size: 1rem; }
.citatly__divider { font-size: 0; }
.citatly__divider::after { content: " | "; font-size: 1rem; }'
																			); ?></pre>
						<p style="margin-top:8px;">
							<span class="dashicons dashicons-external" style="vertical-align:middle;margin-right:4px;" aria-hidden="true"></span>
							<?php
							$css_doc_url = get_user_locale() === 'de_DE'
								? 'https://citatly.com/de/docs/css-anpassung'
								: 'https://citatly.com/docs/css-styling';
							echo wp_kses(
								sprintf(
									/* translators: %s: URL to the CSS styling documentation page */
									__('Interactive examples are available at <a href="%s" target="_blank" rel="noopener noreferrer">citatly.com/docs/css-styling</a>.', 'citatly-daily-quote'),
									esc_url($css_doc_url)
								),
								['a' => ['href' => [], 'target' => [], 'rel' => []]]
							);
							?>
						</p>
					</div>
				</div>

				<div class="postbox">
					<div class="postbox-header" style="padding:0 14px;">
						<h2 class="hndle"><?php echo esc_html(__('REST Endpoint', 'citatly-daily-quote')); ?></h2>
					</div>
					<div class="inside">
						<p><strong><?php echo esc_html(__('URL:', 'citatly-daily-quote')); ?></strong></p>
						<pre style="<?php echo esc_attr($pre_style); ?>"><?php echo esc_html($rest_url); ?></pre>
						<p><?php echo esc_html(__('Method: GET – no authentication required.', 'citatly-daily-quote')); ?></p>
						<p><strong><?php echo esc_html(__('Response format:', 'citatly-daily-quote')); ?></strong></p>
						<pre style="<?php echo esc_attr($pre_style); ?>"><?php echo esc_html(
																				'{
  "has_quote": true,
  "text": "...",
  "author": "...",
  "extra": "..."
}'
																			); ?></pre>
						<p><?php echo esc_html(__('The response includes Cache-Control headers. The quote changes at midnight (site timezone).', 'citatly-daily-quote')); ?></p>
					</div>
				</div>

				<div class="postbox">
					<div class="postbox-header" style="padding:0 14px;">
						<h2 class="hndle"><?php echo esc_html(__('Import / Export', 'citatly-daily-quote')); ?></h2>
					</div>
					<div class="inside">
						<p><?php echo esc_html(__('Go to Quotes → Import / Export.', 'citatly-daily-quote')); ?></p>
						<p><?php echo esc_html(__('Format: JSON array of objects with the fields text (required), author and extra (both optional).', 'citatly-daily-quote')); ?></p>
						<p><?php echo esc_html(__('Example:', 'citatly-daily-quote')); ?></p>
						<pre style="<?php echo esc_attr($pre_style); ?>"><?php echo esc_html(
																				'[
  {
    "text": "Quote text",
    "author": "Author Name",
    "extra": "Source"
  }
]'
																			); ?></pre>
						<ul style="margin:.5em 0 0 1.5em;list-style:disc;">
							<li><?php echo esc_html(__('Quotes with identical text are skipped (duplicate check).', 'citatly-daily-quote')); ?></li>
							<li><?php echo esc_html(__('Maximum file size: 2 MB.', 'citatly-daily-quote')); ?></li>
						</ul>
					</div>
				</div>

				<div class="postbox" style="grid-column:1/-1;">
					<div class="postbox-header" style="padding:0 14px;">
						<h2 class="hndle"><?php echo esc_html(__('Managing Quotes', 'citatly-daily-quote')); ?></h2>
					</div>
					<div class="inside">
						<ul style="margin:.5em 0 0 1.5em;list-style:disc;">
							<li><?php echo esc_html(__('Quotes are stored as plain text only – no HTML, no links.', 'citatly-daily-quote')); ?></li>
							<li><?php echo esc_html(__('Line breaks in the text field are preserved in the frontend output (white-space: pre-line).', 'citatly-daily-quote')); ?></li>
							<li><?php echo esc_html(__('If no title is set, one is generated automatically from the first 80 characters of the quote text.', 'citatly-daily-quote')); ?></li>
						</ul>
					</div>
				</div>

				<div class="postbox" style="grid-column:1/-1;background:#f9f9f9;">
					<div class="postbox-header" style="padding:0 14px;">
						<h2 class="hndle">☕ Unterstütze das Projekt</h2>
					</div>
					<div class="inside">
						<p><?php echo esc_html(__('Citatly ist kostenlos. Wenn dir das Plugin hilft, freue ich mich über eine kleine Spende!', 'citatly-daily-quote')); ?></p>
						<p>
							<a href="https://ko-fi.com/dieterDG" target="_blank" rel="noopener noreferrer" class="button button-secondary">
								☕ Ko-fi Spende
							</a>
							<a href="https://github.com/sponsors/dieterDG" target="_blank" rel="noopener noreferrer" class="button button-secondary" style="margin-left:8px;">
								💙 GitHub Sponsors
							</a>
						</p>
					</div>
				</div>

			</div>
		</div>
<?php
	}

	// -------------------------------------------------------------------------

	public function register_block(): void
	{
		$build_dir = __DIR__ . '/build';
		if (! file_exists($build_dir . '/block.json')) {
			return;
		}

		register_block_type($build_dir, [
			'render_callback' => [$this, 'render_block'],
		]);
	}

	public function set_block_translations(): void
	{
		wp_set_script_translations(
			'citatly-daily-quote-editor-script',
			'citatly-daily-quote',
			plugin_dir_path(__FILE__) . 'languages'
		);
	}

	public function render_block(array $attributes): string
	{
		// WordPress speichert das native "Zusätzliche CSS-Klasse"-Feld als className.
		$css_class = isset($attributes['className']) ? trim((string) $attributes['className']) : '';
		return $this->shortcode($css_class !== '' ? ['class' => $css_class] : []);
	}

	public function register_assets(): void
	{
		$handle = 'citatly-frontend';

		wp_register_style(
			$handle,
			plugins_url('citatly.css', __FILE__),
			[],
			self::VERSION
		);

		wp_register_script($handle, plugins_url('citatly.js', __FILE__), [], self::VERSION, true);

		wp_localize_script($handle, 'Citatly', [
			'endpoint' => esc_url_raw(rest_url(self::REST_NAMESPACE . self::REST_ROUTE)),
		]);
	}

	public function shortcode(array $atts = []): string
	{
		$atts = shortcode_atts([
			'class' => '',
		], $atts, self::SHORTCODE);

		$class = trim((string) $atts['class']);
		$class_attr = $class !== '' ? ' ' . sanitize_html_class($class) : '';

		wp_enqueue_style('citatly-frontend');
		wp_enqueue_script('citatly-frontend');

		$html  = '<div class="citatly' . $class_attr . '" data-citatly="1">';
		// pre-line: Zeilenumbrüche aus PlainText bleiben sichtbar
		$html .= '<div class="citatly__text" style="white-space:pre-line" aria-live="polite"></div>';
		$html .= '<div class="citatly__meta">';
		$html .= '</div>';
		$html .= '</div>';

		return $html;
	}
}

Citatly_Plugin::init();
