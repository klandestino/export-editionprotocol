<?php
/**
 * This file includes class and function for getting relevand data from a WP_Post and saving it to a Protocol.
 */

/**
 * Class for getting relevant data from a WP_Post.
 */
add_action('plugins_loaded', function() {
	class ProcessProtocol extends WP_Background_Process {
		/**
		 * The action string.
		 *
		 * @var string
		 */
		protected $action = 'edition_protocol';

		/**
		 * The task action for every task in queue.
		 *
		 * @param mixed $item Queue item to iterate over
		 *
		 * @return mixed
		 */
		protected function task( $post_id = null ): bool {
			$post = get_post( $post_id );
	        if ( $post instanceof WP_Post ) {
	        	// Get the edition protocol post.
	        	$protocol = $this->get_protocol();

	        	// Set up the protocol data.
	        	$protocol_data = array(
	        		'IssueNumber'    => $this->get_issue_number( $post ),
	        		'IssueYear'      => $this->get_issue_year( $post ),
	        		'Uniqueness'     => 1,
	        		'Content'        => $this->get_post_content( $post ),
	        		'URL'            => $this->get_post_url( $post ), 
	        		'ObjectTitle'    => $this->get_post_title( $post ),
	        		'PubDate'        => $this->get_post_pubdate( $post ),
	        		'MediaCopyright' => $this->get_post_copyright( $post ),
	        		'Length'         => $this->get_post_length( $post ),
	        		'Images'         => $this->get_post_images( $post ),
	        		'ImagesLenght'   => $this->get_post_image_lenght( $post ),
	        		'TotalLenght'    => $this->get_post_total_lenght( $post ),
	        		'License'        => '',
	        		'IsEditorial'    => 1,
	        	);

	        	// Add new post to protocol meta.
				$temp_array   = get_post_meta( $protocol->ID, 'protocol_data', true );
				$temp_array[] = $protocol_data;

				// Update the meta with new content.
	        	update_post_meta(
	        		$protocol->ID,
	        		'protocol_data',
	        		$temp_array
	        	);
	        }
	        return false;
		}

		/**
		 * Get issued week from post.
		 */
		protected function get_issue_number( WP_Post $post ): string {
			return date( 'W', strtotime( $post->post_date ) );
		}

		/**
		 * Get issued year from post.
		 */
		protected function get_issue_year( WP_Post $post ): string {
			return date( 'Y', strtotime( $post->post_date ) );
		}

		/**
		 * Get filtered and RAW post content post.
		 */
		protected function get_post_content( WP_Post $post ): string {
			$post_content = get_post_field( 'post_content', $post->ID );
			$post_content = apply_filters( 'the_content', $post_content );
			return wp_strip_all_tags( $post_content );
		}

		/**
		 * Get the post URL.
		 */
		protected function get_post_url( WP_Post $post ): string {
			 return get_permalink( $post->ID );
		}

		/**
		 * Get the post title.
		 */
		protected function get_post_title( WP_Post $post ): string {
			 return $post->post_title;
		}

		/**
		 * Get the post publish date.
		 */
		protected function get_post_pubdate( WP_Post $post ): string {
			return date( 'Y-m-d', strtotime( $post->post_date ) );
		}

		/**
		 * Get site name as Copy right info.
		 */
		protected function get_post_copyright( WP_Post $post ): string {
			return get_bloginfo( 'site_name' );
		}

		/**
		 * Get filtered post content (incl. post excerpt) character lenght.
		 */
		protected function get_post_length( WP_Post $post ): int {
			$post_content  = get_post_field( 'post_content', $post->ID );
			$post_excerpt  = get_post_field( 'post_excerpt', $post->ID );
			$total_content = $post_excerpt . $post_content;
			$total_content = apply_filters( 'the_content', $total_content );
        	$total_content = wp_strip_all_tags( $total_content );

        	// Remove newlines (characters) from a string
			$total_content = str_replace( '\n', '', $total_content );
			$total_content = str_replace( '\r', '', $total_content );

        	return (int) strlen( utf8_decode( str_replace(' ', '', $total_content ) ) );
		}

		/**
		 * Get number of images in post content plus featured image.
		 */
		protected function get_post_images( WP_Post $post ): int {
			$post_content   = get_post_field( 'post_content', $post->ID );
			$post_content   = apply_filters( 'the_content', $post_content );
			$content_images = substr_count( $post_content, '<img' );
			$featured_image = has_post_thumbnail( $post->ID ) ? 1: 0;
	        return (int) substr_count( $post_content, '<img' ) + $featured_image;
		}

		/**
		 * Get number of images in post content plus featured image times 300.
		 */
		protected function get_post_image_lenght( WP_Post $post ): int {
			$images = $this->get_post_images( $post );
			return (int) $images * 300;
		}

		/**
		 * Get total post lenght.
		 */
		protected function get_post_total_lenght( WP_Post $post ): int {
			$content_lenght = $this->get_post_length( $post );
			$image_lenght = $this->get_post_image_lenght( $post );
			return (int) $content_lenght + $image_lenght;
		}

		/**
		 * Get edition protocol post for the last year.
		 */
		protected function get_protocol(): ? WP_Post {
			// Get last year.
			$post_name = date( 'Y', strtotime( '-1 year' ) );

			// Get existing edition protocol.
			$protocol = get_page_by_path( $post_name, OBJECT, 'edition_protocol' );

			// If that edition protocol exists, create a new one and set as Draft.
			if ( ! $protocol instanceof WP_Post ) {
				$protocol_id = wp_insert_post(
					array(
						'post_type'   => 'edition_protocol',
						'post_status' => 'draft',
						'post_title'  => $post_name,
						'post_name'   => $post_name,
					)
				);
				$protocol = get_post( $protocol_id );
			} else {
				// Set existing edition protocol as Draft.
				wp_update_post(
					array(
						'ID'          => $protocol->ID,
						'post_status' => 'draft',
					)
				);
			}
			// Set first position in meta to the header labels.
			update_post_meta( $protocol->ID, 'protocol_data', array( array( 'IssueNumber', 'IssueYear', 'Uniqueness', 'Content', 'URL', 'ObjectTitle', 'PubDate', 'MediaCopyright', 'Length', 'Images', 'ImagesLenght', 'TotalLenght', 'License', 'IsEditorial' ) ), true );

			// Return WP_Post object for edition protocol.
			return $protocol;
		}

		/**
		 * Complete the background process.
		 */
		protected function complete(): void {
			parent::complete();

			// Get edition protocol.
			$protocol = $this->get_protocol();

			// Set edition protocol as Publish.
			wp_update_post(
				array(
					'ID'          => $protocol->ID,
					'post_status' => 'publish',
				)
			);
		}

	}
});
