<?php
/**
 * D1 §4.9's adapter-facing half -- the class handed to `create_server()` as
 * argument 9, and the only kit class that names the adapter's observability
 * interface.
 *
 * Split out of `Observability` deliberately. A class that `implements` an
 * interface cannot be autoloaded at all unless that interface exists, so
 * folding this into `Observability` made *every* call to `Observability::log()`
 * fatal on a site where the adapter is missing -- including the ones on the
 * degraded path that exist precisely to report the problem. `Manifest::load()`
 * logs invalid abilities, and `AdminApi` registers its routes at Bootstrap
 * step 3, above the step 4 dependency check, so an admin loading the settings
 * panel with the adapter absent hit "Interface ... not found" instead of the
 * admin notice D1 §4.1 requires. Verified as a real fatal, not a theoretical
 * one, before the split.
 *
 * Duck typing is not an alternative: `McpServer::$observability_handler` is a
 * *typed* property (`public McpObservabilityHandlerInterface`), so a handler
 * that merely has the method raises a TypeError on assignment. Checked in both
 * competing adapter copies on the test site (v0.1.0 and v0.5.0) -- identical.
 *
 * Nothing outside `ServerFactory::create()` names this class, and that runs on
 * `mcp_adapter_init`, long past the dependency check -- so on a site with no
 * adapter this file is never loaded and the interface is never needed.
 *
 * @package WPAB\Mcp
 */

declare(strict_types=1);

namespace WPAB\Mcp;

use WP\MCP\Infrastructure\Observability\Contracts\McpObservabilityHandlerInterface;

/**
 * The adapter-facing half of observability.
 */
final class ObservabilityBridge implements McpObservabilityHandlerInterface {

	/**
	 * The adapter decides $event and $tags on its own -- this is a thin bridge
	 * into wc_get_logger(), not the place D1 §4.9's field list (user ID, tool
	 * name, access level, argument digest, outcome) gets assembled. That
	 * happens in Registrar::wrap_execute() via Observability::log_tool_call(),
	 * which controls all of it directly.
	 *
	 * @param string     $event Adapter-supplied event name.
	 * @param array      $tags Adapter-supplied context.
	 * @param float|null $duration_ms Elapsed milliseconds, when the adapter measured it.
	 */
	public function record_event( string $event, array $tags = [], ?float $duration_ms = null ): void {

		$context = $tags;

		if ( null !== $duration_ms ) {
			$context['duration_ms'] = $duration_ms;
		}

		Observability::log( 'info', $event, $context );
	}
}
