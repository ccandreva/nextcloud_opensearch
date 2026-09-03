# Testing the Nextcloud OpenSearch Full Text Search Platform

This document records the development build and runtime testing procedures used during the Nextcloud 34 port.

It is intended both as a repeatable test procedure and as a record of known limitations discovered during testing.

# Reference Test Environment

P0 was tested using:

```text
Operating system: Fedora 44
Web server:       Apache 2.4.68
PHP:              8.5.9
Nextcloud:        34.0.3
Full Text Search: 34.0.1
Files FTS:        34.0.1
Composer:         2.10.2
```

The test Nextcloud installation used during P0 was:

```text
/var/www/cxcloud
```

and the OpenSearch application was installed at:

```text
/var/www/cxcloud/apps/fulltextsearch_opensearch
```

Commands in this document assume the web-server user is `apache`.

# Building the Scoped Vendor Tree

The application contains a scoped copy of its runtime dependencies under:

```text
lib/Vendor
```

This tree is generated using Composer and PHP-Scoper.

A normal root `vendor` tree is a development/build artifact and should not be confused with the application's final scoped runtime dependencies.

## Restore dependencies

From the application directory:

```bash
cd /var/www/cxcloud/apps/fulltextsearch_opensearch
```

For a clean build:

```bash
rm -rf vendor lib/Vendor lib/VendorPsr
composer install --no-scripts
```

The development dependency lock currently contains packages that do not declare compatibility with PHP 8.5.

On the Fedora 44/PHP 8.5 test system it may therefore be necessary to use:

```bash
composer install --no-scripts --ignore-platform-reqs
```

This is currently a development/build workaround. It should not be interpreted as proof that all development dependencies support PHP 8.5.

## Run the vendor build

After restoring the normal Composer dependency tree:

```bash
composer vendor-build-setup
```

The build process:

1. scopes dependencies
2. performs OpenSearch namespace transformations
3. organizes the resulting dependency tree
4. places runtime dependencies under `lib/Vendor`
5. removes source dependency directories
6. regenerates application autoload information

## Important: builds are not currently idempotent

The vendor build consumes and removes portions of the root Composer dependency tree.

Running:

```bash
composer vendor-build-setup
```

a second time without restoring dependencies can fail with errors such as:

```text
The "vendor/opensearch-project" directory does not exist.
```

Before rebuilding, restore the dependency tree with `composer install --no-scripts` as described above.

# Expected Vendor Structure

A successful build should produce directories resembling:

```text
lib/Vendor
lib/Vendor/GuzzleHttp
lib/Vendor/Http
lib/Vendor/Http/Client
lib/Vendor/Http/Discovery
lib/Vendor/Http/Promise
lib/Vendor/OpenSearch
lib/Vendor/OpenSearch/Common
lib/Vendor/OpenSearch/ConnectionPool
lib/Vendor/OpenSearch/Connections
lib/Vendor/OpenSearch/Endpoints
lib/Vendor/OpenSearch/Handlers
lib/Vendor/OpenSearch/Helper
lib/Vendor/OpenSearch/Namespaces
lib/Vendor/OpenSearch/Serializers
lib/Vendor/Psr
lib/Vendor/Psr/Clock
lib/Vendor/Psr/Container
lib/Vendor/Psr/EventDispatcher
lib/Vendor/Psr/Http
lib/Vendor/Psr/Log
lib/Vendor/React
lib/Vendor/React/Promise
```

Check with:

```bash
find lib/Vendor -maxdepth 2 -type d | sort
```

There should not be an accidental sibling tree such as:

```text
lib/VendorPsr
```

That indicates a vendor-organizer path problem.

# Verify OpenSearch Scoping

OpenSearch classes should be scoped into the application namespace.

For example:

```bash
grep -R \
  'namespace OCA\\FullTextSearch_OpenSearch\\Vendor\\OpenSearch' \
  lib/Vendor/OpenSearch | head
```

