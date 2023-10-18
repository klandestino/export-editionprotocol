<?php
/**
 * Main class, sets up admin interface and handles cron jobs.
 */

namespace ExportEditionProtocol;

use DateTime;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class Exporter {

	public function register_hooks(): void {
		add_action( 'admin_menu', [ $this, 'add_menu' ] );
		add_action( 'admin_head-tools_page_export_editions', [ $this, 'maybe_schedule_cron' ] );
		add_action( 'admin_head-tools_page_export_editions', [ $this, 'maybe_cancel_cron' ] );
		add_action( 'eep_create_export', [ $this, 'export' ] );
		add_filter( 'removable_query_args', [ $this, 'removable_query_args' ] );
	}

	public function add_menu(): void {
		add_management_page( 'Export Editions', 'Export Editions', 'manage_options', 'export_editions', [ $this, 'add_page' ] );
	}

	public function add_page(): void {
		?>
		<div class="wrap">
			<h1>Export Editions</h1>
			<table class="wp-list-table widefat fixed striped table-view-list">
				<thead>
					<tr>
						<th>Year</th>
						<th>Download</th>
						<th>Generate</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $this->get_years() as $year ) : ?>
						<?php $exported_file = get_option( "eep_exported_file_{$year}", false ); ?>
						<?php $exported_processing = get_option( "eep_export_processing_{$year}", false ); ?>
						<tr>
							<td><strong><?= esc_html( $year ); ?></strong></td>
							<?php if ( $exported_file ) : ?>
								<td><a href="<?= esc_url( $exported_file['url'] ); ?>">Download export</a></td>
							<?php elseif ( $exported_processing ) : ?>
								<td>Export in process, please check back in a few minutes... <a href="<?= esc_url( admin_url( 'tools.php?page=export_editions&eep_action=cancel&eep_nonce=' . wp_create_nonce( 'eep_cancel' ) . '&eep_year=' . $year ) ); ?>">Cancel</a></td>
							<?php else : ?>
								<td>No export generated</td>
							<?php endif; ?>
							<?php if ( ! $exported_processing ) : ?>
								<td><a href="<?= esc_url( admin_url( 'tools.php?page=export_editions&eep_action=export&eep_nonce=' . wp_create_nonce( 'eep_export' ) . '&eep_year=' . $year ) ); ?>"><?= $exported_file ? 'Regenerate export' : 'Generate export'; ?></a></td>
							<?php else : ?>
								<td></td>
							<?php endif; ?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function maybe_schedule_cron(): void {
		if ( isset( $_GET['eep_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['eep_nonce'] ) ), 'eep_export' ) ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			$year   = (int) $_GET['eep_year'] ?? null;
			$action = isset( $_GET['eep_action'] ) ? sanitize_text_field( wp_unslash( $_GET['eep_action'] ) ) : null;
			if ( null === $year || 'export' !== $action ) {
				return;
			}
			// Set the status to processing and delete old file if it exists.
			update_option( "eep_export_processing_{$year}", true );
			$old_file = get_option( "eep_exported_file_{$year}", false );
			if ( $old_file ) {
				wp_delete_file( $old_file['path'] );
				delete_option( "eep_exported_file_{$year}" );
			}
			wp_schedule_single_event( time(), 'eep_create_export', [ $year ] );
		}
	}

	public function maybe_cancel_cron(): void {
		if ( isset( $_GET['eep_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['eep_nonce'] ) ), 'eep_cancel' ) ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			$year   = (int) $_GET['eep_year'] ?? null;
			$action = isset( $_GET['eep_action'] ) ? sanitize_text_field( wp_unslash( $_GET['eep_action'] ) ) : null;
			if ( null === $year || 'cancel' !== $action ) {
				return;
			}
			$old_file = get_option( "eep_exported_file_{$year}", false );
			if ( $old_file ) {
				wp_delete_file( $old_file['path'] );
				delete_option( "eep_exported_file_{$year}" );
			}
			delete_option( "eep_export_processing_{$year}" );
			wp_clear_scheduled_hook( 'eep_create_export', [ $year ] );
		}
	}

	public function export( $year ): void {
		$formatter = new Formatter();
		$posts     = get_posts(
			[
				'numberposts' => -1,
				'post_status' => 'publish',
				'post_type'   => 'post',
				'date_query'  => [
					[
						'after'     => $this->get_start_date( 1, $year ),
						'before'    => $this->get_end_date( 52, $year ),
						'inclusive' => true,
					],
				],
			]
		);

		$data   = [];
		$data[] = $formatter->first_row();
		foreach ( $posts as $post ) {
			$data[] = $formatter->format( $post );
		}

		$spreadsheet = new Spreadsheet();
		$sheet       = $spreadsheet->getActiveSheet();

		// Loop through all articles and generate cells in sheet.
		foreach ( $data as $index => $article ) {
			++$index; // Add 1 to index to avoid zero index in Excel.
			foreach ( $article as $key => $value ) {
				$article[ $key ] = str_replace( '%index%', $index, $value );
			}
			$sheet->fromArray( $article, null, "A{$index}" );
		}

		// Create a xlsx file and save it to the uploads directory.
		$upload_dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'export-edition-protocol/';
		if ( ! is_dir( $upload_dir ) ) {
			wp_mkdir_p( $upload_dir );
		}
		$filename = md5( $year . time() ) . '.xlsx';
		$filepath = $upload_dir . $filename;
		$fileurl  = trailingslashit( wp_upload_dir()['baseurl'] ) . 'export-edition-protocol/' . $filename;
		$writer   = new Xlsx( $spreadsheet );
		$writer->save( $filepath );

		$file_info = [
			'path' => $filepath,
			'url'  => $fileurl,
		];

		// Save filepath and url to an option and delete the processing option.
		update_option( "eep_exported_file_{$year}", $file_info );
		delete_option( "eep_export_processing_{$year}" );
	}

	public function removable_query_args( array $args ): array {
		$args[] = 'eep_nonce';
		$args[] = 'eep_action';
		$args[] = 'eep_year';
		return $args;
	}

	private function get_years(): array {
		global $wpdb;
		$result = [];
		$years  = $wpdb->get_results(
			"SELECT YEAR(post_date) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'post' GROUP BY YEAR(post_date) ORDER BY YEAR(post_date) DESC",
			ARRAY_N
		);
		if ( is_array( $years ) && count( $years ) > 0 ) {
			foreach ( $years as $year ) {
				$result[] = $year[0];
			}
		}
		return $result;
	}

	private function get_start_date( int $week, int $year ): string {
		$date_time = new DateTime();
		$date_time->setISODate( $year, $week );
		return $date_time->format( 'Y-m-d' );
	}

	private function get_end_date( int $week, int $year ): string {
		$date_time = new DateTime();
		$date_time->setISODate( $year, $week );
		$date_time->modify( '+6 days' );
		return $date_time->format( 'Y-m-d' );
	}
}
