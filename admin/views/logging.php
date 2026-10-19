<?php
/**
 * Admin Logging View
 *
 * @package Agent007
 */

global $agent007_logger;

$log_contents = $this->logger->get_log_contents( 100 );
$is_enabled   = $this->logger->is_enabled();

?>
<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="card">
		<h2><?php esc_html_e( 'Logging Settings', 'agent007' ); ?></h2>
		<form method="post">
			<?php wp_nonce_field( 'agent007_logging_nonce', 'agent007_nonce' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="logging_enabled">
							<?php esc_html_e( 'Enable Logging', 'agent007' ); ?>
						</label>
					</th>
					<td>
						<input type="hidden" name="action" value="toggle_logging">
						<input type="checkbox" id="logging_enabled" name="logging_enabled" value="1" <?php checked( $is_enabled, true ); ?> />
						<p class="description">
							<?php esc_html_e( 'Enable or disable plugin logging', 'agent007' ); ?>
						</p>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Update Settings' ); ?>
		</form>
	</div>

	<div class="card">
		<h2><?php esc_html_e( 'Log Actions', 'agent007' ); ?></h2>
		<form method="post" style="display: inline;">
			<?php wp_nonce_field( 'agent007_logging_nonce', 'agent007_nonce' ); ?>
			<input type="hidden" name="action" value="clear_log">
			<?php submit_button( 'Clear Log', 'delete' ); ?>
		</form>
		<form method="post" style="display: inline;">
			<?php wp_nonce_field( 'agent007_logging_nonce', 'agent007_nonce' ); ?>
			<input type="hidden" name="action" value="download_log">
			<?php submit_button( 'Download Log' ); ?>
		</form>
	</div>

	<div class="card">
		<h2><?php esc_html_e( 'Recent Logs', 'agent007' ); ?></h2>
		<p>
			<?php esc_html_e( 'Log File: ', 'agent007' ); ?>
			<code><?php echo esc_html( WP_CONTENT_DIR . '/../PROCESS.LOG' ); ?></code>
		</p>
		<textarea readonly style="width: 100%; height: 400px; font-family: monospace; font-size: 12px;"><?php echo esc_textarea( $log_contents ); ?></textarea>
	</div>
</div>