Expected results resemble:

```text
namespace OCA\FullTextSearch_OpenSearch\Vendor\OpenSearch\ConnectionPool;
namespace OCA\FullTextSearch_OpenSearch\Vendor\OpenSearch\Connections;
...
```

The production installation examined during P0 used the same general `lib/Vendor` layout and scoped OpenSearch namespaces.

# PSR-4 Warnings

The vendor build may currently emit warnings involving classes such as:

```text
Http\Discovery\Composer\Plugin
Psr\Log\LoggerAwareTrait
```

Some namespaces are intentionally excluded from PHP-Scoper prefixing.

In particular, `Psr\Log` is explicitly excluded from the application namespace prefix.

The correct filesystem location is nevertheless:

```text
lib/Vendor/Psr/Log
```

rather than:

```text
lib/VendorPsr/Log
```

During P0 these PSR-4 warnings did not prevent the application from loading or operating.

They should eventually be reviewed as part of build/tooling modernization.

# Development Tool Compatibility

The existing lock file includes Psalm 5.26.1.

That version does not declare support for PHP 8.5, so a normal development Composer installation on PHP 8.5 can fail before all development tools are installed.

As a result, inability to run Psalm or other development tooling on the current test machine should be distinguished from an application runtime failure.

Development dependency modernization is planned separately from the NC34 bootstrap work.

# Nextcloud Preparation

From the Nextcloud root:

```bash
cd /var/www/cxcloud
```

Verify status:

```bash
sudo -u apache php occ status
```

A ready test system should report:

```text
installed: true
versionstring: 34.0.3
maintenance: false
needsDbUpgrade: false
```

# Avoid Multiple Search Platform Backends During Testing

During P0, the maintained Elasticsearch application was already installed.

To avoid ambiguity while debugging the OpenSearch platform, it was disabled:

```bash
sudo -u apache php occ app:disable fulltextsearch_elasticsearch
```

This is a test-environment precaution rather than a general Nextcloud installation requirement.

# Enable the OpenSearch Application

Enable the application:

```bash
sudo -u apache php occ app:enable fulltextsearch_opensearch
```

Verify:

```bash
sudo -u apache php occ app:list
```

For the NC34 development port, expect:

```text
fulltextsearch_opensearch: 34.0.0-dev.0
```

# Verify Command Registration

Run:

```bash
sudo -u apache php occ list | grep -Ei 'fulltextsearch|opensearch'
```

The OpenSearch-specific command should include:

```text
fulltextsearch_opensearch
  fulltextsearch_opensearch:configure
```

The Full Text Search core commands should include commands such as:

```text
fulltextsearch:check
fulltextsearch:configure
fulltextsearch:index
fulltextsearch:live
fulltextsearch:reset
fulltextsearch:search
fulltextsearch:test
```

Successful command registration is a useful bootstrap test because it exercises application discovery, bootstrap, dependency injection, and command registration.

# Test OpenSearch Configuration

Run the OpenSearch configure command with no arguments:

```bash
sudo -u apache php occ fulltextsearch_opensearch:configure
```

On a fresh configuration, the expected defaults are:

```json
{
    "fields_limit": 10000,
    "opensearch_host": "",
    "opensearch_index": "",
    "opensearch_logger_enabled": true,
    "analyzer_tokenizer": "standard",
    "allow_self_signed_cert": false
}
```

This verifies the read path through `ConfigLexicon` and `IAppConfig`. When the configured host contains credentials, verify that the command prints the username but replaces the password with `********`.

# Configure the Full Text Search Platform

Full Text Search should use:

```text
OCA\FullTextSearch_OpenSearch\Platform\OpenSearchPlatform
```

The corresponding Full Text Search configuration value is:

```json
{
    "search_platform": "OCA\\FullTextSearch_OpenSearch\\Platform\\OpenSearchPlatform"
}
```

