<?php
namespace ghopunk\WpStatic;

//wp-includes/shortcodes.php

class Shortcodes {
	
	static ?array $shortcode_tags = array();

	static function add_shortcode( $tag, $callback ) {

		if ( '' === trim( $tag ) ) {
			return;
		}

		if ( 0 !== preg_match( '@[<>&/\[\]\x00-\x20=]@', $tag ) ) {
			return;
		}

		static::$shortcode_tags[ $tag ] = $callback;
	}

	static function remove_shortcode( $tag ) {

		unset( static::$shortcode_tags[ $tag ] );
	}

	static function remove_all_shortcodes() {

		static::$shortcode_tags = array();
	}

	static function shortcode_exists( $tag ) {
		return array_key_exists( $tag, static::$shortcode_tags );
	}

	static function has_shortcode( $content, $tag ) {
		if ( false === strpos( $content, '[' ) ) {
			return false;
		}

		if ( static::shortcode_exists( $tag ) ) {
			preg_match_all( '/' . static::get_shortcode_regex() . '/', $content, $matches, PREG_SET_ORDER );
			if ( empty( $matches ) ) {
				return false;
			}

			foreach ( $matches as $shortcode ) {
				if ( $tag === $shortcode[2] ) {
					return true;
				} elseif ( ! empty( $shortcode[5] ) && static::has_shortcode( $shortcode[5], $tag ) ) {
					return true;
				}
			}
		}
		return false;
	}

	static function apply_shortcodes( $content, $ignore_html = false ) {
		return static::do_shortcode( $content, $ignore_html );
	}

	static function do_shortcode( $content, $ignore_html = false ) {

		if ( false === strpos( $content, '[' ) ) {
			return $content;
		}
		
		if ( empty( static::$shortcode_tags ) || ! is_array( static::$shortcode_tags ) ) {
			return $content;
		}

		// Find all registered tag names in $content.
		preg_match_all( '@\[([^<>&/\[\]\x00-\x20=]++)@', $content, $matches );
		$tagnames = array_intersect( array_keys( static::$shortcode_tags ), $matches[1] );

		if ( empty( $tagnames ) ) {
			return $content;
		}
		
		//tidak dipakai, rawan error
		//$content = static::do_shortcodes_in_html_tags( $content, $ignore_html, $tagnames );

		$pattern = static::get_shortcode_regex( $tagnames );
		$content = preg_replace_callback( "/$pattern/", '\\' . __CLASS__ . '::do_shortcode_tag', $content );

		// Always restore square braces so we don't break things like <!--[if IE ]>.
		$content = static::unescape_invalid_shortcodes( $content );

		return $content;
	}
	
	static function get_shortcode_regex( $tagnames = null ) {

		if ( empty( $tagnames ) ) {
			$tagnames = array_keys( static::$shortcode_tags );
		}
		$tagregexp = implode( '|', array_map( 'preg_quote', $tagnames ) );

		// WARNING! Do not change this regex without changing do_shortcode_tag() and strip_shortcode_tag().
		// Also, see shortcode_unautop() and shortcode.js.

		// phpcs:disable Squiz.Strings.ConcatenationSpacing.PaddingFound -- don't remove regex indentation
		return '\\['                             // Opening bracket.
			. '(\\[?)'                           // 1: Optional second opening bracket for escaping shortcodes: [[tag]].
			. "($tagregexp)"                     // 2: Shortcode name.
			. '(?![\\w-])'                       // Not followed by word character or hyphen.
			. '('                                // 3: Unroll the loop: Inside the opening shortcode tag.
			.     '[^\\]\\/]*'                   // Not a closing bracket or forward slash.
			.     '(?:'
			.         '\\/(?!\\])'               // A forward slash not followed by a closing bracket.
			.         '[^\\]\\/]*'               // Not a closing bracket or forward slash.
			.     ')*?'
			. ')'
			. '(?:'
			.     '(\\/)'                        // 4: Self closing tag...
			.     '\\]'                          // ...and closing bracket.
			. '|'
			.     '\\]'                          // Closing bracket.
			.     '(?:'
			.         '('                        // 5: Unroll the loop: Optionally, anything between the opening and closing shortcode tags.
			.             '[^\\[]*+'             // Not an opening bracket.
			.             '(?:'
			.                 '\\[(?!\\/\\2\\])' // An opening bracket not followed by the closing shortcode tag.
			.                 '[^\\[]*+'         // Not an opening bracket.
			.             ')*+'
			.         ')'
			.         '\\[\\/\\2\\]'             // Closing shortcode tag.
			.     ')?'
			. ')'
			. '(\\]?)';                          // 6: Optional second closing brocket for escaping shortcodes: [[tag]].
		// phpcs:enable
	}

	static function do_shortcode_tag( $m ) {

		// Allow [[foo]] syntax for escaping a tag.
		if ( '[' === $m[1] && ']' === $m[6] ) {
			return substr( $m[0], 1, -1 );
		}

		$tag  = $m[2];
		$attr = static::shortcode_parse_atts( $m[3] );

		if ( ! is_callable( static::$shortcode_tags[ $tag ] ) ) {
			return $m[0];
		}
		
		//filter tidak dipakai
		//$return = static::apply_filters( 'pre_do_shortcode_tag', false, $tag, $attr, $m );
		//if ( false !== $return ) {
		//	return $return;
		//}

		$content = isset( $m[5] ) ? $m[5] : null;

		$output = $m[1] . call_user_func( static::$shortcode_tags[ $tag ], $attr, $content, $tag ) . $m[6];
		
		return $output;
		//filter tidak dipakai
		//return static::apply_filters( 'do_shortcode_tag', $output, $tag, $attr, $m );
	}

