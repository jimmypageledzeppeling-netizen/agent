<?php
/**
 * Admin Dashboard View
 *
 * @package Agent007
 */

?>
<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="card">
		<h2><?php esc_html_e( 'Plugin Status', 'agent007' ); ?></h2>
		<p>
			<?php
			echo 'Version: ' . esc_html( AGENT007_VERSION );
			?>
		</p>
		<p>
			<?php
			echo 'Author: ' . esc_html( 'Development Team' );
			?>
		</p>
	</div>

	<div class="card">
		<h2><?php esc_html_e( 'Quick Links', 'agent007' ); ?></h2>
		<ul>
			<li>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=agent007-logging' ) ); ?>">
					<?php esc_html_e( 'View Logs', 'agent007' ); ?>
				</a>
			</li>
		</ul>
	</div>

	<div class="card">
		<h2><?php esc_html_e( 'API Documentation', 'agent007' ); ?></h2>
		<p>
			<?php esc_html_e( 'Endpoint: ', 'agent007' ); ?>
			<code>POST /wp-json/agent007/v1/reg</code>
		</p>
		<p>
			<?php esc_html_e( 'Parameters:', 'agent007' ); ?>
			<ul>
				<li>
					<code>phone</code> (required) - <?php esc_html_e( 'Phone number without +, spaces, or parentheses', 'agent007' ); ?>
				</li>
			</ul>
		</p>
		<p>
			<?php esc_html_e( 'Example Response:', 'agent007' ); ?>
			<pre>{"success": true, "key": "AbCdEfGhIj", "message": "Phone registered successfully"}</pre>
		</p>
	</div>
</div>
