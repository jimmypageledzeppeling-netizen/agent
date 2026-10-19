<?php
/**
 * Admin Test View
 *
 * @package Agent007
 */

?>
<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="card">
		<h2><?php esc_html_e( 'Регистрация номера', 'agent007' ); ?></h2>
		<p><?php esc_html_e( 'Номер отправляется в тот же обработчик, что и REST API /agent007/v1/reg. Только цифры, минимум 10.', 'agent007' ); ?></p>
		<form method="post">
			<?php wp_nonce_field( 'agent007_test_nonce', 'agent007_nonce' ); ?>
			<input type="text" name="phone" value="<?php echo esc_attr( $phone ); ?>" placeholder="79991234567" pattern="\d{10,}" required>
			<?php submit_button( 'Отправить', 'primary', 'submit', false ); ?>
		</form>
	</div>

	<?php if ( null !== $result ) : ?>
		<div class="card">
			<h2><?php echo esc_html( 'Ответ (HTTP ' . $result['status'] . ')' ); ?></h2>
			<?php if ( ! empty( $result['body']['key'] ) ) : ?>
				<p><strong><?php esc_html_e( 'Ключ:', 'agent007' ); ?></strong> <code><?php echo esc_html( $result['body']['key'] ); ?></code></p>
			<?php endif; ?>
			<pre><?php echo esc_html( wp_json_encode( $result['body'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></pre>
		</div>
	<?php endif; ?>
</div>
