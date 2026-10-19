<?php
/**
 * Admin Target Edit View
 *
 * @package Agent007
 *
 * @var object $target Target row (set by TargetsPage::render_edit).
 */

?>
<div class="card">
	<h2><?php echo esc_html( 'Цель #' . (int) $target->ID ); ?></h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="agent007_update_target">
		<input type="hidden" name="id" value="<?php echo esc_attr( (int) $target->ID ); ?>">
		<?php wp_nonce_field( 'agent007_update_target' ); ?>
		<table class="form-table">
			<tr>
				<th scope="row"><label for="agent007_phone"><?php esc_html_e( 'Телефон', 'agent007' ); ?></label></th>
				<td>
					<input type="text" id="agent007_phone" name="phone" value="<?php echo esc_attr( $target->phone ); ?>" pattern="\d{10,}" required class="regular-text">
					<p class="description"><?php esc_html_e( 'Только цифры, минимум 10.', 'agent007' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="agent007_key"><?php esc_html_e( 'Ключ', 'agent007' ); ?></label></th>
				<td>
					<input type="text" id="agent007_key" name="key" value="<?php echo esc_attr( $target->key ); ?>" pattern="[A-Za-z]{10}" maxlength="10" required class="regular-text">
					<p class="description"><?php esc_html_e( 'Ровно 10 латинских букв, регистр важен.', 'agent007' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="agent007_status"><?php esc_html_e( 'Статус', 'agent007' ); ?></label></th>
				<td>
					<select id="agent007_status" name="status">
						<?php foreach ( \Agent007\Database::STATUSES as $code => $label ) : ?>
							<option value="<?php echo esc_attr( $code ); ?>" <?php selected( (int) $target->status, $code ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="agent007_access_path"><?php esc_html_e( 'Путь доступа', 'agent007' ); ?></label></th>
				<td>
					<input type="text" id="agent007_access_path" name="access_path" value="<?php echo esc_attr( (string) $target->access_path ); ?>" maxlength="255" class="regular-text">
				</td>
			</tr>
		</table>
		<?php submit_button( 'Сохранить', 'primary', 'submit', false ); ?>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . \Agent007\TargetsPage::SLUG ) ); ?>"><?php esc_html_e( 'Отмена', 'agent007' ); ?></a>
	</form>
</div>