Use the normal Full Text Search configuration mechanism for the installed version to set this value.

# Configure OpenSearch

Configure the OpenSearch host and index using:

```bash
sudo -u apache php occ fulltextsearch_opensearch:configure \
'{"opensearch_host":"http://HOST:9200","opensearch_index":"INDEX"}'
```

Use credentials/TLS settings appropriate for the test OpenSearch installation.

Do not record real credentials in this document or commit them to the repository.

Verify the resulting configuration with:

```bash
sudo -u apache php occ fulltextsearch_opensearch:configure
```

# Functional Platform Test

The principal integration test is provided by the Full Text Search core:

```bash
sudo -u apache php occ fulltextsearch:test
```

Note that this is:

```text
fulltextsearch:test
```

not:

```text
fulltextsearch_opensearch:test
```

The test command belongs to the Full Text Search core and exercises whichever platform is currently configured.

# P0 Functional Test Results

During the NC34 P0 test, OpenSearch successfully completed:

```text
Creating mocked content provider. ok
Testing mocked provider: get indexable documents. ok
Loading search platform. (OpenSearch) ok
Testing search platform. ok
Locking process ok
Removing test. ok
Initializing index mapping. ok
Indexing generated documents. ok
Retreiving content from a big index (license). ok
Comparing document with source. ok
```

Basic search tests also passed, including:

```text
'test'
'document is a simple test'
'"document is a test"'
'"document is a simple test"'
'document is a simple -test'
'document is a simple +test'
'document is a simple +test +testing'
'document is a simple +test -testing'
```

This establishes that the OpenSearch client, indexing path, retrieval path, and substantial portions of query generation remain operational on Nextcloud 34.

# Known Functional Test Failure: Group Access

The test currently fails during group access testing:

```text
Searching with group access rights:
 - 'license' - [] -  (result: 0, expected: []) ok
 - 'license' - ["group_1"] -  (result: 1, expected: ["license"]) ok
 - 'license' - ["group_1","Group_2"] -  (result: 1, expected: ["license"]) ok
 - 'license' - ["group_3","Group_2"] -  (result: 0, expected: ["license"]) fail
```

This is tracked as GitHub issue #3.

The same behavior was previously reported against an older version of the OpenSearch application and therefore should not be treated as a regression introduced by the Nextcloud 34 P0 port.

# Known Functional Test Failure: Missing Index

There is currently a second limitation when using a completely new index name.

If the configured OpenSearch index does not exist at all:

```bash
sudo -u apache php occ fulltextsearch:test
```

can fail at:

```text
Removing test. fail
```

with:

```text
index_not_found_exception
```

This occurs because the Full Text Search test removes stale test documents before it asks the platform to initialize its index.

This behavior is tracked as GitHub issue #2.

Until that issue is fixed, creating an empty index manually allows the test to progress, but doing so has an important side effect described below.

# Mapping Caveat When Manually Creating an Index

If a blank OpenSearch index is manually created before running the test, the plugin sees that the index already exists and does not execute its normal index-creation mapping.

When documents are subsequently indexed, OpenSearch dynamically generates mappings.

During P0 this produced fields such as:

```json
"groups": {
    "type": "text",
    "fields": {
        "keyword": {
            "type": "keyword",
            "ignore_above": 256
        }
    }
}
```

Similar dynamic mappings were observed for:

```text
owner
users
groups
content
hash
provider
source
title
```

This is not necessarily the mapping the OpenSearch application intends to create.

Therefore, do not use a manually created blank index to draw conclusions about the correctness of `IndexMappingService`.

After issue #2 is resolved, mapping tests should begin with a nonexistent disposable index and allow the plugin itself to create it.

# Inspecting an Index Mapping

For a disposable test index:

```bash
curl -s 'http://HOST:9200/INDEX/_mapping?pretty'
```

Do not use destructive index operations against a production index merely to test mapping initialization.

