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

if ( file_exists( dirname( __FILE__ ) . '/vendor/autoload.php' ) ) {
	require_once dirname( __FILE__ ) . '/vendor/autoload.php';
}

$export_edition_protocol = new ExportEditionProtocol\Exporter();
$export_edition_protocol->register_hooks();
