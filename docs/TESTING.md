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

# Administrator Settings Browser Test

Automated tests do not exercise Nextcloud's administration-page composition,
Content Security Policy, or browser request handling. Perform this acceptance
test on the supported Nextcloud 34 installation after deploying the application:

1. Record the output of `sudo -u apache php occ fulltextsearch_opensearch:configure` and confirm that any host password is masked. Do not copy credentials into test reports.
2. Sign in as an administrator and open **Administration settings → Full Text Search**. Confirm that the OpenSearch section renders and is not left as an empty mount point.
3. In browser developer tools, confirm that the console has no JavaScript errors. Inspect the page source and initial-state data and confirm that neither the OpenSearch username nor password is present.
4. Inspect the Network panel during page load. The initial configuration should be supplied with the page; there should be no legacy `GET /apps/fulltextsearch_opensearch/admin/settings` request. Confirm that no response contains OpenSearch credentials.
5. Compare every displayed value with the no-argument OCC output. The host field should retain the configured scheme, server, port, and path while omitting its user information. The index, fields limit, tokenizer, logging, and self-signed-certificate values should match.
6. Change a non-host setting and select **Save**. Confirm that the POST to `/apps/fulltextsearch_opensearch/admin/settings` succeeds, its response contains no credentials, and `nextcloud.log` contains no PHP error or credential-bearing message. Reload and confirm that the value persisted and the credentialed host still works through OCC.
7. Replace the host value with a complete test URL (including test-only credentials when authentication is required), save, and reload. Confirm that the endpoint remains visible without user information and that OCC reports the new URL with only its password masked.
8. Enter an invalid host scheme or invalid index name and save. Confirm that the request returns HTTP 400, the relevant field is highlighted, useful validation text is shown, no invalid value is persisted, and `nextcloud.log` has no unexpected exception or credential disclosure.
9. Restore the original test configuration. Compare the browser values once more with `sudo -u apache php occ fulltextsearch_opensearch:configure` to verify that GUI and OCC use the same underlying configuration.

Verify the resulting configuration with:

```bash
sudo -u apache php occ fulltextsearch_opensearch:configure
```

# Synthetic Platform Contract Test

The Full Text Search core provides a synthetic backend contract test:

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

The test command belongs to the Full Text Search core and exercises whichever platform is currently configured. It uses mocked documents; follow the real-provider acceptance procedure below to verify actual Files indexing and user-facing search.

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

# Historical P0 Failure: Group Access

The P0 test failed during group access testing:

```text
Searching with group access rights:
 - 'license' - [] -  (result: 0, expected: []) ok
 - 'license' - ["group_1"] -  (result: 1, expected: ["license"]) ok
 - 'license' - ["group_1","Group_2"] -  (result: 1, expected: ["license"]) ok
 - 'license' - ["group_3","Group_2"] -  (result: 0, expected: ["license"]) fail
```

This is tracked as GitHub issue #3.

The same behavior was previously reported against an older version of the OpenSearch application and therefore should not be treated as a regression introduced by the Nextcloud 34 P0 port.

# Historical P0 Failure: Missing Index

P0 had a second limitation when using a completely new index name.

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

The historical workaround was to create an empty index manually, but that has the mapping side effect described below. Current code provides `fulltextsearch_opensearch:initialize`; use it to create explicit mappings instead. The issue #15 procedure explains the separate accommodation required to test immutable P0 safely.

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

# Real-provider end-to-end acceptance (#15)

`fulltextsearch:test` validates the synthetic backend contract. It does **not**
validate Files enumeration, real attachment extraction, population of a replacement
index, the browser search path, or permissions on real files. Accept a release only
when the applicable layers below have evidence.

## Establish the comparison and protect reference data

1. Read the porting and administration guides. Record `git status --short`, branch,
   full commit SHA, and actual Nextcloud, PHP, provider, backend, client, and server
   versions. Use `git log --graph --decorate --oneline --all` and commit contents to
   identify an immutable baseline; do not depend on old branch names.
2. Record the selected platform and index and securely retain the configuration
   needed to restore them. Never print an old revision's unmasked configure output.
   Current browser-safe configuration removes all host user information. Do not
   record credentials, personal filenames/content, or raw request logs in reports.
3. Check connection, index-read, and pipeline-read permissions before resetting
   anything. Root server version responses can be compatibility values; distinguish
   them from a verified node version. A denied node-info query is not proof of a
   particular OpenSearch version.
4. Use distinct fresh indexes, such as `nextcloud34-ab-p0-issue15` and
   `nextcloud34-ab-current-issue15`. Record their creation and verify nonexistence
   first. Never reset/delete an existing reference index. Keep files, accounts,
   shares, provider settings, and runtime dependencies constant between runs.
5. Prevent competing indexing runs; use the core runner's lock. Record any site
   changes during the comparison. Each CLI invocation loads the checked-out PHP
   afresh; account for web OPcache separately before browser acceptance.

## Understand and prepare indexing state

