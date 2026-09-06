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


namespace OCA\FullTextSearch_OpenSearch\Service;

use OCA\FullTextSearch_OpenSearch\ConfigLexicon;
use OCA\FullTextSearch_OpenSearch\Exceptions\ConfigurationException;
use OCP\AppFramework\Services\IAppConfig;

class ConfigService {
	public function __construct(
		private IAppConfig $appConfig,
	) {
	}

	public function getConfig(): array {
		return [
			ConfigLexicon::FIELDS_LIMIT => $this->appConfig->getAppValueInt(ConfigLexicon::FIELDS_LIMIT),
			ConfigLexicon::OPENSEARCH_HOST => $this->appConfig->getAppValueString(ConfigLexicon::OPENSEARCH_HOST),
			ConfigLexicon::OPENSEARCH_INDEX => $this->appConfig->getAppValueString(ConfigLexicon::OPENSEARCH_INDEX),
			ConfigLexicon::OPENSEARCH_LOGGER_ENABLED => $this->appConfig->getAppValueBool(ConfigLexicon::OPENSEARCH_LOGGER_ENABLED),
			ConfigLexicon::ANALYZER_TOKENIZER => $this->appConfig->getAppValueString(ConfigLexicon::ANALYZER_TOKENIZER),
			ConfigLexicon::ALLOW_SELF_SIGNED_CERT => $this->appConfig->getAppValueBool(ConfigLexicon::ALLOW_SELF_SIGNED_CERT),
		];
	}

	public function setConfig(array $save): void {
		if (array_key_exists(ConfigLexicon::FIELDS_LIMIT, $save)) {
			$this->appConfig->setAppValueInt(ConfigLexicon::FIELDS_LIMIT, (int)$save[ConfigLexicon::FIELDS_LIMIT]);
		}
		if (array_key_exists(ConfigLexicon::OPENSEARCH_HOST, $save)) {
			$this->appConfig->setAppValueString(ConfigLexicon::OPENSEARCH_HOST, (string)$save[ConfigLexicon::OPENSEARCH_HOST]);
		}
		if (array_key_exists(ConfigLexicon::OPENSEARCH_INDEX, $save)) {
			$this->appConfig->setAppValueString(ConfigLexicon::OPENSEARCH_INDEX, (string)$save[ConfigLexicon::OPENSEARCH_INDEX]);
		}
		if (array_key_exists(ConfigLexicon::OPENSEARCH_LOGGER_ENABLED, $save)) {
			$this->appConfig->setAppValueBool(
				ConfigLexicon::OPENSEARCH_LOGGER_ENABLED,
				$this->toBool($save[ConfigLexicon::OPENSEARCH_LOGGER_ENABLED]),
			);
		}
		if (array_key_exists(ConfigLexicon::ANALYZER_TOKENIZER, $save)) {
			$this->appConfig->setAppValueString(ConfigLexicon::ANALYZER_TOKENIZER, (string)$save[ConfigLexicon::ANALYZER_TOKENIZER]);
		}
		if (array_key_exists(ConfigLexicon::ALLOW_SELF_SIGNED_CERT, $save)) {
			$this->appConfig->setAppValueBool(
				ConfigLexicon::ALLOW_SELF_SIGNED_CERT,
				$this->toBool($save[ConfigLexicon::ALLOW_SELF_SIGNED_CERT]),
			);
		}
	}

	/** @throws ConfigurationException */
	public function getOpenSearchHost(): array {
		$host = $this->appConfig->getAppValueString(ConfigLexicon::OPENSEARCH_HOST);
		if ($host === '') {
			throw new ConfigurationException('Your OpenSearchPlatform is not configured properly');
		}

		return array_map('trim', explode(',', $host));
	}

	/** @throws ConfigurationException */
	public function getOpenSearchIndex(): string {
		$index = $this->appConfig->getAppValueString(ConfigLexicon::OPENSEARCH_INDEX);
		if ($index === '') {
			throw new ConfigurationException('Your OpenSearchPlatform is not configured properly');
		}

		return $index;
	}

	public function checkConfig(array $data): array {
		$errors = [];
		if (!$this->isValidHost($data[ConfigLexicon::OPENSEARCH_HOST] ?? null)) {
			$errors[] = ConfigLexicon::OPENSEARCH_HOST;
		}
		if (!$this->isValidIndex($data[ConfigLexicon::OPENSEARCH_INDEX] ?? null)) {
			$errors[] = ConfigLexicon::OPENSEARCH_INDEX;
		}

		return $errors;
	}

	private function isValidHost(?string $host): bool {
		return $host !== null
			&& filter_var($host, FILTER_VALIDATE_URL) !== false
			&& in_array(parse_url($host, PHP_URL_SCHEME), ['http', 'https'], true);
	}

	private function isValidIndex(?string $index): bool {
		return $index !== null
			&& preg_match('/^(?![-_])[a-z0-9-_]{1,255}(?<![-_])$/', $index) === 1;
	}

	private function toBool(mixed $value): bool {
		return filter_var($value, FILTER_VALIDATE_BOOL);
	}
}
