<?php

declare(strict_types=1);

/**
 * FullTextSearch_OpenSearch - Use OpenSearch to index the content of your nextcloud
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@artificial-owl.com>
 * @copyright 2018
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\FullTextSearch_OpenSearch\Command;

use Exception;
use OC\Core\Command\Base;
use OCA\FullTextSearch_OpenSearch\Platform\OpenSearchPlatform;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Initialize extends Base {

	public function __construct(
		private OpenSearchPlatform $platform,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		parent::configure();
		$this->setName('fulltextsearch_opensearch:initialize')
			->setDescription('Create a missing OpenSearch index, mappings, and attachment pipeline');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$output->write('Loading OpenSearch platform... ');
		try {
			$this->platform->loadPlatform();
			$output->writeln('<info>ok</info>');
		} catch (Exception $e) {
			$output->writeln('<error>fail</error>');
			$output->writeln($e->getMessage(), OutputInterface::OUTPUT_RAW);

			return self::FAILURE;
		}

		$activeStage = null;
		$labels = [
			'checkOpenSearchIndex' => 'Checking configured index... ',
			'createOpenSearchIndex' => 'Creating index settings and mappings... ',
			'createOpenSearchAttachmentPipeline' => 'Creating attachment pipeline... ',
		];

		try {
			$created = $this->platform->provisionIndex(
				function (string $stage) use ($output, $labels, &$activeStage): void {
					if ($activeStage !== null) {
						$output->writeln('<info>ok</info>');
					}
					$output->write($labels[$stage] ?? ($stage . '... '));
					$activeStage = $stage;
				}
			);

			if (!$created) {
				if ($activeStage !== null) {
					$output->writeln('<info>ok</info>');
				}
				$output->writeln('<comment>The configured index already exists and was not modified.</comment>');

				return self::SUCCESS;
			}

			if ($activeStage !== null) {
				$output->writeln('<info>ok</info>');
			}

			return self::SUCCESS;
		} catch (Exception $e) {
			if ($activeStage !== null) {
				$output->writeln('<error>fail</error>');
			}
			$output->writeln($e->getMessage(), OutputInterface::OUTPUT_RAW);

			return self::FAILURE;
		}
	}
}