Files generates chunks per user, enumerates documents under those chunks, then
fills content and access metadata. Enumeration honors `.noindex`, storage/provider
settings, and indexability rules. Offers across users/chunks need not be unique.
The core runner consults `fulltextsearch_index`, keyed by provider/document and
collection, **not by OpenSearch index name**. It skips ignored documents, documents
with retained errors (unless requested otherwise), and documents considered
up-to-date. Merely changing `opensearch_index` does not clear this state.

The `force` option does not override the earlier ignored/error checks. An ordinary
`fulltextsearch:index` invocation is therefore not proof of a full rebuild.
For a clean A/B rebuild, select and provision the disposable target first, then
reset only the Files provider:

```bash
sudo -u apache php occ fulltextsearch_opensearch:configure \
  '{"opensearch_index":"nextcloud34-ab-current-issue15"}'
sudo -u apache php occ fulltextsearch_opensearch:initialize
sudo -u apache php occ fulltextsearch:reset --provider files
# Interactive confirmations: y, then reset files ALL
sudo -u apache php occ fulltextsearch:index --no-readline '{"provider":"files"}'
```

Verify the active disposable index immediately before reset. This reset removes
Files documents from the selected backend and deletes the shared Files indexing
state in Nextcloud. It affects subsequent incremental indexing even if the old
backend index remains untouched. Record this operational effect and arrange a
consistent final active index/state. Do not reset unrelated providers.

Do **not** use an unscoped `fulltextsearch:reset`: the backend's full reset deletes
both the configured index and the global `attachment` pipeline. The pipeline is
shared, not a disposable per-index resource.

P0 lacks the explicit initialize command. Its initializer can overwrite the shared
pipeline, and its failure recovery can delete that pipeline and the target index.
For a non-destructive P0 indexing comparison with an existing pipeline, create the
fresh target using P0's `IndexMappingService::generateGlobalMap()` through the
configured client. Verify the existing attachment pipeline before and after using
a digest. P0 then sees the index and skips provisioning. This tests P0 indexing
against the same existing pipeline; it does **not** validate P0's historical
pipeline-creation behavior. Do not create a blank dynamically mapped index.

For that historical comparison only, after selecting the disposable P0 index,
save the following as a temporary PHP script and run it as the OCC account. Adjust
the Nextcloud root and target to the recorded test environment. This uses internal
APIs and is not a replacement for the current supported initialize command:

```php
<?php
define('OC_CONSOLE', true);
require '/var/www/cxcloud/lib/base.php';
$target = 'nextcloud34-ab-p0-issue15';
$config = \OCP\Server::get(\OCA\FullTextSearch_OpenSearch\Service\ConfigService::class);
if ($config->getOpenSearchIndex() !== $target) {
    throw new \RuntimeException('Unexpected configured index');
}
$platform = \OCP\Server::get(\OCA\FullTextSearch_OpenSearch\Platform\OpenSearchPlatform::class);
$platform->loadPlatform();
$client = (new \ReflectionMethod($platform, 'getClient'))->invoke($platform);
if ($client->indices()->exists(['index' => $target])) {
    throw new \RuntimeException('Target already exists; do not reuse it');
}
$pipeline = $client->ingest()->getPipeline(['id' => 'attachment']);
if (!isset($pipeline['attachment'])) {
    throw new \RuntimeException('Existing shared pipeline required');
}
echo 'pipeline_sha256=' . hash('sha256', json_encode($pipeline)) . PHP_EOL;
$mapping = \OCP\Server::get(\OCA\FullTextSearch_OpenSearch\Service\IndexMappingService::class);
$client->indices()->create($mapping->generateGlobalMap());
```

## Measure the whole path

Run the focused transport regression check on the configured Nextcloud test
installation as the OCC account:

```bash
sudo -u apache php apps/fulltextsearch_opensearch/tests/Runtime/TemporaryPlatformFailure.php
```

If the checkout is outside the installation's `apps` directory, set
`NEXTCLOUD_ROOT` to the installation root for the PHP process. The check uses the
installed core's exception-catching behavior and a fake OpenSearch client. It
tests initial-request and contentless-fallback node exhaustion without changing
cluster availability or writing documents/indexing state. Both cases must pass.
Development OCP stubs alone can conceal a namespace mismatch with the installed
Nextcloud/Full Text Search combination.

A real temporary platform failure may interrupt a full run. Record the failure,
exit, runner-lock state, and retry commands; do not silently count an interrupted
run as complete. If PHP terminated without clearing its lock, verify that the
test process is no longer running before using `fulltextsearch:stop`. That command
stops all active indexing, so verify that no unrelated runner is active first.

Record per code state:

- Exact SHA, versions, selected index, reset/provisioning/indexing commands, exit
  status, and whether normal command execution or a measurement wrapper was used.
- Users/chunks traversed; document offers and distinct provider/document IDs;
  documents selected after state filtering; documents filled and submitted;
  success, warning/fallback, failure, and skip counts where observable. Mark any
  unmeasured count explicitly. Separate repeat offers from actual overwrites.
