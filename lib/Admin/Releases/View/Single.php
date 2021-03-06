<?php
/**
 * View for a single release.
 *
 * @author    Iron Bound Designs
 * @since     1.0
 * @license   AGPL
 * @copyright Iron Bound Designs, 2015.
 */

namespace ITELIC\Admin\Releases\View;

use IronBound\WP_Notifications\Template\Editor;
use IronBound\WP_Notifications\Template\Factory;
use ITELIC\Admin\Chart;
use ITELIC\Admin\Tab\Dispatch;
use ITELIC\Admin\Tab\View;
use ITELIC\Plugin;
use ITELIC\Release;

/**
 * Class Single
 *
 * @package ITELIC\Admin\Releases\View
 */
class Single extends View {

	/**
	 * @var Release
	 */
	protected $release;

	/**
	 * @var Chart\Base
	 */
	protected $progress;

	/**
	 * @var Chart\Base $version
	 */
	protected $version;

	/**
	 * Constructor.
	 *
	 * @param Release    $release
	 * @param Chart\Base $progress
	 * @param Chart\Base $version
	 */
	public function __construct( Release $release = null, Chart\Base $progress = null, Chart\Base $version = null ) {
		$this->release  = $release;
		$this->progress = $progress;
		$this->version  = $version;
	}

	/**
	 * Render the release view.
	 */
	public function render() {

		if ( ! $this->release ) {
			return;
		}

		$df  = str_replace( 'F', 'M', get_option( 'date_format' ) );
		$tf  = get_option( 'time_format' );
		$dtf = "$df $tf";

		if ( $this->release->get_status() == Release::STATUS_DRAFT ) {
			$click_edit = ' title="' . __( "Click to Edit", Plugin::SLUG ) . '"';
		} else {
			$click_edit = '';
		}
		?>

		<div id="release-details">
			<div class="spacing-wrapper bottom-border header-block">

				<div class="status status-<?php echo esc_attr( $this->release->get_status() ); ?>">
					<span data-value="<?php echo esc_attr( $this->release->get_status() ); ?>"<?php echo $click_edit; ?>>
						<?php echo $this->release->get_status( true ); ?>
					</span>
				</div>

				<div class="name-block">
					<h2 class="product-name"><?php echo $this->release->get_product()->post_title; ?></h2>

					<h2 class="version-name"><?php echo $this->release->get_version(); ?></h2>
				</div>
			</div>

			<div class="spacing-wrapper bottom-border third-row misc-block">
				<div class="third type">
					<h4><?php _e( "Type", Plugin::SLUG ); ?></h4>

					<h3 data-value="<?php echo $this->release->get_type(); ?>"<?php echo $click_edit; ?>>
						<?php echo $this->release->get_type( true ); ?>
					</h3>
				</div>
				<div class="third release-date">
					<h4><?php _e( "Released", Plugin::SLUG ); ?></h4>

					<h3>
						<?php if ( null === $this->release->get_start_date() ): ?>
							<?php echo '&mdash;' ?>
						<?php else: ?>
							<?php echo \ITELIC\convert_gmt_to_local( $this->release->get_start_date() )->format( $df ); ?>
						<?php endif; ?>
					</h3>
				</div>
				<div class="third version">
					<h4><?php _e( "Version", Plugin::SLUG ); ?></h4>

					<h3 data-value="<?php echo $this->release->get_version(); ?>"<?php echo $click_edit; ?>>
						<?php echo $this->release->get_version(); ?>
					</h3>
				</div>
			</div>

			<?php if ( get_post_meta( $this->release->get_product()->ID, '_itelic_first_release', true ) == $this->release->get_pk() ): ?>

				<div class="spacing-wrapper">
					<p class="initial-release-notice">
						<?php printf(
							__( "Congratulations on releasing %s; there isn't any data to display for your first release, though.", Plugin::SLUG ),
							$this->release->get_product()->post_title );?>
					</p>
				</div>

				</div>

				<?php return; ?>
			<?php endif; ?>

			<?php if ( $this->release->get_status() == Release::STATUS_DRAFT ): ?>

				<?php $this->render_replace_file_section(); ?>

			<?php endif; ?>

			<?php
			$this->render_security_message();
			$this->render_upgrades_bar();
			$this->render_whats_changed();

			if ( $this->release->get_status() != Release::STATUS_ARCHIVED ):
				$this->render_notification_editor();
			endif;

			$this->render_progress_line_chart();
			$this->render_versions_pie_chart();
			$this->render_notify_button_section(); ?>
		</div>

		<?php

		/**
		 * Fires after the main single release screen.
		 *
		 * @since 1.0
		 *
		 * @param Release $release
		 */
		do_action( 'itelic_single_release_screen_after', $this->release );
	}