# Disposable Test Indexes

Use a clearly disposable index name when testing index creation and mappings, for example:

```text
nextcloud34-test
```

Do not delete or reset an existing production index as part of development testing.

# OpenSearch Request Logging

Enabling:

```text
opensearch_logger_enabled
```

allows OpenSearch client requests to appear in the Nextcloud log.

This can be useful for determining whether an operation actually occurred.

For example, during one P0 investigation a clean request log showed:

```text
HEAD /nextcloud34-test
POST /nextcloud34-test/_doc/...
```

but no index-creation `PUT`.

The index had been manually created beforehand, so `IndexService::initializeIndex()` correctly detected that it existed and skipped creation.

When investigating index lifecycle behavior, distinguish carefully between:

* an index created by the plugin
* an index created manually
* an index dynamically created by OpenSearch during document indexing

# Interpreting Test Failures

A failing `fulltextsearch:test` does not automatically mean the current porting phase failed.

When a failure is encountered:

1. identify the exact test stage
2. determine whether the platform successfully loaded
3. determine whether the failure reproduces on an older OpenSearch version if possible
4. compare the relevant implementation with `fulltextsearch_elasticsearch:stable34`
5. determine whether the current phase changed the affected code
6. create or update a GitHub issue for an unrelated/pre-existing failure

Do not expand the current porting phase automatically to fix every discovered problem.

# P0 Acceptance Criteria

For the Nextcloud 34 P0 phase, the important runtime criteria were:

* Nextcloud accepts the application metadata
* application enables successfully
* application bootstrap succeeds
* dependency injection succeeds
* ConfigLexicon registers successfully
* configuration can be read
* configuration can be written
* OpenSearch platform can be selected
* OpenSearch platform instantiates
* OpenSearch connection succeeds
* the existing indexing/search implementation remains substantially functional

These criteria have been met.

Known functional issues are tracked separately and do not prevent P0 from being considered complete.

# P1 Provisioning and Error-Handling Tests

Use the explicit provisioning command when validating a missing index:

```bash
sudo -u apache php occ fulltextsearch_opensearch:initialize
```

Verify both lifecycle paths:

1. With a missing index and provisioning credentials, the command creates the index, explicit mappings, and the `attachment` pipeline.
2. With an existing index and existing attachment pipeline, the command succeeds without modifying either resource.
3. With an existing index and missing attachment pipeline, the command leaves the index and mappings untouched and creates only the pipeline.
4. After intentionally denying pipeline creation, rerun with sufficient permission and verify that initialization recovers by creating only the missing pipeline.
5. If index creation is denied, the command identifies the index/mapping stage and prints the OpenSearch error.
6. If pipeline creation is denied or invalid, the command identifies the attachment-pipeline stage and prints the OpenSearch error.
7. With all OpenSearch nodes unavailable during document indexing, the platform reports a temporary platform failure.
8. For permanent document or deletion errors, the runner retains the actionable OpenSearch reason instead of replacing it with a generic message.

After successful provisioning, inspect the `attachment` pipeline and confirm its processors are ordered as attachment extraction, content conversion, and binary removal.

`fulltextsearch:test` writes and removes synthetic documents and requires delete-by-query permission. It is a functional validation command, not the recommended provisioning command for a restricted account.

See `docs/ADMINISTRATION.md` for the provisioning workflow and permission boundaries.

# Future P1 Testing

P1 should add targeted verification around:

* index creation from a nonexistent index
* explicit mappings generated by `IndexMappingService`
* index reset/delete behavior
* access-control mappings
* group access
* user access
* circle access
* share/link access
* case handling
* search query generation
* document updates
* behavior corresponding to changes in `fulltextsearch_elasticsearch:stable34`

Issues #2 and #3 should be explicitly retested as relevant P1 changes are introduced.

A fix should not be considered complete solely because code was changed. The corresponding runtime reproduction should pass before the issue is closed.