- Runner error/result callbacks and time-bounded Nextcloud log observations.
  A zero command exit status is insufficient: the core command catches some
  per-user exceptions, and the core service catches some per-document exceptions.
  The runner's stored `totalDocuments` is not a reliable count in core 34.0.1
  (`RunningService::stop()` writes the constant 42).
- After indexing, refresh the disposable index, query `_count`, and count the
  `files` provider separately if any other documents exist. Compare distinct IDs
  as well as totals. Retain only redacted aggregates in committed evidence.

The OpenSearch backend submits individual requests with IDs
`<provider>:<document-id>`. Encoded nonempty content invokes `attachment` ingest.
A failed content request may be retried without content: warning results and
nonempty-content checks matter even when final document counts look correct.
Temporary platform failures should abort/retry; record permanent rejection reasons
and provider errors without exposing source documents.

If only a small subset is submitted in both revisions, investigate provider/state
selection first. If thousands are submitted but only a small subset persists,
inspect rejection, fallback, permission, pipeline, mapping, and ID evidence. If
only current code fails under equivalent conditions, isolate the changed range.
An old Elasticsearch count is a clue, not the expected number: compare current
provider IDs and stale/deleted records when practical without changing that index.

## Validate document shape and content

Inspect ordinary text, extracted PDF/office content, and a shared/group document.
Check `_id`, `provider`, `title`, nonempty expected `content`, `owner`, `users`,
`groups`, `circles`, `links`, and `lastModified` where supported. P0 predates
`lastModified`; record that expected difference. Empty access arrays are valid for
unshared files; verify actual entries against the file's real sharing state.
Record field presence/types, content lengths, and anonymized sample labels, not
personal contents. Count and inspect contentless fallback documents separately.

## Browser search and access-control acceptance

With the populated current index selected and current PHP active, use Nextcloud's
normal **Full Text Search** results, not merely the filename-only Files provider:

1. Search a known filename, a word inside a plain-text file, and a word inside a
   PDF/office file. Repeat a content query with different case.
2. Verify expected titles, useful excerpts/highlights, and links opening the
   correct object. Record the request/result and relevant console/network/log
   errors without recording cookies or credentials.
3. As the intended user, verify an owned/visible document and a shared/group-visible
   document appear. Verify a known inaccessible document does not appear; also
   verify that document is indexed/searchable by its authorized owner so that
   absence cannot be explained by failed indexing.
4. Keep existing permissions unchanged. If controlled fixtures/accounts are needed,
   identify them as disposable and record their creation, exact visibility, and
   cleanup. Do not weaken real-user permissions.
5. If browser tooling cannot reach the existing session, finish server-side checks
   and request only these remaining interactions from the operator. Record the
   operator's exact observations; do not label unobserved checks as passes.

## Completion and cleanup

Keep synthetic, provider, population, GUI, and ACL results separate. A populated
index or passing synthetic test cannot stand in for GUI/ACL acceptance. Document
remaining hypotheses and missing evidence. Restore the original configuration or
explicitly record the agreed test configuration left active; keep database state
consistent with it. Only delete indexes whose task creation was verified, and
never delete the shared pipeline as index cleanup. Leave the issue branch for
independent review; every issue-specific commit must reference #15.
# Nextcloud 34 administration settings acceptance (#21)

Browser acceptance remains manual. Use disposable values and redact credentials,
cookies, request tokens, and personal data from any recorded evidence.

1. Select Elasticsearch, reload the administration page, and verify its settings
   are visible while OpenSearch settings are hidden. Select OpenSearch, reload,
   and verify the reverse.
2. Without reloading, switch Elasticsearch → OpenSearch → Elasticsearch. Verify
   the two settings sections alternate visibility at each step. Select OpenSearch
   again for the remaining checks.
3. Change the main Full Text Search Navigation Icon setting. In Network, inspect
   `POST /apps/fulltextsearch/admin/settings`: request data should contain
   `app_navigation` and the selected `search_platform` class name. Record the
   HTTP status and response; reload to verify persistence. If it fails, record
   the console error, URL, status, sanitized request/response, and matching
   `nextcloud.log` entry. Check the platform dropdown by the same method.
4. Change each OpenSearch field in turn: host, index, fields limit, analyzer
   tokenizer (leave each field), logging, and self-signed TLS (toggle each).
   Verify a `POST /apps/fulltextsearch_opensearch/admin/settings` containing
   only the changed key, visible success feedback, and persistence after reload.
   There should be no general OpenSearch Save button.
5. On a disposable host, configure a credential-bearing URL through OCC. Reload
   and verify user information is absent from the host field, rendered initial
   state, GET/POST responses, and console. Change only a non-host field, then
   inspect the stored host with the OCC configuration command and verify
   connectivity. It must retain the credentials. Edit the host and restore its
   original displayed value before leaving the field; verify no host POST and
   no credential loss. Finally replace it intentionally and verify the new host
   persists. Never commit real credentials.
6. Enter an invalid disposable host or index, leave the field, and verify useful
   validation feedback and that stored valid configuration remains intact.
   Check that console output and server logs do not expose credentials.
