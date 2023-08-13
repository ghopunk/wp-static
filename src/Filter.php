<?php

namespace ghopunk\WpStatic;

use ghopunk\WpStatic\WpHook;

//wp-includes/plugin.php

class Filter {
	/** @var WpHook[] $wp_filter */
	static ?array $wp_filter = array();

	/** @var int[] $wp_actions */
	static ?array $wp_actions = array();

	/** @var int[] $wp_filters */
	static ?array $wp_filters = array();

	/** @var string[] $wp_current_filter */
	static ?array $wp_current_filter = array();

	static function init(){
		if ( self::$wp_filter ) {
			self::$wp_filter = WpHook::build_preinitialized_hooks( self::$wp_filter );
		} else {
			self::$wp_filter = array();
		}
	}

	static function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		//definisikan dahulu
		self::init();
		
		if ( ! isset( self::$wp_filter[ $hook_name ] ) ) {
			self::$wp_filter[ $hook_name ] = new WpHook();
		}

		self::$wp_filter[ $hook_name ]->add_filter( $hook_name, $callback, $priority, $accepted_args );

		return true;
	}

	static function apply_filters( $hook_name, $value, ...$args ) {

		if ( ! isset( self::$wp_filters[ $hook_name ] ) ) {
			self::$wp_filters[ $hook_name ] = 1;
		} else {
			++self::$wp_filters[ $hook_name ];
		}

		// Do 'all' actions first.
		if ( isset( self::$wp_filter['all'] ) ) {
			self::$wp_current_filter[] = $hook_name;

			$all_args = func_get_args(); // phpcs:ignore PHPCompatibility.FunctionUse.ArgumentFunctionsReportCurrentValue.NeedsInspection
			self::_wp_call_all_hook( $all_args );
		}

		if ( ! isset( self::$wp_filter[ $hook_name ] ) ) {
			if ( isset( self::$wp_filter['all'] ) ) {
				array_pop( self::$wp_current_filter );
			}

			return $value;
		}

		if ( ! isset( self::$wp_filter['all'] ) ) {
			self::$wp_current_filter[] = $hook_name;
		}

		// Pass the value to WpHook.
		array_unshift( $args, $value );

		$filtered = self::$wp_filter[ $hook_name ]->apply_filters( $value, $args );

		array_pop( self::$wp_current_filter );

		return $filtered;
	}

	static function apply_filters_ref_array( $hook_name, $args ) {

		if ( ! isset( self::$wp_filters[ $hook_name ] ) ) {
			self::$wp_filters[ $hook_name ] = 1;
		} else {
			++self::$wp_filters[ $hook_name ];
		}

		// Do 'all' actions first.
		if ( isset( self::$wp_filter['all'] ) ) {
			self::$wp_current_filter[] = $hook_name;
			$all_args            = func_get_args(); // phpcs:ignore PHPCompatibility.FunctionUse.ArgumentFunctionsReportCurrentValue.NeedsInspection
			self::_wp_call_all_hook( $all_args );
		}

		if ( ! isset( self::$wp_filter[ $hook_name ] ) ) {
			if ( isset( self::$wp_filter['all'] ) ) {
				array_pop( self::$wp_current_filter );
			}

			return $args[0];
		}

		if ( ! isset( self::$wp_filter['all'] ) ) {
			self::$wp_current_filter[] = $hook_name;
		}

		$filtered = self::$wp_filter[ $hook_name ]->apply_filters( $args[0], $args );

		array_pop( self::$wp_current_filter );

		return $filtered;
	}

	static function has_filter( $hook_name, $callback = false ) {

		if ( ! isset( self::$wp_filter[ $hook_name ] ) ) {
			return false;
		}

		return self::$wp_filter[ $hook_name ]->has_filter( $hook_name, $callback );
	}

	static function remove_filter( $hook_name, $callback, $priority = 10 ) {

		$r = false;

		if ( isset( self::$wp_filter[ $hook_name ] ) ) {
			$r = self::$wp_filter[ $hook_name ]->remove_filter( $hook_name, $callback, $priority );

			if ( ! self::$wp_filter[ $hook_name ]->callbacks ) {
				unset( self::$wp_filter[ $hook_name ] );
			}
		}

		return $r;
	}

	static function remove_all_filters( $hook_name, $priority = false ) {

		if ( isset( self::$wp_filter[ $hook_name ] ) ) {
			self::$wp_filter[ $hook_name ]->remove_all_filters( $priority );

			if ( ! self::$wp_filter[ $hook_name ]->has_filters() ) {
				unset( self::$wp_filter[ $hook_name ] );
			}
		}

		return true;
	}

	static function current_filter() {

		return end( self::$wp_current_filter );
	}

	static function doing_filter( $hook_name = null ) {

		if ( null === $hook_name ) {
			return ! empty( self::$wp_current_filter );
		}

		return in_array( $hook_name, self::$wp_current_filter, true );
	}

	static function did_filter( $hook_name ) {

		if ( ! isset( self::$wp_filters[ $hook_name ] ) ) {
			return 0;
		}

		return self::$wp_filters[ $hook_name ];
	}

	static function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		return self::add_filter( $hook_name, $callback, $priority, $accepted_args );
	}

	static function do_action( $hook_name, ...$arg ) {

		if ( ! isset( self::$wp_actions[ $hook_name ] ) ) {
			self::$wp_actions[ $hook_name ] = 1;
		} else {
			++self::$wp_actions[ $hook_name ];
		}

		// Do 'all' actions first.
		if ( isset( self::$wp_filter['all'] ) ) {
			self::$wp_current_filter[] = $hook_name;
			$all_args            = func_get_args(); // phpcs:ignore PHPCompatibility.FunctionUse.ArgumentFunctionsReportCurrentValue.NeedsInspection
			self::_wp_call_all_hook( $all_args );
		}

		if ( ! isset( self::$wp_filter[ $hook_name ] ) ) {
			if ( isset( self::$wp_filter['all'] ) ) {
				array_pop( self::$wp_current_filter );
			}

			return;
		}

		if ( ! isset( self::$wp_filter['all'] ) ) {
			self::$wp_current_filter[] = $hook_name;
		}

		if ( empty( $arg ) ) {
			$arg[] = '';
		} elseif ( is_array( $arg[0] ) && 1 === count( $arg[0] ) && isset( $arg[0][0] ) && is_object( $arg[0][0] ) ) {
			// Backward compatibility for PHP4-style passing of `array( &$this )` as action `$arg`.
			$arg[0] = $arg[0][0];
		}

		self::$wp_filter[ $hook_name ]->do_action( $arg );

		array_pop( self::$wp_current_filter );
	}

	static function do_action_ref_array( $hook_name, $args ) {

		if ( ! isset( self::$wp_actions[ $hook_name ] ) ) {
			self::$wp_actions[ $hook_name ] = 1;
		} else {
			++self::$wp_actions[ $hook_name ];
		}

		// Do 'all' actions first.
		if ( isset( self::$wp_filter['all'] ) ) {
			self::$wp_current_filter[] = $hook_name;
			$all_args            = func_get_args(); // phpcs:ignore PHPCompatibility.FunctionUse.ArgumentFunctionsReportCurrentValue.NeedsInspection
			self::_wp_call_all_hook( $all_args );
		}

		if ( ! isset( self::$wp_filter[ $hook_name ] ) ) {
			if ( isset( self::$wp_filter['all'] ) ) {
				array_pop( self::$wp_current_filter );
			}

			return;
		}

		if ( ! isset( self::$wp_filter['all'] ) ) {
			self::$wp_current_filter[] = $hook_name;
		}

		self::$wp_filter[ $hook_name ]->do_action( $args );

		array_pop( self::$wp_current_filter );
	}

	static function has_action( $hook_name, $callback = false ) {
		return self::has_filter( $hook_name, $callback );
	}

	static function remove_action( $hook_name, $callback, $priority = 10 ) {
		return self::remove_filter( $hook_name, $callback, $priority );
	}

	static function remove_all_actions( $hook_name, $priority = false ) {
		return self::remove_all_filters( $hook_name, $priority );
	}

	static function current_action() {
		return self::current_filter();
	}

	static function doing_action( $hook_name = null ) {
		return self::doing_filter( $hook_name );
	}

	static function did_action( $hook_name ) {

		if ( ! isset( self::$wp_actions[ $hook_name ] ) ) {
			return 0;
		}

		return self::$wp_actions[ $hook_name ];
	}

	static function _wp_call_all_hook( $args ) {

		self::$wp_filter['all']->do_all_hook( $args );
	}

	static function wp_count_filter($tag) {
		$count = 0;
		if ( isset( self::$wp_filter[ $tag ] ) && isset( self::$wp_filter[ $tag ]->callbacks ) ) {
			$count = count( self::$wp_filter[ $tag ]->callbacks );
		}
		return $count;
	}

	static function wp_count_action($tag) {
		return self::$wp_count_filter($tag);
	}

}