	/**
	 * Render the replace file section.
	 *
	 * Only visible on draft views.
	 *
	 * @since 1.0
	 */
	protected function render_replace_file_section() {

		?>

		<div class="spacing-wrapper bottom-border replace-file-block">

			<span class="replace-file-container">
				<label>
					<?php echo basename( get_attached_file( $this->release->get_download()->ID ) ); ?>
				</label>
				<a href="javascript:" class="button" id="replace-file"><?php _e( "Replace", Plugin::SLUG ); ?></a>
			</span>
		</div>        <?php
	}

	/**
	 * Render the what's changed section.
	 *
	 * @since 1.0
	 */
	protected function render_whats_changed() {

		?>

		<div class="spacing-wrapper bottom-border changelog-block">

			<h4><?php _e( "What's Changed", Plugin::SLUG ); ?>
				<span class="tip" title="<?php _e( "Don't include the version number or date, they'll be added automatically.", Plugin::SLUG ) ?>">i</span>
			</h4>

			<div class="whats-changed" title="<?php _e( "Click to Edit", Plugin::SLUG ); ?>">
				<?php echo $this->release->get_changelog(); ?>
			</div>

			<div class="whats-changed-editor">

				<?php wp_editor( $this->release->get_changelog(), 'whats-changed-input', array(
					'teeny'         => true,
					'editor_height' => '150px',
					'media_buttons' => false
				) ); ?>

				<p>
					<a href="javascript:" class="button" id="cancel-changelog-editor">
						<?php _e( "Cancel", Plugin::SLUG ); ?>
					</a>

					<a href="javascript:" class="button button-primary" id="save-changelog-editor">
						<?php _e( "Save", Plugin::SLUG ); ?>
					</a>
				</p>
			</div>

		</div>

		<?php

	}

	/**
	 * Render the security message section.
	 *
	 * @since 1.0
	 */
	protected function render_security_message() {

		$hidden = $this->release->get_type() != Release::TYPE_SECURITY ? ' hidden' : '';

		$m = $this->release->get_meta( 'security-message', true );
		?>

		<div class="spacing-wrapper bottom-border security-message-block<?php echo $hidden; ?>">

			<h4><?php _e( "Security Message", Plugin::SLUG ); ?></h4>

			<p class="security-message" title="<?php _e( "Click to Edit", Plugin::SLUG ); ?>"><?php echo $m; ?></p>

		</div>

		<?php
	}

	/**
	 * Render the upgrades completion bar.
	 *
	 * @since 1.0
	 */
	protected function render_upgrades_bar() {

		$updated           = $this->release->get_total_updated();
		$total_activations = $this->release->get_total_active_activations();
		$total_activations = max( 1, $total_activations );

		$percent = min( number_format( $updated / $total_activations * 100, 0 ), 100 );

		if ( $this->release->get_status() == Release::STATUS_DRAFT ) {
			$hidden = ' hidden';
		} else {
			$hidden = '';
		}

		$tip = __( "Update notifications can't be sent for archived releases.", Plugin::SLUG );

		if ( $this->release->get_status() == Release::STATUS_ARCHIVED ) {
			$disabled = ' button-disabled';
			$title = " title=\"$tip\"";
		} else {
			$disabled = '';
			$title = '';
		}
		?>

		<div class="spacing-wrapper bottom-border upgrade-progress-block<?php echo $hidden; ?>">

			<h4>
				<?php _e( "Updates", Plugin::SLUG ); ?>
				<a href="javascript:" id="more-upgrades-link"><?php _e( "More", Plugin::SLUG ); ?></a>
			</h4>

			<div class="progress-container" data-percent="<?php echo $percent; ?>">

				<progress value="<?php echo esc_attr( $updated ); ?>" max="<?php echo esc_attr( $total_activations ); ?>">
					<div class="progress-bar">
						<span style="width: <?php echo $percent; ?>%;">Progress: <?php echo $percent; ?>%</span>
					</div>
				</progress>

				<button class="button <?php echo $disabled; ?>" id="notify-button"<?php echo $title; ?> data-tip="<?php echo $tip; ?>">
					<?php _e( "Notify", Plugin::SLUG ); ?>
				</button>
			</div>
		</div>

		<?php
	}

