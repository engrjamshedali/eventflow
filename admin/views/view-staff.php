<?php
/**
 * EventFlow Admin View: Staff & Roles Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$staff_roles = array_keys( EventFlow_Roles::get_staff_roles() );
$staff_users = get_users( array( 'role__in' => array_merge( array( 'administrator' ), $staff_roles ) ) );
?>

<div class="wrap ef-admin-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Staff & Permissions Management', 'eventflow' ); ?></h1>
	<hr class="wp-header-end">

	<div class="ef-grid ef-grid-2" style="margin-top: 20px;">
		<!-- Provision Form -->
		<div class="ef-card">
			<div class="ef-card-header">
				<h3><?php esc_html_e( 'Provision / Assign Staff Role', 'eventflow' ); ?></h3>
			</div>

			<form id="ef-admin-staff-form" class="ef-form">
				<div class="ef-form-group">
					<label><?php esc_html_e( 'Staff Name', 'eventflow' ); ?> <span class="ef-req">*</span></label>
					<input type="text" name="staff_name" class="ef-input" placeholder="e.g. John Doe" required>
				</div>

				<div class="ef-form-group">
					<label><?php esc_html_e( 'Email Address', 'eventflow' ); ?> <span class="ef-req">*</span></label>
					<input type="email" name="staff_email" class="ef-input" placeholder="staff@eventflow.dev" required>
				</div>

				<div class="ef-form-group">
					<label><?php esc_html_e( 'Assigned Role & Permissions', 'eventflow' ); ?> <span class="ef-req">*</span></label>
					<select name="staff_role" class="ef-input" required>
						<?php foreach ( EventFlow_Roles::get_staff_roles() as $key => $title ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $title ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="ef-form-group">
					<label><?php esc_html_e( 'Username (Optional - auto-generated if blank)', 'eventflow' ); ?></label>
					<input type="text" name="staff_username" class="ef-input" placeholder="e.g. jdoe">
				</div>

				<div class="ef-form-group">
					<label><?php esc_html_e( 'Password (Optional, leaves unchanged for existing users)', 'eventflow' ); ?></label>
					<input type="password" name="staff_password" class="ef-input" placeholder="••••••••">
				</div>

				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Save Staff Account', 'eventflow' ); ?>
				</button>
			</form>
		</div>

		<!-- Staff List -->
		<div class="ef-card">
			<div class="ef-card-header">
				<h3><?php esc_html_e( 'Active Management Staff', 'eventflow' ); ?></h3>
			</div>

			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'eventflow' ); ?></th>
						<th><?php esc_html_e( 'Username', 'eventflow' ); ?></th>
						<th><?php esc_html_e( 'Email', 'eventflow' ); ?></th>
						<th><?php esc_html_e( 'Role', 'eventflow' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $staff_users as $s ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $s->display_name ); ?></strong></td>
							<td><code>@<?php echo esc_html( $s->user_login ); ?></code></td>
							<td><?php echo esc_html( $s->user_email ); ?></td>
							<td><span class="ef-badge ef-badge-primary"><?php echo esc_html( EventFlow_Roles::get_role_label( $s->ID ) ); ?></span></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
</div>

