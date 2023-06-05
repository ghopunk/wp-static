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
		if ( static::$wp_filter ) {
			static::$wp_filter = WpHook::build_preinitialized_hooks( static::$wp_filter );
		} else {
			static::$wp_filter = array();
		}
	}

	static function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		//definisikan dahulu
		static::init();
		
		if ( ! isset( static::$wp_filter[ $hook_name ] ) ) {
			static::$wp_filter[ $hook_name ] = new WpHook();
		}

		static::$wp_filter[ $hook_name ]->add_filter( $hook_name, $callback, $priority, $accepted_args );

		return true;
	}

	static function apply_filters( $hook_name, $value, ...$args ) {

		if ( ! isset( static::$wp_filters[ $hook_name ] ) ) {
			static::$wp_filters[ $hook_name ] = 1;
		} else {
			++static::$wp_filters[ $hook_name ];
		}

		// Do 'all' actions first.
		if ( isset( static::$wp_filter['all'] ) ) {
			static::$wp_current_filter[] = $hook_name;

			$all_args = func_get_args(); // phpcs:ignore PHPCompatibility.FunctionUse.ArgumentFunctionsReportCurrentValue.NeedsInspection
			static::_wp_call_all_hook( $all_args );
		}

		if ( ! isset( static::$wp_filter[ $hook_name ] ) ) {
			if ( isset( static::$wp_filter['all'] ) ) {
				array_pop( static::$wp_current_filter );
			}

			return $value;
		}

		if ( ! isset( static::$wp_filter['all'] ) ) {
			static::$wp_current_filter[] = $hook_name;
		}

		// Pass the value to WpHook.
		array_unshift( $args, $value );

		$filtered = static::$wp_filter[ $hook_name ]->apply_filters( $value, $args );

		array_pop( static::$wp_current_filter );

		return $filtered;
	}

	static function apply_filters_ref_array( $hook_name, $args ) {

		if ( ! isset( static::$wp_filters[ $hook_name ] ) ) {
			static::$wp_filters[ $hook_name ] = 1;
		} else {
			++static::$wp_filters[ $hook_name ];
		}

		// Do 'all' actions first.
		if ( isset( static::$wp_filter['all'] ) ) {
			static::$wp_current_filter[] = $hook_name;
			$all_args            = func_get_args(); // phpcs:ignore PHPCompatibility.FunctionUse.ArgumentFunctionsReportCurrentValue.NeedsInspection
			static::_wp_call_all_hook( $all_args );
		}

		if ( ! isset( static::$wp_filter[ $hook_name ] ) ) {
			if ( isset( static::$wp_filter['all'] ) ) {
				array_pop( static::$wp_current_filter );
			}

			return $args[0];
		}

		if ( ! isset( static::$wp_filter['all'] ) ) {
			static::$wp_current_filter[] = $hook_name;
		}

		$filtered = static::$wp_filter[ $hook_name ]->apply_filters( $args[0], $args );

		array_pop( static::$wp_current_filter );

		return $filtered;
	}

	static function has_filter( $hook_name, $callback = false ) {

		if ( ! isset( static::$wp_filter[ $hook_name ] ) ) {
			return false;
		}

		return static::$wp_filter[ $hook_name ]->has_filter( $hook_name, $callback );
	}

	static function remove_filter( $hook_name, $callback, $priority = 10 ) {

		$r = false;

		if ( isset( static::$wp_filter[ $hook_name ] ) ) {
			$r = static::$wp_filter[ $hook_name ]->remove_filter( $hook_name, $callback, $priority );

			if ( ! static::$wp_filter[ $hook_name ]->callbacks ) {
				unset( static::$wp_filter[ $hook_name ] );
			}
		}

		return $r;
	}

	static function remove_all_filters( $hook_name, $priority = false ) {

		if ( isset( static::$wp_filter[ $hook_name ] ) ) {
			static::$wp_filter[ $hook_name ]->remove_all_filters( $priority );

			if ( ! static::$wp_filter[ $hook_name ]->has_filters() ) {
				unset( static::$wp_filter[ $hook_name ] );
			}
		}

		return true;
	}

	static function current_filter() {

		return end( static::$wp_current_filter );
	}

	static function doing_filter( $hook_name = null ) {

		if ( null === $hook_name ) {
			return ! empty( static::$wp_current_filter );
		}

		return in_array( $hook_name, static::$wp_current_filter, true );
	}

	static function did_filter( $hook_name ) {

		if ( ! isset( static::$wp_filters[ $hook_name ] ) ) {
			return 0;
		}

		return static::$wp_filters[ $hook_name ];
	}

	static function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
		return static::add_filter( $hook_name, $callback, $priority, $accepted_args );
	}

	static function do_action( $hook_name, ...$arg ) {

		if ( ! isset( static::$wp_actions[ $hook_name ] ) ) {
			static::$wp_actions[ $hook_name ] = 1;
		} else {
			++static::$wp_actions[ $hook_name ];
		}

		// Do 'all' actions first.
		if ( isset( static::$wp_filter['all'] ) ) {
			static::$wp_current_filter[] = $hook_name;
			$all_args            = func_get_args(); // phpcs:ignore PHPCompatibility.FunctionUse.ArgumentFunctionsReportCurrentValue.NeedsInspection
			static::_wp_call_all_hook( $all_args );
		}

		if ( ! isset( static::$wp_filter[ $hook_name ] ) ) {
			if ( isset( static::$wp_filter['all'] ) ) {
				array_pop( static::$wp_current_filter );
			}

			return;
		}

		if ( ! isset( static::$wp_filter['all'] ) ) {
			static::$wp_current_filter[] = $hook_name;
		}

		if ( empty( $arg ) ) {
			$arg[] = '';
		} elseif ( is_array( $arg[0] ) && 1 === count( $arg[0] ) && isset( $arg[0][0] ) && is_object( $arg[0][0] ) ) {
			// Backward compatibility for PHP4-style passing of `array( &$this )` as action `$arg`.
			$arg[0] = $arg[0][0];
		}

		static::$wp_filter[ $hook_name ]->do_action( $arg );

		array_pop( static::$wp_current_filter );
	}

	static function do_action_ref_array( $hook_name, $args ) {

		if ( ! isset( static::$wp_actions[ $hook_name ] ) ) {
			static::$wp_actions[ $hook_name ] = 1;
		} else {
			++static::$wp_actions[ $hook_name ];
		}

		// Do 'all' actions first.
		if ( isset( static::$wp_filter['all'] ) ) {
			static::$wp_current_filter[] = $hook_name;
			$all_args            = func_get_args(); // phpcs:ignore PHPCompatibility.FunctionUse.ArgumentFunctionsReportCurrentValue.NeedsInspection
			static::_wp_call_all_hook( $all_args );
		}

		if ( ! isset( static::$wp_filter[ $hook_name ] ) ) {
			if ( isset( static::$wp_filter['all'] ) ) {
				array_pop( static::$wp_current_filter );
			}

			return;
		}

		if ( ! isset( static::$wp_filter['all'] ) ) {
			static::$wp_current_filter[] = $hook_name;
		}

		static::$wp_filter[ $hook_name ]->do_action( $args );

		array_pop( static::$wp_current_filter );
	}

	static function has_action( $hook_name, $callback = false ) {
		return has_filter( $hook_name, $callback );
	}

	static function remove_action( $hook_name, $callback, $priority = 10 ) {
		return static::remove_filter( $hook_name, $callback, $priority );
	}

	static function remove_all_actions( $hook_name, $priority = false ) {
		return static::remove_all_filters( $hook_name, $priority );
	}

	static function current_action() {
		return static::current_filter();
	}

	static function doing_action( $hook_name = null ) {
		return static::doing_filter( $hook_name );
	}

	static function did_action( $hook_name ) {

		if ( ! isset( static::$wp_actions[ $hook_name ] ) ) {
			return 0;
		}

		return static::$wp_actions[ $hook_name ];
	}

	static function _wp_call_all_hook( $args ) {

		static::$wp_filter['all']->do_all_hook( $args );
	}

	static function wp_count_filter($tag) {
		$count = 0;
		if ( isset( static::$wp_filter[ $tag ] ) && isset( static::$wp_filter[ $tag ]->callbacks ) ) {
			$count = count( static::$wp_filter[ $tag ]->callbacks );
		}
		return $count;
	}

	static function wp_count_action($tag) {
		return static::$wp_count_filter($tag);
	}

}