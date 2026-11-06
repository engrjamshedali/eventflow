<?php
/**
 * EventFlow Standalone Master App Shell
 *
 * Provides a clean, isolated full-page canvas for the EventFlow application.
 * Completely eliminates the active WordPress theme's header, logo, navigation menus,
 * sidebars, widgets, and footer.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$view       = isset( $GLOBALS['ef_standalone_view'] ) ? $GLOBALS['ef_standalone_view'] : 'auth-home';
$page_title = isset( $GLOBALS['ef_standalone_title'] ) ? $GLOBALS['ef_standalone_title'] : __( 'Event Portal', 'eventflow' );
$event      = EventFlow_Event_Manager::get_active_event();
$event_name = $event ? esc_html( $event->title ) : get_bloginfo( 'name' );

// Buffer the specific view content
ob_start();

switch ( $view ) {
	case 'auth-home':
		include EVENTFLOW_PLUGIN_DIR . 'templates/frontend/auth-home.php';
		break;

	case 'auth':
		include EVENTFLOW_PLUGIN_DIR . 'templates/frontend/auth-page.php';
		break;

	case 'dashboard':
		if ( ! is_user_logged_in() ) {
			include EVENTFLOW_PLUGIN_DIR . 'templates/frontend/auth-home.php';
		} else {
			include EVENTFLOW_PLUGIN_DIR . 'templates/frontend/participant-dashboard.php';
		}
		break;

	case 'staff-dashboard':
		echo EventFlow_Shortcodes::render_staff_dashboard();
		break;

	case 'landing':
		echo EventFlow_Shortcodes::render_landing();
		break;

	case 'track':
		echo EventFlow_Shortcodes::render_track();
		break;

	default:
		if ( have_posts() ) {
			while ( have_posts() ) {
				the_post();
				the_content();
			}
		}
		break;
}

$app_content = ob_get_clean();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> class="eventflow-standalone-html">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<title><?php echo esc_html( $event_name ); ?> &mdash; <?php echo esc_html( $page_title ); ?></title>

	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">

	<?php if ( class_exists( 'EventFlow_Favicon' ) ) { EventFlow_Favicon::render_meta_tags(); } ?>
	<?php wp_head(); ?>

	<style id="eventflow-app-shell-reset">
		html.eventflow-standalone-html,
		body.eventflow-standalone-body {
			margin: 0 !important;
			padding: 0 !important;
			width: 100% !important;
			min-height: 100vh !important;
			box-sizing: border-box !important;
			font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif !important;
			-webkit-font-smoothing: antialiased;
			-moz-osx-font-smoothing: grayscale;
			overflow-x: hidden !important;
		}

		*, *::before, *::after {
			box-sizing: inherit;
		}

		#eventflow-app-shell {
			width: 100%;
			min-height: 100vh;
			display: flex;
			flex-direction: column;
			position: relative;
			background-color: #F0F7F5 !important;
		}

		/* View-specific background treatments */
		body.eventflow-view-auth-home,
		body.eventflow-view-auth,
		body.eventflow-view-landing,
		body.eventflow-view-dashboard,
		body.eventflow-view-track {
			background: #F0F7F5 !important;
			color: #1E293B !important;
		}
		body.eventflow-view-auth-home #eventflow-app-shell,
		body.eventflow-view-auth #eventflow-app-shell,
		body.eventflow-view-landing #eventflow-app-shell,
		body.eventflow-view-dashboard #eventflow-app-shell,
		body.eventflow-view-track #eventflow-app-shell {
			background: #F0F7F5 !important;
		}

		body.eventflow-view-staff-dashboard {
			background: #0B0F19 !important;
			color: #F8FAFC !important;
		}
		body.eventflow-view-staff-dashboard #eventflow-app-shell {
			background: #0B0F19 !important;
		}

		/* Reset any theme injected container widths */
		.eventflow-app {
			width: 100% !important;
			max-width: 100% !important;
		}

		/* Admin bar integration if shown for administrators */
		body.admin-bar.eventflow-standalone-body #eventflow-app-shell {
			min-height: calc(100vh - 32px);
		}
		@media screen and (max-width: 782px) {
			body.admin-bar.eventflow-standalone-body #eventflow-app-shell {
				min-height: calc(100vh - 46px);
			}
		}
	</style>
</head>
<body <?php body_class( 'eventflow-standalone-body eventflow-view-' . esc_attr( $view ) ); ?>>

	<div id="eventflow-app-shell">
		<?php
		// Render buffered view content
		echo $app_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
	</div>

	<?php wp_footer(); ?>
</body>
</html>
