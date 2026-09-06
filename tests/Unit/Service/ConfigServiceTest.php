<?php

namespace OCA\FullTextSearch_OpenSearch\Service;

use OCA\FullTextSearch_OpenSearch\ConfigLexicon;
use OCP\AppFramework\Services\IAppConfig;
use PHPUnit\Framework\TestCase;



class ConfigServiceTest extends TestCase
{
    private IAppConfig $configMock;
    private ConfigService $configService;

    protected function setUp(): void
    {
        $this->configMock = $this->createMock(IAppConfig::class);
        $this->configService = new ConfigService($this->configMock);
    }

    public function testCheckConfigValidData(): void
    {
        $validData = [
            ConfigLexicon::OPENSEARCH_HOST => 'https://example.com',
            ConfigLexicon::OPENSEARCH_INDEX => 'valid_index',
            ConfigLexicon::ANALYZER_TOKENIZER => 'standard',
        ];

        $result = $this->configService->checkConfig($validData);

        $this->assertEmpty($result);
    }

    public function testCheckConfigMissingRequiredKeys(): void
    {
        $invalidData = [
            ConfigLexicon::OPENSEARCH_INDEX => 'valid_index',
        ];

        $result = $this->configService->checkConfig($invalidData);

        $this->assertEquals([ConfigLexicon::OPENSEARCH_HOST], $result);
    }

    public function testCheckConfigInvalidHostUrl(): void
    {
        $invalidData = [
            ConfigLexicon::OPENSEARCH_HOST => 'invalid-url',
            ConfigLexicon::OPENSEARCH_INDEX => 'valid_index',
            ConfigLexicon::ANALYZER_TOKENIZER => 'standard',
        ];

        $result = $this->configService->checkConfig($invalidData);

        $this->assertEquals([ConfigLexicon::OPENSEARCH_HOST], $result);
    }

    public function testCheckConfigInvalidHostScheme(): void
    {
        $invalidData = [
            ConfigLexicon::OPENSEARCH_HOST => 'ftp://example.com',
            ConfigLexicon::OPENSEARCH_INDEX => 'valid_index',
            ConfigLexicon::ANALYZER_TOKENIZER => 'standard',
        ];

        $result = $this->configService->checkConfig($invalidData);

        $this->assertEquals([ConfigLexicon::OPENSEARCH_HOST], $result);
    }

    public function testCheckConfigInvalidIndexName(): void
    {
        $invalidData = [
            ConfigLexicon::OPENSEARCH_HOST => 'https://example.com',
            ConfigLexicon::OPENSEARCH_INDEX => '-invalid_index',
            ConfigLexicon::ANALYZER_TOKENIZER => 'standard',
        ];

        $result = $this->configService->checkConfig($invalidData);

        $this->assertEquals([ConfigLexicon::OPENSEARCH_INDEX], $result);
    }

    public function testAdminConfigDoesNotExposeCredentials(): void
    {
        $this->configMock->method('getAppValueInt')->willReturn(10000);
        $this->configMock->method('getAppValueString')->willReturnMap([
            [ConfigLexicon::OPENSEARCH_HOST, 'https://alice:secret@example.com:9200,http://bob:other@second.example:9200'],
            [ConfigLexicon::OPENSEARCH_INDEX, 'nextcloud'],
            [ConfigLexicon::ANALYZER_TOKENIZER, 'standard'],
        ]);
        $this->configMock->method('getAppValueBool')->willReturn(false);

        $result = $this->configService->getAdminConfig();

        $this->assertSame(
            'https://example.com:9200,http://second.example:9200',
            $result[ConfigLexicon::OPENSEARCH_HOST],
        );
        $this->assertStringNotContainsString('secret', json_encode($result));
        $this->assertStringNotContainsString('other', json_encode($result));
    }
}
