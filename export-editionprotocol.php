<?php
/**
 * Plugin Name:     Export Edition Protocol
 * Description:     An export function for edition protocol.
 * Author:          Jesper Nilsson
 * Author URI:      https://github.com/redundans
 * Text Domain:     editionprotocol
 * Version:         0.1.0
 * License:         GPL v3
 *
 * @package         Editionprotocol
 */

require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Include WP Background Process class.
if ( ! class_exists( 'WP_Background_Process' ) ) {
	include_once plugin_dir_path( __FILE__ ) . 'vendor/deliciousbrains/wp-background-processing/wp-background-processing.php';
}

// Include Process Protocol class.
if ( ! class_exists( 'Process_Protocol' ) ) {
	include_once plugin_dir_path( __FILE__ ) . 'classes/class-process-protocol.php';
}

/**
 * Edition Protocol Class.
 */
class EditionProtocol {
	/**
	 * Constructor function for process.
	 */
    public function __construct() {
		add_action( 'admin_init', array( $this, 'init' ) );
    }

    public function init() {
        $this->process_handler = new ProcessProtocol;

		// Test the post nonce.
		if ( ! isset( $_POST['editionprotocol_nonce_field'] ) 
		    || ! wp_verify_nonce( $_POST['editionprotocol_nonce_field'], 'editionprotocol_action' ) 
		) {
			return;
		}

		// Test year is given.
		if ( ! isset( $_POST['year'] ) ) {
			return;
		}

		// Get last year posts and push to background process.
		$args = array(
			'numberposts' => -1,
			'post_status' => 'publish',
			'post_type'   => 'post',
		    'date_query' => array(
		        array(
		            'year' => $_POST['year'],
		        ),
		    ),

		);
		$process_posts = get_posts( $args );
		foreach ( $process_posts as $process_post ) {
		    $this->process_handler->push_to_queue( $process_post->ID );
		}

		// Dispatch queue.
        $this->process_handler->save()->dispatch();
    }
}

/**
 * We hook this at plugins_loaded with priority 11, so that BackgroundAsync will already be loaded then.
 */
add_action(
	'plugins_loaded',
	function() {
    	new EditionProtocol();
	},
	11
);

/**
 * Adds "Create Edition Protocol" button on module list page
 */
add_action('manage_posts_extra_tablenav', 'add_extra_button');
function add_extra_button($where)
{
    global $post_type_object;
    if ($post_type_object->name === 'edition_protocol') {
        $years = get_posts_years_array();
        ?>
    	</form>
        <form method="post" action="">
        	<select name="year">
        		<?php
        		foreach ( $years as $year ) {
        			echo "<option value=\"{$year}\">{$year}</option>";
        		}
        		?>
        	</select>
        	<?php wp_nonce_field( 'editionprotocol_action', 'editionprotocol_nonce_field' ); ?>
        	<input type="submit" class="button" value="<?php _e( 'Create Edition Protocol', 'editionprotocol' ); ?>">
        </form>
        <?php
    }
}

/**
 * Get all the years that has published articles.
 */
function get_posts_years_array() {
    global $wpdb;
    $result = array();
    $years = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT YEAR(post_date) FROM {$wpdb->posts} WHERE post_status = 'publish' GROUP BY YEAR(post_date) DESC"
        ),
        ARRAY_N
    );
    if ( is_array( $years ) && count( $years ) > 0 ) {
        foreach ( $years as $year ) {
            $result[] = $year[0];
        }
    }
    return $result;
}

/**
 * Register Edition protocols Custom Post Type.
 */

