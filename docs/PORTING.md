# Nextcloud OpenSearch Porting Guide

## Purpose

This repository maintains an OpenSearch search platform for Nextcloud Full Text Search.

The project was originally forked from Nextcloud's maintained Elasticsearch platform:

`nextcloud/fulltextsearch_elasticsearch`

The OpenSearch fork diverged from the Elasticsearch implementation in order to use the OpenSearch PHP client and OpenSearch-specific configuration and behavior. Development of the parent OpenSearch project subsequently slowed or stopped, while the Nextcloud Elasticsearch application continued to evolve with new Nextcloud releases.

The goal of this project is to bring the OpenSearch application forward to current Nextcloud releases while preserving OpenSearch as the backend.

This is not intended to become an Elasticsearch compatibility layer. Changes from `fulltextsearch_elasticsearch` should be evaluated individually and adapted where appropriate.

## Upstream Reference

The primary reference implementation is:

`nextcloud/fulltextsearch_elasticsearch`

For a Nextcloud release port, compare against the corresponding stable branch rather than `master`.

For the Nextcloud 34 port:

`nextcloud/fulltextsearch_elasticsearch:stable34`

The OpenSearch fork and Elasticsearch upstream share a historical common ancestor:

`9138dd7129c85f67d8794abff79276e05f3b6b97`

As of the initial porting analysis, the OpenSearch fork had 12 commits after that ancestor while the Elasticsearch project had accumulated substantially more changes.

Because both projects changed independently after the fork, current Elasticsearch files should not simply replace their OpenSearch counterparts.

# Design Principles

## Preserve the OpenSearch identity

The following are intentional characteristics of this project and should not be changed merely to match the Elasticsearch implementation:

* Application ID: `fulltextsearch_opensearch`
* PHP namespace: `OCA\FullTextSearch_OpenSearch`
* Platform class: `OpenSearchPlatform`
* Platform ID: `open_search`
* Platform name: `OpenSearch`
* OpenSearch PHP client
* OpenSearch authentication and connection behavior
* OpenSearch-specific configuration names
* Scoped OpenSearch vendor namespace

Existing configuration keys should be retained unless an explicit migration is designed.

Current keys include:

* `fields_limit`
* `opensearch_host`
* `opensearch_index`
* `opensearch_logger_enabled`
* `analyzer_tokenizer`
* `allow_self_signed_cert`

## Treat Elasticsearch changes according to purpose

When reviewing changes made upstream, classify them before porting them.

### Nextcloud compatibility

Examples:

* changed Nextcloud APIs
* dependency injection changes
* application registration
* configuration APIs
* command interfaces
* controller or route changes

These normally should be ported.

### Generic Full Text Search behavior

Examples:

* document handling
* query generation
* access-control handling
* index lifecycle improvements
* error handling

These should be evaluated and usually adapted to OpenSearch.

### Elasticsearch-specific behavior

Examples:

* Elasticsearch PHP client APIs
* Elasticsearch 8/9 compatibility code
* Elasticsearch-specific connection handling
* Elasticsearch-specific mappings or workarounds

These must not be copied blindly.

### Build and dependency changes

These should be evaluated independently because this project has its own OpenSearch client and scoped-vendor build process.

### Repository hygiene

Tests, CI, metadata, licensing, translations, and packaging changes can generally be handled separately from runtime porting.

# Porting Plan

The modernization work is divided into phases so that Nextcloud compatibility can be established before making larger behavioral or dependency changes.

## P0 — Nextcloud Compatibility and Bootstrap

**Status: Complete for Nextcloud 34**

The purpose of P0 is to establish that the existing OpenSearch application can load and participate correctly in the target Nextcloud release without attempting to fix every existing runtime problem.

P0 includes:

* application metadata
* Nextcloud application bootstrap
* configuration registration
* migration to current configuration APIs
* command compatibility
* settings/controller compatibility
* minimal platform changes necessary for dependency injection and startup

P0 deliberately avoids substantial search/index redesign and OpenSearch client modernization.

### ConfigLexicon

Nextcloud's current application configuration system uses a configuration lexicon and typed `IAppConfig` access.

The OpenSearch application now registers a `ConfigLexicon` during application bootstrap.

The lexicon preserves the existing OpenSearch configuration keys and defaults.

In particular, `opensearch_logger_enabled` retains the historical OpenSearch default of `true`. The Elasticsearch reference implementation currently uses a different default; matching that value would constitute an OpenSearch behavior change rather than a compatibility requirement.

### IAppConfig migration

`ConfigService` was migrated from the older string-oriented configuration interface to typed `IAppConfig` access.

Existing configuration written using the older Nextcloud configuration API is expected to remain readable. Nextcloud treats legacy values without known type information as mixed values, allowing typed access and later typed writes to normalize them.

An explicit database migration was therefore not required for P0.

### Configure command

`fulltextsearch_opensearch:configure` now follows the current Full Text Search platform behavior.

With JSON configuration supplied, it updates the configuration.

With no argument, it prints the current configuration.

Example:

