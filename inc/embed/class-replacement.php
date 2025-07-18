<?php
namespace epiphyt\Embed_Privacy\embed;

use DOMDocument;
use DOMElement;
use DOMNode;
use epiphyt\Embed_Privacy\data\Providers;
use epiphyt\Embed_Privacy\data\Replacer;
use epiphyt\Embed_Privacy\Embed_Privacy;

/**
 * Embed replacement representation.
 * 
 * @author	Epiphyt
 * @license	GPL2
 * @package	epiphyt\Embed_Privacy
 * @since	1.10.0
 */
final class Replacement {
	/**
	 * @var		string Original content
	 */
	private $content = '';
	
	/**
	 * @var		\epiphyt\Embed_privacy\embed\Provider|null Current processed provider for this replacement
	 */
	private $provider;
	
	/**
	 * @var		\epiphyt\Embed_privacy\embed\Provider[] List of matching providers for this replacement
	 */
	private $providers = [];
	
	/**
	 * @var		array List of replacements
	 */
	private $replacements = [];
	
	/**
	 * Replacement constructor
	 * 
	 * @param	string	$content Original embedded content
	 * @param	string	$url Embedded content URL
	 */
	public function __construct( $content, $url = '' ) {
		$this->content = $content;
		$this->set_provider( $content, $url );
	}
	
	/**
	 * Get the content with an overlay.
	 * 
	 * @param	array										$attributes Embed attributes
	 * @param	\epiphyt\Embed_Privacy\embed\Provider|null	$provider Embed provider
	 * @return	string Content with embeds replaced by an overlay
	 */
	public function get( array $attributes = [], $provider = null ) {
		/**
		 * Filter the content after it has been replaced with an overlay.
		 * 
		 * @since	1.10.0
		 * 
		 * @param	string	$content Replaced content
		 */
		$content = (string) \apply_filters( 'embed_privacy_overlay_replaced_content', $this->content );
		
		/**
		 * If set to true, unknown providers are not handled via Embed Privacy.
		 * 
		 * @since	1.5.0
		 * 
		 * @param	bool	$ignore_unknown Whether unknown providers should be ignored
		 * @param	string	$content The original content
		 */
		$ignore_unknown_providers = (bool) \apply_filters( 'embed_privacy_ignore_unknown_providers', false, $content );
		
		// get default external content
		// special case for youtube-nocookie.com as it is part of YouTube provider
		if ( ! $ignore_unknown_providers || \str_contains( $content, 'youtube-nocookie.com' ) ) {
			if ( $provider instanceof Provider ) {
				$this->provider = $provider;
				$content = $this->replace( $content, $attributes );
			}
			else {
				foreach ( $this->get_providers() as $provider ) {
					$this->provider = $provider;
					$content = $this->replace( $content, $attributes );
				}
			}
			
			$this->provider = null;
		}
		
		return $content;
	}
	
	/**
	 * Get a list of characters to replace to prevent problems with DOMDocument.
	 * 
	 * @return	array List of character replacements
	 */
	private static function get_character_replacements() {
		$replacements = [
			'%' => '@@epi_percentage',
			' ' => ' data-epi-spacing ',
			'[' => '@@epi_square_bracket_start',
			']' => '@@epi_square_bracket_end',
			'{' => '@@epi_curly_bracket_start',
			'}' => '@@epi_curly_bracket_end',
		];
		
		/**
		 * Filter character replacements.
		 * 
		 * @since	1.10.0
		 * 
		 * @param	array	$replacements Current replacements
		 */
		$replacements = (array) \apply_filters( 'embed_privacy_overlay_character_replacements', $replacements );
		
		return $replacements;
	}
	
	/**
	 * Get the current provider.
	 * 
	 * @deprecated	1.10.4
	 * 
	 * @return	\epiphyt\Embed_privacy\embed\Provider|null Provider object
	 */
	public function get_provider() {
		\_doing_it_wrong(
			__METHOD__,
			\esc_html__( 'This method is outdated and will be removed in the future.', 'embed-privacy' ),
			'1.10.4'
		);
		
		return $this->provider;
	}
	
	/**
	 * Get all providers to replace an embed of.
	 * 
	 * @return	\epiphyt\Embed_privacy\embed\Provider[] Provider object
	 */
	public function get_providers() {
		return $this->providers;
	}
	