add_action(
	'init',
	function () {
		// Register Edition protocols Custom Post Type labels.
		$labels = array(
			'name'                  => _x( 'Edition protocols', 'Post Type General Name', 'editionprotocol' ),
			'singular_name'         => _x( 'Edition protocol', 'Post Type Singular Name', 'editionprotocol' ),
			'menu_name'             => __( 'Edition protocols', 'editionprotocol' ),
			'name_admin_bar'        => __( 'Edition protocol', 'editionprotocol' ),
			'archives'              => __( 'Item Archives', 'editionprotocol' ),
			'attributes'            => __( 'Item Attributes', 'editionprotocol' ),
			'parent_item_colon'     => __( 'Parent Item:', 'editionprotocol' ),
			'all_items'             => __( 'All Items', 'editionprotocol' ),
			'add_new_item'          => __( 'Add New Item', 'editionprotocol' ),
			'add_new'               => __( 'Add New', 'editionprotocol' ),
			'new_item'              => __( 'New Item', 'editionprotocol' ),
			'edit_item'             => __( 'Edit Item', 'editionprotocol' ),
			'update_item'           => __( 'Update Item', 'editionprotocol' ),
			'view_item'             => __( 'View Item', 'editionprotocol' ),
			'view_items'            => __( 'View Items', 'editionprotocol' ),
			'search_items'          => __( 'Search Item', 'editionprotocol' ),
			'not_found'             => __( 'Not found', 'editionprotocol' ),
			'not_found_in_trash'    => __( 'Not found in Trash', 'editionprotocol' ),
			'featured_image'        => __( 'Featured Image', 'editionprotocol' ),
			'set_featured_image'    => __( 'Set featured image', 'editionprotocol' ),
			'remove_featured_image' => __( 'Remove featured image', 'editionprotocol' ),
			'use_featured_image'    => __( 'Use as featured image', 'editionprotocol' ),
			'insert_into_item'      => __( 'Insert into item', 'editionprotocol' ),
			'uploaded_to_this_item' => __( 'Uploaded to this item', 'editionprotocol' ),
			'items_list'            => __( 'Items list', 'editionprotocol' ),
			'items_list_navigation' => __( 'Items list navigation', 'editionprotocol' ),
			'filter_items_list'     => __( 'Filter items list', 'editionprotocol' ),
		);

		// Set Edition protocols Custom Post Type capabilities.
		$capabilities = array(
			'edit_post'             => 'export',
			'read_post'             => 'export',
			'delete_post'           => 'export',
			'edit_posts'            => 'export',
			'edit_others_posts'     => 'export',
			'publish_posts'         => 'export',
			'read_private_posts'    => 'export',
		);

		// Setup registering Edition protocols Custom Post Type arguments.
		$args = array(
			'label'                 => __( 'Edition protocol', 'editionprotocol' ),
			'description'           => __( 'Edition protocol for Export.', 'editionprotocol' ),
			'labels'                => $labels,
			'supports'              => array( 'title', 'editor' ),
			'hierarchical'          => false,
			'public'                => false,
			'show_ui'               => true,
			'show_in_menu'          => true,
			'menu_position'         => 25,
			'menu_icon'             => 'dashicons-clipboard',
			'show_in_admin_bar'     => false,
			'show_in_nav_menus'     => false,
			'can_export'            => false,
			'has_archive'           => false,
			'exclude_from_search'   => true,
			'publicly_queryable'    => false,
			'rewrite'               => false,
			'capabilities' => array(
	            'create_posts' => 'do_not_allow',
	            'delete_post'  => 'manage_options',
	            'edit_posts'   => 'manage_options',
	        ),
			'show_in_rest'          => false,
		);

		// Register Edition protocols Custom Post Type.
		register_post_type( 'edition_protocol', $args );
	}
);

/**
 * Add download and non linked name column.
 */
add_filter(
	'manage_edition_protocol_posts_columns',
	function( $columns ) {
		unset( $columns['date'] );
		unset( $columns['title'] );
		$columns['name'] = __( 'Title', 'editionprotocol' );
		$columns['download'] = __( 'Download', 'editionprotocol' );
  		return $columns;
	}
);

/**
 * Add name and download link to columns.
 */
add_action(
	'manage_edition_protocol_posts_custom_column',
	function( $column, $post_id ) {
		if ( 'download' === $column ) {
			// Only show the download link if the background process is finnished.
			if ( 'publish' === get_post_status( $post_id ) ) {
				echo '<a href="/wp-admin/?download-protocol=' . $post_id . '">' . get_the_title( $post_id ) . '.csv</a>';
			} else {
				echo 'Exporting...';
			}
		}
		if ( 'name' === $column ) {
			echo '<strong>' . get_the_title( $post_id ) . '</strong>';
		}
	},
	10,
	2
);

/**
 * Download CSV file made of post meta data from edition protocol.
 */
add_action(
	'init',
	function() {
		if( isset( $_GET['download-protocol'] ) ) {
			// Get edition protocol post.
			$protocol = get_post( $_GET['download-protocol'] );
			if ( $protocol instanceof WP_Post ) {
				// Get all articles in protocol_data.
				$articles = get_post_meta( $protocol->ID, 'protocol_data', true );

				// Initiate Spreadsheet.
				$spreadsheet = new Spreadsheet();
				$sheet       = $spreadsheet->getActiveSheet();

				// Loop through alla articles and generate cells in sheet.
				foreach ( $articles as $index => $article ) {
					$index++; // Add 1 to index to avoid zero index in Excel.
					foreach ( $article as $key => $value ) {
						$article[$key] = str_replace( '%index%', $index, $value );
					}
					$sheet->fromArray( $article, NULL, "A{$index}" );
				}

				// Create a xlsx file in tmp and output it to the browser.
				$writer   = new Xlsx( $spreadsheet );
				$filepath = '/tmp/tmp.xlsx';
				$writer->save( $filepath );
				if ( file_exists( $filepath ) ) {
		            header('Content-Description: File Transfer');
		            header('Content-Type: application/octet-stream');
		            header('Content-Disposition: attachment; filename="'.basename($filepath).'"');
		            header('Expires: 0');
		            header('Cache-Control: must-revalidate');
		            header('Pragma: public');
		            header('Content-Length: ' . filesize($filepath));
		            // Flush system output buffer.
		            flush();
		            readfile($filepath);
		        }

			}
			// Die WordPress without output.
	        die();
		}
	}
);
