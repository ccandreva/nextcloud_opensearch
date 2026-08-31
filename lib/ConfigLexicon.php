<?php

declare(strict_types=1);

namespace OCA\FullTextSearch_OpenSearch;

use OCP\Config\Lexicon\Entry;
use OCP\Config\Lexicon\ILexicon;
use OCP\Config\Lexicon\Strictness;
use OCP\Config\ValueType;

class ConfigLexicon implements ILexicon {
	public const FIELDS_LIMIT = 'fields_limit';
	public const OPENSEARCH_HOST = 'opensearch_host';
	public const OPENSEARCH_INDEX = 'opensearch_index';
	public const OPENSEARCH_LOGGER_ENABLED = 'opensearch_logger_enabled';
	public const ANALYZER_TOKENIZER = 'analyzer_tokenizer';
	public const ALLOW_SELF_SIGNED_CERT = 'allow_self_signed_cert';

	public function getStrictness(): Strictness {
		return Strictness::NOTICE;
	}

	public function getAppConfigs(): array {
		return [
			new Entry(self::FIELDS_LIMIT, ValueType::INT, 10000),
			new Entry(self::OPENSEARCH_HOST, ValueType::STRING, ''),
			new Entry(self::OPENSEARCH_INDEX, ValueType::STRING, ''),
			new Entry(self::OPENSEARCH_LOGGER_ENABLED, ValueType::BOOL, true),
			new Entry(self::ANALYZER_TOKENIZER, ValueType::STRING, 'standard'),
			new Entry(self::ALLOW_SELF_SIGNED_CERT, ValueType::BOOL, false),
		];
	}

	public function getUserConfigs(): array {
		return [];
	}
}
