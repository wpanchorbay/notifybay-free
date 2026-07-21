<?php
/**
 * Action Scheduler Fallback System.
 *
 * Provides a self-healing queue runner that bypasses WP-Cron and Loopbacks,
 * processing a dynamic batch of actions based on an assigned time/weight budget.
 *
 * @package    NotifyBay
 * @subpackage Engine
 * @since      1.0.0
 * @author     WPAnchorBay <sankarsan@gmail.com>
 */

namespace NotifyBay\Engine;

use NotifyBay\Core\Plugin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ActionSchedulerFallback
 */
class ActionSchedulerFallback {



	/**
	 * The single instance of the class.
	 *
	 * @var ActionSchedulerFallback
	 */
	private static $instance = null;

	/**
	 * Maximum weight budget per page load.
	 */
	const MAX_BUDGET = 100;

	/**
	 * Transient key for the fallback queue.
	 */
	const QUEUE_TRANSIENT = 'notifybay_fallback_queue';

	/**
	 * Get the instance.
	 *
	 * @return ActionSchedulerFallback
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register hooks.
	 *
	 * @param Plugin $plugin The plugin instance.
	 */
	public function run( Plugin $plugin ) {
		$loader = $plugin->get_loader();

		// Run the fallback check on 'init' so it catches both admin and frontend traffic
		$loader->add_action( 'init', $this, 'process_fallback_queue' );
	}

	/**
	 * Process the fallback queue dynamically based on the weight budget.
	 */
	public function process_fallback_queue() {
		// Only run if Action Scheduler is active
		if ( ! class_exists( 'ActionScheduler_QueueRunner' ) || ! function_exists( 'as_get_scheduled_actions' ) ) {
			return;
		}

		$queue = get_transient( self::QUEUE_TRANSIENT );

		// 1. Rebuild the queue buffer if empty or expired
		if ( false === $queue ) {
			$actions = as_get_scheduled_actions(
				array(
					'group'    => 'notifybay_alerts',
					'status'   => \ActionScheduler_Store::STATUS_PENDING,
					'per_page' => 100,
					'date'     => time(), // Only fetch past due actions
				)
			);

			$queue = array();
			if ( ! empty( $actions ) ) {
				foreach ( $actions as $action_id => $action ) {
					$queue[] = array(
						'id'   => $action_id,
						'hook' => $action->get_hook(),
					);
				}
			}

			// Cache the queue (even if empty, to prevent constant DB queries)
			set_transient( self::QUEUE_TRANSIENT, $queue, MINUTE_IN_SECONDS );
		}
		// 2. Process actions if queue is not empty
		if ( ! empty( $queue ) && is_array( $queue ) ) {
			$current_budget  = 0;
			$actions_to_run  = array();
			$remaining_queue = array();

			// Determine which actions fit in the budget
			foreach ( $queue as $index => $item ) {
				$cost = $this->get_action_weight( $item['hook'] );

				// If adding this action exceeds budget (and we already have at least 1 action), stop batching
				if ( $current_budget + $cost > self::MAX_BUDGET && count( $actions_to_run ) > 0 ) {
					// Slice the remainder of the queue to save it back
					$remaining_queue = array_slice( $queue, $index );
					break;
				}

				$current_budget  += $cost;
				$actions_to_run[] = array(
					'id'   => $item['id'],
					'hook' => $item['hook'],
				);
			}

			// Update the transient with the remaining items (maintaining the existing expiration conceptually)
			if ( ! empty( $remaining_queue ) ) {
				set_transient( self::QUEUE_TRANSIENT, $remaining_queue, MINUTE_IN_SECONDS );
			} else {
				// We emptied the queue, force a rebuild sooner if needed, or just let it expire normally
				set_transient( self::QUEUE_TRANSIENT, array(), MINUTE_IN_SECONDS );
			}

			// 3. Execute the selected batch inline
			if ( ! empty( $actions_to_run ) ) {
				$runner = \ActionScheduler_QueueRunner::instance();
				foreach ( $actions_to_run as $action_data ) {
					$action_id = $action_data['id'];
					$hook      = $action_data['hook'];

					// Using process_action properly handles status updates, exceptions, and logging
					if ( method_exists( $runner, 'process_action' ) ) {
						$start_time = microtime( true );
						$runner->process_action( $action_id, 'notifybay_fallback' );
						$end_time = microtime( true );
						$duration = round( ( $end_time - $start_time ) * 1000, 2 ); // in ms

						notifybay_log( sprintf( 'Fallback runner processed action #%d (%s) in %s ms', $action_id, $hook, $duration ), 'debug' );
					}
				}
			}
		}
	}

	/**
	 * Get the weight (cost) of a specific Action Scheduler hook.
	 *
	 * @param string $hook The action hook name.
	 * @return int The cost weight.
	 */
	private function get_action_weight( $hook ) {
		/**
		 * Filters the estimated processing cost ("weight") of each Action Scheduler
		 * hook the fallback runner budgets for. NotifyBay Pro registers weights for
		 * the premium hooks it adds (notifybay_run_price_dispatcher,
		 * notifybay_run_hurry_dispatcher) here rather than Free hardcoding entries
		 * for hooks it no longer handles.
		 *
		 * @since 1.0.0
		 * @hook notifybay_fallback_weights
		 * @param array $weights Map of hook name => integer weight.
		 * @return array
		 */
		$weights = apply_filters(
			'notifybay_fallback_weights',
			array(
				'notifybay_run_dispatcher'            => 40,
				'notifybay_send_email_worker'         => 20,
				'notifybay_stale_processing_recovery' => 10,
				'notifybay_verification_cleanup'      => 10,
				'notifybay_expiry_cleanup'            => 10,
			)
		);

		return isset( $weights[ $hook ] ) ? $weights[ $hook ] : 30; // Default fallback weight
	}
}
