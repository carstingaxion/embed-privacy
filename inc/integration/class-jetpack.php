<?php
namespace epiphyt\Embed_Privacy\integration;

use Automattic\Jetpack\Assets;
use epiphyt\Embed_Privacy\embed\Overlay;
use epiphyt\Embed_Privacy\Embed_Privacy;
use Jetpack as JetpackBase;

/**
 * Jetpack integration for Embed Privacy.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 * @since	1.10.0
 */
final class Jetpack {
	/**
	 * Initialize functionality.
	 */
	public static function init() {
		\add_action( 'wp_enqueue_scripts', [ self::class, 'deregister_assets' ], 100 );
		\add_filter( 'embed_privacy_overlay_replaced_content', [ self::class, 'replace_facebook_posts' ] );
	}
	
	/**
	 * Deregister assets.
	 */
	public static function deregister_assets() {
		\wp_deregister_script( 'jetpack-facebook-embed' );
	}
	
	/**
	 * Replace Facebook posts.
	 * 
	 * @param	string	$content Current replaced content
	 * @return	string Updated replaced content
	 */
	public static function replace_facebook_posts( $content ) {
		if ( ! \str_contains( $content, 'class="fb-post"' ) ) {
			return $content;
		}
		
		$attributes = [
			'additional_checks' => [
				[
					'attribute' => 'class',
					'compare' => '===',
					'type' => 'attribute',
					'value' => 'fb-post',
				],
			],
			'assets' => [],
			'check_always_active' => true,
			'element_attribute' => 'data-href',
			'elements' => [
				'div',
			],
		];
		
		// register jetpack script if available
		if ( \class_exists( '\Automattic\Jetpack\Assets' ) && \defined( 'JETPACK__VERSION' ) ) {
			/** @disregard P1009 */
			$jetpack = JetpackBase::init();
			$attributes['assets'][] = [
				'type' => 'inline',
				'object_name' => 'jpfbembed',
				'data' => [
					/**
					 * Filter the Jetpack sharing Facebook app ID.
					 * 
					 * @since	1.4.5
					 * 
					 * @param	string	$app_id The current app ID
					 */
					'appid' => \apply_filters( 'jetpack_sharing_facebook_app_id', '249643311490' ),
					'locale' => $jetpack->get_locale(),
				],
			];
			$attributes['assets'][] = [
				'type' => 'script',
				'handle' => 'jetpack-facebook-embed',
				'src' => Assets::get_file_url_for_environment( '_inc/build/facebook-embed.min.js', '_inc/facebook-embed.js' ),
				'version' => \JETPACK__VERSION,
			];
		}
		
		$overlay = new Overlay( $content );
		$new_content = $overlay->get( $attributes );
		
		if ( $new_content !== $content ) {
			Embed_Privacy::get_instance()->has_embed = true;
			$content = $new_content;
		}
		
		return $content;
	}
}