```text
$ sudo -u apache php occ fulltextsearch_opensearch:configure
{
    "fields_limit": 10000,
    "opensearch_host": "",
    "opensearch_index": "",
    "opensearch_logger_enabled": true,
    "analyzer_tokenizer": "standard",
    "allow_self_signed_cert": false
}
```

### Nextcloud version metadata

The port originally began by comparing against Elasticsearch `master`, which at the time targeted the upcoming Nextcloud 35 release.

During P0 this was corrected: the current production target is Nextcloud 34, and the appropriate reference implementation is `fulltextsearch_elasticsearch:stable34`.

The application metadata for this port is therefore:

```xml
<version>34.0.0-dev.0</version>
```

with:

```xml
<nextcloud min-version="34" max-version="34"/>
```

Future porting work should always establish the target stable Nextcloud release before using Elasticsearch `master` as a reference.

# P0 Build-System Findings

The application vendors and scopes its OpenSearch dependencies into `lib/Vendor`.

This is important because a deployed application should not depend on a normal development Composer installation.

## PHP-Scoper location

The repository uses `bamarni/composer-bin-plugin`, with PHP-Scoper installed under:

```text
vendor-bin/php-scoper/
```

The build script incorrectly attempted to execute:

```text
vendor/bin/php-scoper
```

The correct executable location for the current repository configuration is:

```text
vendor-bin/php-scoper/vendor/bin/php-scoper
```

This was corrected during P0.

## Vendor organizer path bug

`lib-vendor-organizer.php` contained a path concatenation bug.

The destination was constructed as:

```php
$destination = rtrim($sourceDirectory, '/') . str_replace('\\', '/', $namespace);
```

which could produce paths such as:

```text
lib/VendorPsr/Log
```

instead of:

```text
lib/Vendor/Psr/Log
```

The corrected expression is:

```php
$destination = rtrim($sourceDirectory, '/') . '/' . str_replace('\\', '/', $namespace);
```

This was corrected during P0.

## Build process is destructive

The vendor build process consumes dependencies from the normal Composer `vendor` directory, scopes them, moves the resulting tree into `lib/Vendor`, and removes source dependency directories.

As a consequence, `composer vendor-build-setup` is not safely repeatable against the same already-processed Composer tree.

If another build is required, restore the Composer dependency tree first.

For example:

```bash
rm -rf vendor lib/Vendor lib/VendorPsr
composer install --no-scripts
```

On a development environment whose PHP version is newer than supported by old development dependencies, restoring the dependency tree may currently require:

```bash
composer install --no-scripts --ignore-platform-reqs
```

This should be considered a temporary development/build workaround rather than a production dependency policy.

## PHP 8.5 development dependencies

Testing P0 on PHP 8.5 exposed old development dependencies, notably Psalm 5.26.1, whose declared PHP support does not include PHP 8.5.

This did not prevent the application itself from running.

Development-tool dependency modernization belongs to a later build/tooling phase rather than P0.

# P0 Runtime Verification

P0 was tested on:

* Fedora 44
* Apache 2.4.68
* PHP 8.5.9
* Nextcloud 34.0.3
* Full Text Search 34.0.1
* Files Full Text Search 34.0.1

The existing Elasticsearch platform was disabled while testing the OpenSearch platform.

## Application bootstrap

The OpenSearch application enabled successfully and appeared as:

```text
fulltextsearch_opensearch: 34.0.0-dev.0
```

Nextcloud registered:

```text
fulltextsearch_opensearch
  fulltextsearch_opensearch:configure
```

This verifies that:

* Nextcloud accepts the NC34 application metadata
* application bootstrap succeeds
* dependency injection succeeds
* ConfigLexicon registration succeeds
* the OpenSearch platform can be instantiated
* the command is registered

## Platform configuration

Full Text Search was configured to use:

```text
OCA\FullTextSearch_OpenSearch\Platform\OpenSearchPlatform
```

The OpenSearch platform then loaded successfully.

## Functional test results

`occ fulltextsearch:test` progressed substantially through the functional test suite when an index existed.

Successful operations included:

* mocked provider creation
* platform loading
* platform connectivity test
* process locking
* index initialization call
* document indexing
* large document retrieval
* source comparison
* basic keyword searches
* phrase searches
* required/excluded term searches
* document access updates

This demonstrates that P0 did not merely make the application load: the existing OpenSearch implementation remains substantially functional on Nextcloud 34.

# Known Issues Discovered During P0

Failures that appear to predate the Nextcloud 34 port should not automatically expand the scope of the current phase.

They are recorded as GitHub issues and should be handled during the appropriate functional phase.

## Missing index during fulltextsearch:test

Tracked in GitHub issue #2.

When the configured OpenSearch index does not exist, `occ fulltextsearch:test` fails during its initial `Removing test` operation before reaching index initialization.

Nextcloud's test sequence intentionally removes old test data before asking the platform to initialize its index.

Investigation indicates that the OpenSearch client throws its own 404 exception hierarchy, while portions of the plugin currently catch a different HTTP-client exception type.

This behavior should be reviewed as part of the P1 `IndexService` work rather than patched ad hoc during P0.

## Mixed-case group access failure

Tracked in GitHub issue #3.

The Full Text Search test fails this access-control case:

