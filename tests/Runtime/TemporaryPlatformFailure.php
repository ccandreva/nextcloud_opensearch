<?php

declare(strict_types=1);

/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Run as the Nextcloud OCC account. NEXTCLOUD_ROOT may override the installation
 * root. Uses the installed Full Text Search runner contract, not development OCP
 * stubs. Both transport failures are simulated; no backend/state writes occur.
 */

define('OC_CONSOLE', true);
require (getenv('NEXTCLOUD_ROOT') ?: dirname(__DIR__, 4)) . '/lib/base.php';

use OC\FullTextSearch\Model\DocumentAccess;
use OC\FullTextSearch\Model\IndexDocument;
use OCA\FullTextSearch\Exceptions\PlatformTemporaryException;
use OCA\FullTextSearch\Model\Index;
use OCA\FullTextSearch\Service\IndexService as CoreIndexService;
use OCA\FullTextSearch_OpenSearch\Platform\OpenSearchPlatform;
use OCA\FullTextSearch_OpenSearch\Vendor\OpenSearch\Client;
use OCA\FullTextSearch_OpenSearch\Vendor\OpenSearch\Common\Exceptions\BadRequest400Exception;
use OCA\FullTextSearch_OpenSearch\Vendor\OpenSearch\Common\Exceptions\NoNodesAvailableException;
use OCP\FullTextSearch\Model\IIndex;
use OCP\Server;

final class UnavailableIndexingClient extends Client {

	public int $calls = 0;

	public function __construct(private bool $failDuringFallback) {
		// Deliberately do not construct a transport or contact any server.
	}

	public function index(array $params = []) {
		$this->calls++;
		if ($this->failDuringFallback && $this->calls === 1) {
			throw new BadRequest400Exception(
				'{"error":{"type":"mapper_parsing_exception","reason":"synthetic rejection"},"status":400}'
			);
		}

		throw new NoNodesAvailableException('simulated unavailable node');
	}
}

$failed = false;
foreach ([false, true] as $fallback) {
	$label = $fallback ? 'contentless fallback' : 'initial indexing';
	$client = new UnavailableIndexingClient($fallback);
	$platform = Server::get(OpenSearchPlatform::class);
	(new ReflectionProperty($platform, 'client'))->setValue($platform, $client);
	$index = new Index('files', 'temporary-failure-no-write');
	$index->setStatus(IIndex::INDEX_FULL);
	$document = new IndexDocument('files', 'temporary-failure-no-write');
	$document->setIndex($index);
	$document->setAccess(new DocumentAccess('temporary-failure-test'));
	$document->setContent('Synthetic transport failure test');

	try {
		// Core must propagate the temporary failure, rather than swallowing or
		// translating it into a missing-document error and continuing indexing.
		Server::get(CoreIndexService::class)->indexDocument($platform, $document);
		throw new RuntimeException('Expected a temporary platform failure');
	} catch (PlatformTemporaryException $e) {
		if ($client->calls !== ($fallback ? 2 : 1)) {
			$failed = true;
			echo "FAIL: $label used an unexpected number of requests\n";
		} else {
			echo "PASS: $label propagates the installed runner's temporary failure\n";
		}
	} catch (Throwable $e) {
		$failed = true;
		echo "FAIL: $label raised " . get_class($e) . "\n";
	}
}

exit($failed ? 1 : 0);