	static function unescape_invalid_shortcodes( $content ) {
		// Clean up entire string, avoids re-parsing HTML.
		$trans = array(
			'&#91;' => '[',
			'&#93;' => ']',
		);

		$content = strtr( $content, $trans );

		return $content;
	}

	static function get_shortcode_atts_regex() {
		return '/([\w-]+)\s*=\s*"([^"]*)"(?:\s|$)|([\w-]+)\s*=\s*\'([^\']*)\'(?:\s|$)|([\w-]+)\s*=\s*([^\s\'"]+)(?:\s|$)|"([^"]*)"(?:\s|$)|\'([^\']*)\'(?:\s|$)|(\S+)(?:\s|$)/';
	}

	static function shortcode_parse_atts( $text ) {
		$atts    = array();
		$pattern = static::get_shortcode_atts_regex();
		$text    = preg_replace( "/[\x{00a0}\x{200b}]+/u", ' ', $text );
		if ( preg_match_all( $pattern, $text, $match, PREG_SET_ORDER ) ) {
			foreach ( $match as $m ) {
				if ( ! empty( $m[1] ) ) {
					$atts[ strtolower( $m[1] ) ] = stripcslashes( $m[2] );
				} elseif ( ! empty( $m[3] ) ) {
					$atts[ strtolower( $m[3] ) ] = stripcslashes( $m[4] );
				} elseif ( ! empty( $m[5] ) ) {
					$atts[ strtolower( $m[5] ) ] = stripcslashes( $m[6] );
				} elseif ( isset( $m[7] ) && strlen( $m[7] ) ) {
					$atts[] = stripcslashes( $m[7] );
				} elseif ( isset( $m[8] ) && strlen( $m[8] ) ) {
					$atts[] = stripcslashes( $m[8] );
				} elseif ( isset( $m[9] ) ) {
					$atts[] = stripcslashes( $m[9] );
				}
			}

			// Reject any unclosed HTML elements.
			foreach ( $atts as &$value ) {
				if ( false !== strpos( $value, '<' ) ) {
					if ( 1 !== preg_match( '/^[^<]*+(?:<[^>]*+>[^<]*+)*+$/', $value ) ) {
						$value = '';
					}
				}
			}
		} else {
			$atts = ltrim( $text );
		}

		return $atts;
	}

	static function shortcode_atts( $pairs, $atts, $shortcode = '' ) {
		$atts = (array) $atts;
		$out  = array();
		foreach ( $pairs as $name => $default ) {
			if ( array_key_exists( $name, $atts ) ) {
				$out[ $name ] = $atts[ $name ];
			} else {
				$out[ $name ] = $default;
			}
		}

		if ( $shortcode ) {
			//filter tidak dipakai
			//$out = static::apply_filters( "shortcode_atts_{$shortcode}", $out, $pairs, $atts, $shortcode );
		}

		return $out;
	}

	static function strip_shortcodes( $content ) {

		if ( false === strpos( $content, '[' ) ) {
			return $content;
		}

		if ( empty( static::$shortcode_tags ) || ! is_array( static::$shortcode_tags ) ) {
			return $content;
		}

		// Find all registered tag names in $content.
		preg_match_all( '@\[([^<>&/\[\]\x00-\x20=]++)@', $content, $matches );

		$tags_to_remove = array_keys( static::$shortcode_tags );
		
		//filter tidak dipakai
		//$tags_to_remove = static::apply_filters( 'strip_shortcodes_tagnames', $tags_to_remove, $content );

		$tagnames = array_intersect( $tags_to_remove, $matches[1] );

		if ( empty( $tagnames ) ) {
			return $content;
		}
		
		//tidak dipakai, rawan error
		//$content = static::do_shortcodes_in_html_tags( $content, true, $tagnames );

		$pattern = static::get_shortcode_regex( $tagnames );
		$content = preg_replace_callback( "/$pattern/", '\\' . __CLASS__ . '::strip_shortcode_tag', $content );

		// Always restore square braces so we don't break things like <!--[if IE ]>.
		$content = static::unescape_invalid_shortcodes( $content );

		return $content;
	}

	static function strip_shortcode_tag( $m ) {
		// Allow [[foo]] syntax for escaping a tag.
		if ( '[' === $m[1] && ']' === $m[6] ) {
			return substr( $m[0], 1, -1 );
		}

		return $m[1] . $m[6];
	}
	
	//add from me. Only execute spesific shortcode tags
	//tags array. ex: [ 'tags_1', 'tags_2' ]
	static function do_shortcode_by_tags( $content, array $tags = array(), $ignore_html = false ) {
		
		if ( false === strpos( $content, '[' ) ) {
			return $content;
		}
		
		if ( empty( static::$shortcode_tags ) || ! is_array( static::$shortcode_tags ) ) {
			return $content;
		}
		
		if( empty($tags) ) {
			return static::do_shortcode( $content, $ignore_html );
		}
		
		// Find all registered tag names in $content.
		preg_match_all( '@\[([^<>&/\[\]\x00-\x20=]++)@', $content, $matches );
		$tagnames = array_intersect( $tags, $matches[1] );

		if ( empty( $tagnames ) ) {
			return $content;
		}
		
		//tidak dipakai, rawan error
		//$content = static::do_shortcodes_in_html_tags( $content, $ignore_html, $tagnames );

		$pattern = static::get_shortcode_regex( $tagnames );
		$content = preg_replace_callback( "/$pattern/", '\\' . __CLASS__ . '::do_shortcode_tag', $content );

		// Always restore square braces so we don't break things like <!--[if IE ]>.
		$content = static::unescape_invalid_shortcodes( $content );

		return $content;
	}
}