	/**
	 * Render the full-width notify button section.
	 *
	 * @since 1.0
	 */
	protected function render_notify_button_section() {

		if ( $this->release->get_status() == Release::STATUS_ARCHIVED ) {
			$disabled = ' button-disabled';
			$title = __( "Update notifications can't be sent for archived releases.", Plugin::SLUG );
			$title = " title=\"$title\"";
		} else {
			$disabled = '';
			$title = '';
		}

		?>

		<div class="spacing-wrapper bottom-border full-notify-button hidden">
			<button class="button <?php echo $disabled; ?>" id="notify-button-full"<?php echo $title; ?>>
				<?php _e( "Notify Outdated Customers", Plugin::SLUG ); ?>
			</button>
		</div>

		<?php
	}

	/**
	 * Render the progress line chart.
	 *
	 * @since 1.0
	 */
	protected function render_progress_line_chart() {

		?>

		<div class="spacing-wrapper bottom-border progress-line-chart hidden">

			<?php if ( $this->release->get_total_updated() == 0 ): ?>

				<p class="description" style="text-align: center">
					<?php _e( "No one has updated to the latest version yet. Sit tight!", Plugin::SLUG ); ?>
				</p>

			<?php else: ?>

				<h4><?php printf( __( "Updates over the first %d days", Plugin::SLUG ), $this->progress->get_total_items() ); ?></h4>

				<div class="chart">
					<?php $this->progress->graph(); ?>
				</div>

			<?php endif; ?>

		</div>

		<?php
	}

	/**
	 * Render the versions pie chart.
	 *
	 * @since 1.0
	 */
	protected function render_versions_pie_chart() {

		if ( ! $this->version || $this->release->get_total_updated() == 0 ) {
			return;
		}

		$total = $this->version->get_total_items();
		?>

		<div class="spacing-wrapper bottom-border versions-pie-chart hidden">

			<h4><?php printf( __( "Top %d versions updated from", Plugin::SLUG ), $total ); ?></h4>

			<div class="chart">
				<?php $this->version->graph(); ?>
			</div>

			<div id="pie-chart-legend" class="chart-js-legend"></div>
		</div>

		<?php
	}

	/**
	 * Render the notification editor.
	 *
	 * @since 1.0
	 */
	protected function render_notification_editor() {

		$editor = new Editor( Factory::make( 'itelic-outdated-customers' ), array(
			'mustSelectItem'    => __( "You must select an item", Plugin::SLUG ),
			'selectTemplateTag' => __( "Select Template Tag", Plugin::SLUG ),
			'templateTag'       => __( "Template Tag", Plugin::SLUG ),
			'selectATag'        => __( "Select a tag", Plugin::SLUG ),
			'insertTag'         => __( "Insert", Plugin::SLUG ),
			'cancel'            => __( "Cancel", Plugin::SLUG ),
			'insertTemplateTag' => __( "Insert Template Tag", Plugin::SLUG )
		) );
		$editor->thickbox();

		?>

		<div class="spacing-wrapper hidden notifications-editor">

			<h4><?php _e( "Send Update Reminders", Plugin::SLUG ); ?></h4>

			<p class="description">
				<?php printf(
					__( 'Email your customers who have not yet updated to version %1$s of %2$s.', Plugin::SLUG ),
					$this->release->get_version(), $this->release->get_product()->post_title
				); ?>
			</p>

			<div class="notification-editor-fields-container">

				<input type="text" id="notification-subject" placeholder="<?php esc_attr_e( "Enter your subject", Plugin::SLUG ); ?>">

				<?php $editor->display_template_tag_button(); ?>

				<?php wp_editor( '', 'notification-body', array(
					'teeny'         => true,
					'media_buttons' => false,
					'editor_height' => '250px'
				) ); ?>

				<p class="clearfix notification-buttons">
					<a href="javascript:" class="button button-secondary" id="cancel-notification">
						<?php _e( "Cancel", Plugin::SLUG ); ?>
					</a>
					<a href="javascript:" class="button button-primary" id="send-notification">
						<?php _e( "Send", Plugin::SLUG ); ?>
					</a>
				</p>
			</div>
		</div>

		<?php
	}

	/**
	 * Get the title of this view.
	 *
	 * @return string
	 */
	protected function get_title() {
		return __( "Manage Release", Plugin::SLUG );
	}

	/**
	 * Override title display to show an add new button.
	 *
	 * @since 1.0
	 */
	public function title() {
		echo '<h1>' . $this->get_title() . ' ';
		echo '<a href="' . add_query_arg( 'view', 'add-new', Dispatch::get_tab_link( 'releases' ) ) . '" class="page-title-action">';
		echo __( "Add New", Plugin::SLUG );
		echo '</a>';
		echo '</h1>';
	}
}