	/**
	 * Replace content with an overlay and print assets.
	 * 
	 * @param	string	$content Content to replace embeds in
	 * @param	array	$attributes Additional attributes
	 * @return	string Replaced content
	 */
	private function replace( $content, array $attributes ) {
		$new_content = $this->replace_content( $content, $attributes );
		
		if ( $new_content !== $content ) {
			Embed_Privacy::get_instance()->has_embed = true;
			Embed_Privacy::get_instance()->frontend->print_assets();
			$content = $new_content;
		}
		
		return $content;
	}
	
	/**
	 * Replace embedded content with an overlay.
	 * 
	 * @param	string	$content Content to replace embeds in
	 * @param	array	$attributes Additional attributes
	 * @return	string Updated content
	 */
	private function replace_content( $content, array $attributes ) {
		if ( empty( $content ) ) {
			return $content;
		}
		
		if ( ! $this->provider instanceof Provider ) {
			return $content;
		}
		
		/**
		 * Filter whether to ignore this embed.
		 * 
		 * @since	1.9.0
		 * 
		 * @param	bool	$ignore_embed Whether to ignore this embed
		 * @param	string	$content The original content
		 * @param	string	$provider_title Embed provider title
		 * @param	string	$provider_name Embed provider name
		 * @param	array	$attributes Additional attributes
		 */
		$ignore_embed = (bool) \apply_filters( 'embed_privacy_ignore_embed', false, $content, $this->provider->get_title(), $this->provider->get_name(), $attributes );
		
		if ( $ignore_embed ) {
			return $content;
		}
		
		$attributes = \wp_parse_args( $attributes, [
			'additional_checks' => [],
			'elements' => [ 'embed', 'iframe', 'object' ],
			'element_attribute' => 'src',
			'height' => 0,
			'ignore_aspect_ratio' => false,
			'is_oembed' => false,
			'regex' => Replacer::extend_pattern( $this->provider->get_pattern(), $this->provider ),
			'strip_newlines' => ! \has_blocks( $content ),
			'width' => 0,
		] );
		
		if ( $attributes['is_oembed'] ) {
			return Template::get( $this->provider, $content, $attributes );
		}
		
		\libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$character_replacements = self::get_character_replacements();
		$dom->loadHTML(
			'<html><meta charset="utf-8">' . \str_replace(
				\array_keys( $character_replacements ),
				\array_values( $character_replacements ),
				$content
			) . '</html>',
			\LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD
		);
		$template_dom = new DOMDocument();
		// detect domain if WordPress is installed on a sub domain
		$host = \wp_parse_url( \home_url(), \PHP_URL_HOST );
		
		if ( ! \filter_var( $host, \FILTER_VALIDATE_IP ) ) {
			$host_array = \explode( '.', \str_replace( 'www.', '', $host ) );
			$tld_count = \count( $host_array );
			
			if ( $tld_count >= 3 && \strlen( $host_array[ $tld_count - 2 ] ) === 2 ) {
				$host = \implode( '.', \array_splice( $host_array, $tld_count - 3, 3 ) );
			}
			else if ( $tld_count >= 2 ) {
				$host = \implode( '.', \array_splice( $host_array, $tld_count - 2, $tld_count ) );
			}
		}
		
		foreach ( $attributes['elements'] as $tag ) {
			$replacements = [];
			
			if ( $tag === 'object' ) {
				$attributes['element_attribute'] = 'data';
			}
			
			/** @var	\DOMElement $element */
			foreach ( $dom->getElementsByTagName( $tag ) as $element ) {
				if ( ! Embed_Privacy::get_instance()->run_checks( $attributes['additional_checks'], $element ) ) {
					continue;
				}
				
				// ignore embeds from the same (sub-)domain
				if ( \preg_match( '/https?:\/\/(.*\.)?' . \preg_quote( $host, '/' ) . '/', $element->getAttribute( $attributes['element_attribute'] ) ) ) {
					continue;
				}
				
				if ( $this->provider->is_unknown() ) {
					$embedded_host = \wp_parse_url( $element->getAttribute( $attributes['element_attribute'] ), \PHP_URL_HOST );
					
					// embeds with relative paths have no host
					// and they are local by definition, so do nothing
					// see https://github.com/epiphyt/embed-privacy/issues/27
					if ( empty( $embedded_host ) ) {
						return $content;
					}
					
					$this->provider->set_title( $embedded_host );
					$this->provider->set_name( \sanitize_title( $embedded_host ) );
					
					// check URL for available provider
					foreach ( Providers::get_instance()->get_list() as $provider ) {
						if (
							$provider->is_matching( $element->getAttribute( $attributes['element_attribute'] ) )
							&& empty( $replacements )
						) {
							continue 2;
						}
					}
				}
				else {
					if (
						empty( $attributes['regex'] )
						|| ! \preg_match_all( $this->provider->get_pattern(), $element->getAttribute( $attributes['element_attribute'] ), $matches )
					) {
						continue;
					}
					
					foreach ( $matches[0] as $matched_content ) {
						/**
						 * Filter whether the replacement should take place for given matches.
						 * 
						 * @since	1.10.9
						 * 
						 * @param	bool									$should_replace Whether the replacement should take place
					 * @param	string									$matched_content Actual matched content
						 * @param	\epiphyt\Embed_privacy\embed\Provider	$provider Provider object
						 * @param	string									$content Current content
						 * @param	mixed[]									$attributes Current attributes
						 */
						$should_replace = \apply_filters( 'embed_privacy_should_replace_match', true, $matched_content, $this->provider, $content, $attributes );
						
						if ( ! $should_replace ) {
							continue 2;
						}
					}
				}
				
				/* translators: embed title */
				$attributes['embed_title'] = $element->hasAttribute( 'title' ) ? $element->getAttribute( 'title' ) : '';
				$attributes['embed_url'] = $element->getAttribute( $attributes['element_attribute'] );
				$attributes['height'] = $element->hasAttribute( 'height' ) ? $element->getAttribute( 'height' ) : 0;
				$attributes['width'] = $element->hasAttribute( 'width' ) ? $element->getAttribute( 'width' ) : 0;
				
				// get overlay template as DOM element
				$template_dom->loadHTML(
					'<html><meta charset="utf-8">' . \str_replace( '%', '%_epi_', Template::get( $this->provider, $dom->saveHTML( $element ), $attributes ) ) . '</html>',
					\LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD
				);
				$overlay = null;
				
				/** @var	\DOMElement $div */
				foreach ( $template_dom->getElementsByTagName( 'div' ) as $div ) {
					if ( \stripos( $div->getAttribute( 'class' ), 'embed-privacy-container' ) !== false ) {
						$overlay = $div;
						break;
					}
				}
				
				// store the elements to replace (see regressive loop down below)
				if ( $overlay instanceof DOMNode || $overlay instanceof DOMElement ) {
					$replacements[] = [
						'element' => $element,
						'replace' => $dom->importNode( $overlay, true ),
					];
				}
				
				// reset embed provider name
				if ( $this->provider->is_unknown() ) {
					$this->provider->set_name( '' );
					$this->provider->set_title( '' );
				}
			}
			
			if ( ! empty( $replacements ) ) {
				$this->replacements = \array_merge( $this->replacements, $replacements );
				Embed_Privacy::get_instance()->has_embed = true;
				$elements = $dom->getElementsByTagName( $tag );
				$i = $elements->length - 1;
				
				// use regressive loop for replaceChild()
				// see: https://www.php.net/manual/en/domnode.replacechild.php#50500
				while ( $i > -1 ) {
					$element = $elements->item( $i );
					
					foreach ( $replacements as $replacement ) {
						if ( $replacement['element'] === $element ) {
							$element->parentNode->replaceChild( $replacement['replace'], $replacement['element'] ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
						}
					}
					
					--$i;
				}
				
				$content = $dom->saveHTML( $dom->documentElement ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			}
		}
		
		\libxml_use_internal_errors( false );
		
		$i = -1;
		
		// embeds for other elements need to be handled manually
		if (
			empty( $this->replacements )
			&& ! empty( $attributes['regex'] )
			&& ! $this->provider->is_unknown()
			&& ! $this->provider->is_disabled()
			&& \preg_match_all( $attributes['regex'], $content, $matches ) >= 1
		) {
			$ignored_attributes = [
				'data-*',
				'href',
			];
			
			/**
			 * Filter ignored attributes for matches.
			 * 
			 * You can use * as a wildcard, e.g. data-*
			 * 
			 * @since	1.10.11
			 * 
			 * @param	string[]								$ignored_attributes Current list of ignored attributes
			 * @param	string[]								$matches List of matched content
			 * @param	\epiphyt\Embed_privacy\embed\Provider	$provider Current provider
			 */
			$ignored_attributes = (array) \apply_filters( 'embed_privacy_ignored_match_attributes', $ignored_attributes, $matches[0], $this->provider );
			
			$ignored_attributes = \implode( '|', \array_map( static function( $item ) {
				return \preg_quote( \trim( $item ), '/' );
			}, $ignored_attributes ) );
			// allow wildcard
			$ignored_attributes = \str_replace( '\*', '(.*)', $ignored_attributes );
			
			foreach ( $matches[0] as $matched_content ) {
				++$i;
				
				if ( \str_contains( $matched_content, 'embed-privacy-' ) ) {
					continue;
				}
				
				if ( isset( $matches['original_pattern'] ) ) {
					if ( empty( $matches['original_pattern'][ $i ] ) ) {
						continue;
					}
					
					// the original pattern must not be inside a href attribute
					if ( \preg_match( '/(' . $ignored_attributes . ')="([^"]*)' . \preg_quote( $matches['original_pattern'][ $i ], '/' ) . '/', $matched_content ) ) {
						continue;
					}
				}
				
				// if the content contains an embed wrapper class, that means that the
				// embed is broken
				if (
					$this->provider->is_system()
					&& \str_contains( $matched_content, 'class="wp-block-embed__wrapper' )
				) {
					return $content;
				}
				
				/**
				 * Filter whether the replacement should take place for given matches.
				 * 
				 * @since	1.10.9
				 * 
				 * @param	bool									$should_replace Whether the replacement should take place
				 * @param	string									$matched_content Actual matched content
				 * @param	\epiphyt\Embed_privacy\embed\Provider	$provider Provider object
				 * @param	string									$content Current content
				 * @param	mixed[]									$attributes Current attributes
				 */
				$should_replace = \apply_filters( 'embed_privacy_should_replace_match', true, $matched_content, $this->provider, $content, $attributes );
				
				if ( $should_replace ) {
					$content = \str_replace(
						$matched_content,
						Template::get(
							$this->provider,
							$matched_content,
							$attributes
						),
						$content
					);
				}
			}
		}
		
		// decode to make sure there is nothing left encoded if replacements have been made
		// otherwise, content is untouched by DOMDocument, and we don't need a decoding
		// only required for WPBakery Page Builder
		if ( ! empty( $this->replacements ) && \str_contains( 'vc_row', $content ) ) {
			$content = \rawurldecode( $content );
		}
		
		return \str_replace(
			\array_merge(
				[
					'<html><meta charset="utf-8">',
					'</html>',
					'%20data-epi-spacing%20',
					'"data-epi-spacing%20',
					'%_epi_20data-epi-spacing%_epi_20', // % has been replaced with %_epi_ after replacing spaces
				],
				\array_values( $character_replacements )
			),
			\array_merge(
				[
					'',
					'',
					' ',
					'" ',
					' ',
				],
				\array_keys( $character_replacements )
			),
			$content
		);
	}
	
	/**
	 * Set the provider for this overlay.
	 * 
	 * @param	string	$content Content to get the provider from
	 * @param	string	$url URL to the embedded content
	 */
	private function set_provider( $content, $url = '' ) {
		$current_provider = null;
		$providers = Providers::get_instance()->get_list();
		
		foreach ( $providers as $provider ) {
			if (
				! $provider->is_matching(
					$content,
					Replacer::extend_pattern( $provider->get_pattern(), $provider )
				)
				&& ( empty( $url ) || ! $provider->is_matching( $url ) )
			) {
				continue;
			}
			
			$current_provider = $provider;
			
			/**
			 * Filter the overlay provider.
			 * 
			 * @since	1.10.0
			 * 
			 * @param	\epiphyt\Embed_Privacy\embed\Provider	$provider Current provider
			 * @param	string									$content Content to get the provider from
			 * @param	string									$url URL to the embedded content
			 */
			$this->providers[] = \apply_filters( 'embed_privacy_overlay_provider', $current_provider, $content, $url );
		}
		
		// support unknown oEmbed provider
		// see https://github.com/epiphyt/embed-privacy/issues/89
		if ( $current_provider === null && ! empty( $url ) ) {
			$parsed_url = \wp_parse_url( $url );
			$provider = isset( $parsed_url['host'] ) ? $parsed_url['host'] : '';
			$current_provider = new Provider();
			$current_provider->set_name( $provider );
			$current_provider->set_title( $provider );
		}
		
		// unknown embeds
		if ( $current_provider === null ) {
			$current_provider = new Provider();
			$current_provider->set_pattern( \preg_quote( $url, '/' ) );
		}
		
		/**
		 * This filter is documented in inc/embed/class-replacement.php.
		 */
		$this->providers[] = \apply_filters( 'embed_privacy_overlay_provider', $current_provider, $content, $url );
	}
}
