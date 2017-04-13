<?php
/**
 * Contains update related functions.
 *
 * @since 1.0.0
 * @ver   2.0.0
 */

function ayecode_show_update_plugin_requirement() {
	if ( !defined('WP_EASY_UPDATES_ACTIVE') ) {
	?>
	<div class="notice notice-warning is-dismissible">
		<p>
			<strong>
				<?php
					echo sprintf( __( 'The plugin %sWP Easy Updates%s is required to check for and update some installed plugins, please install it now.', 'geodirectory' ), '<a href="https://wpeasyupdates.com/" target="_blank" title="WP Easy Updates">', '</a>' );
				?>
			</strong>
		</p>
	</div>
	<?php
	}
}
add_action( 'admin_notices', 'ayecode_show_update_plugin_requirement' );
