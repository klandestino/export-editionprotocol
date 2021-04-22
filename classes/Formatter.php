<?php
/**
 * Formatting class.
 */

namespace ExportEditionProtocol;

use WP_Post;

class Formatter {

	/**
	 * Format a post according to spec.
	 */
	public function format( WP_Post $post ): array {
		return [
			'IssueNumber'    => $this->get_issue_number( $post ),
			'IssueYear'      => $this->get_issue_year( $post ),
			'Uniqueness'     => 1,
			'Content'        => $this->get_post_content( $post ),
			'URL'            => $this->get_post_url( $post ),
			'ObjectTitle'    => $this->get_post_title( $post ),
			'PubDate'        => $this->get_post_pubdate( $post ),
			'MediaCopyright' => $this->get_post_copyright(),
			'Length'         => '=LEN(D%index%)',
			'Images'         => $this->get_post_images( $post ),
			'ImagesLength'   => '=SUM(J%index%*300)',
			'TotalLength'    => '=SUM(I%index%+K%index%)',
			'License'        => '',
			'IsEditorial'    => 1,
		];
	}

	/**
	 * Return the first row (header) for the excel sheet.
	 */
	public function first_row(): array {
		return [ 'IssueNumber', 'IssueYear', 'Uniqueness', 'Content', 'URL', 'ObjectTitle', 'PubDate', 'MediaCopyright', 'Length', 'Images', 'ImagesLength', 'TotalLength', 'License', 'IsEditorial' ];
	}

	/**
	 * Get issued week from post.
	 */
	private function get_issue_number( WP_Post $post ): string {
		return date( 'W', strtotime( $post->post_date ) );
	}

	/**
	 * Get issued year from post.
	 */
	private function get_issue_year( WP_Post $post ): string {
		return date( 'Y', strtotime( $post->post_date ) );
	}

	/**
	 * Get filtered and raw post content.
	 */
	private function get_post_content( WP_Post $post ): string {
		$post_excerpt      = get_post_field( 'post_excerpt', $post );
		$post_content      = get_post_field( 'post_content', $post );
		$total_content     = $post_excerpt . $post_content;
		$total_content     = apply_filters( 'the_content', $total_content );
		$raw_total_content = wp_strip_all_tags( $total_content );
		$raw_total_content = preg_replace( "/\r|\n/", '', $raw_total_content );

		return wp_strip_all_tags( $raw_total_content );
	}

	/**
	 * Get the post URL.
	 */
	protected function get_post_url( WP_Post $post ): string {
		return get_the_permalink( $post );
	}

	/**
	 * Get the post title.
	 */
	private function get_post_title( WP_Post $post ): string {
		return wp_strip_all_tags( $post->post_title );
	}

	/**
	 * Get the post publish date.
	 */
	private function get_post_pubdate( WP_Post $post ): string {
		return date( 'Y-m-d', strtotime( $post->post_date ) );
	}

	/**
	 * Get site name as Copy right info.
	 */
	private function get_post_copyright(): string {
		return get_bloginfo( 'site_name' );
	}

	/**
	 * Get number of images in post content plus featured image.
	 */
	private function get_post_images( WP_Post $post ): int {
		$post_content   = get_post_field( 'post_content', $post->ID );
		$post_content   = apply_filters( 'the_content', $post_content );
		$post_content   = preg_replace( '@<(noscript)[^>]*?>.*?</\\1>@si', '', $post_content );
		$content_images = substr_count( $post_content, '<img' );
		$featured_image = has_post_thumbnail( $post->ID ) ? 1: 0;

		return (int) $content_images + $featured_image;
	}

}