```text
'license' - ["group_3","Group_2"]
```

returning no result where the test expects the `license` document.

This same failure was previously reported against the older parent OpenSearch project as issue #13 using:

* OpenSearch 2.19.2
* Nextcloud 31.0.8
* Full Text Search 31.0.0

The failure therefore predates the Nextcloud 34 modernization work.

Investigation during P0 also revealed differences between the OpenSearch and current Elasticsearch access-field mappings and search queries. These should be analyzed systematically during P1 rather than applying a speculative case-conversion workaround.

# P1 — Functional Search and Index Modernization

**Status: Planned**

P1 should concentrate on functional differences accumulated since the OpenSearch fork diverged from Elasticsearch.

Primary areas include:

* `IndexMappingService`
* `IndexService`
* `SearchMappingService`
* `SearchService`
* `QueryContent`
* related `OpenSearchPlatform` behavior

For each area:

1. Compare the current OpenSearch implementation against the historical common ancestor.
2. Compare `fulltextsearch_elasticsearch:stable34` against that same historical behavior.
3. Identify generic Full Text Search changes.
4. Adapt those changes to OpenSearch rather than copying Elasticsearch implementation details.
5. Preserve existing OpenSearch-specific behavior unless there is a reason to change it.
6. Run targeted tests.
7. If unrelated existing bugs are discovered, create or update issues rather than silently expanding P1 scope.

Known issues #2 and #3 should be considered while working on the related P1 components. If an upstream port naturally resolves one of them, verify that behavior explicitly before closing the issue.

## P1 index lifecycle and error-handling decisions

The plugin must support an existing index with operational credentials that cannot create indexes. Index initialization therefore has the following contract:

- a missing index is created with explicit mappings, followed by the OpenSearch attachment pipeline;
- an existing index is accepted and left untouched;
- creation and pipeline errors propagate with their OpenSearch messages;
- transport node exhaustion during document indexing is reported to Full Text Search as a temporary platform failure;
- permanent request failures remain actionable index or runner errors.

Administrators can explicitly provision a new index with:

```bash
sudo -u apache php occ fulltextsearch_opensearch:initialize
```

This command uses the configured credentials and reports the active provisioning stage. It is intentionally separate from `fulltextsearch:test`, which writes and deletes synthetic documents and requires delete-by-query permission.

The behavior differs from copying Elasticsearch `stable34` mechanically. The upstream platform uses its Elasticsearch transport exception for temporary failures and has no backend-specific initialization command. The OpenSearch port translates its scoped `NoNodesAvailableException` instead and adds the command to support separation between privileged provisioning and restricted operation.

See `docs/ADMINISTRATION.md` for the complete workflow.

# P2 — OpenSearch Client, TLS, Authentication, and Build Tooling

**Status: Planned**

Expected areas include:

* OpenSearch PHP client version
* TLS handling
* self-signed certificate behavior
* Nextcloud CA bundle integration
* authentication behavior
* Composer dependencies
* PHP-Scoper
* vendor build process
* PHP 8.5 development-tool compatibility

The current Elasticsearch stable implementation contains newer TLS handling, including integration with Nextcloud's certificate manager. This should be evaluated and adapted to the OpenSearch client rather than copied blindly.

P0 intentionally retained the existing OpenSearch PHP client requirement and connection implementation.

# P3 — Repository and Packaging Hygiene

**Status: Planned**

Potential work includes:

* automated tests
* GitHub Actions/workflows
* coding-standard configuration
* Psalm configuration
* `.nextcloudignore`
* REUSE/SPDX metadata
* packaging
* translations
* repository metadata

# Branching and Review

Current development follows a Gitflow-like structure.

Important branches include:

```text
devel
port-nextcloud-34
port-nextcloud-34-pN
```

`port-nextcloud-34` is the integration branch for the overall Nextcloud 34 effort.

Individual phases should be developed and reviewed separately before being integrated.

AI-generated implementation work should not be merged merely because it compiles or because an agent reports success. Review the resulting diff, compare relevant behavior with the upstream stable implementation, and perform applicable runtime testing.

# Scope Discipline

A porting phase is not expected to fix every problem encountered while testing it.

When a test exposes a failure:

1. Determine whether the failure was introduced by the current phase.
2. Determine whether the failure blocks the objective of the current phase.
3. If it is pre-existing or outside scope, document it as an issue.
4. Continue the planned port unless the problem prevents meaningful progress.

This rule is especially important for AI-assisted development, where a coding agent may otherwise follow a newly discovered problem into unrelated code.

The issue tracker is the durable record of defects and work items; phase documents define scope; pull requests contain implementation.

# Source of Truth

Repository documentation and GitHub project data should be treated as the durable source of project context.

In particular:

* `AGENTS.md` — instructions and guardrails for coding agents
* `docs/PORTING.md` — architecture, history, decisions, and port status
* `docs/TESTING.md` — reproducible build and runtime testing procedures
* GitHub milestone — work included in a release/port
* GitHub issues — individual defects and requirements
* GitHub pull requests — reviewed implementation

Conversation history with an AI assistant can provide useful working context, but important decisions and discoveries should ultimately be recorded in the repository.
