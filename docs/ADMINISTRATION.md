# OpenSearch Administration

This document describes provisioning and permissions for the Nextcloud OpenSearch Full Text Search platform.

## Configure the platform

Configure the OpenSearch hosts and index name before provisioning. Configuration may be supplied through the Nextcloud administration interface or the existing console command:

```bash
sudo -u apache php occ fulltextsearch_opensearch:configure '{"opensearch_host":"https://user:password@opensearch.example:9200","opensearch_index":"nextcloud"}'
```

Treat shell history and process listings as sensitive when credentials are embedded in a command. Running the command without a JSON argument prints the current configuration with host passwords masked.

## Initialize OpenSearch resources

Run the explicit initialization command with credentials that may create an index and manage an ingest pipeline:

```bash
sudo -u apache php occ fulltextsearch_opensearch:initialize
```

The command checks the configured index and the `attachment` ingest pipeline independently:

- when the index is missing, it creates the index settings and explicit field mappings;
- when the index exists, it does not recreate it or alter its mappings;
- when the attachment pipeline is missing, it creates the pipeline used for encoded document content;
- when the attachment pipeline exists, it does not replace it.

This independent behavior allows the command to recover when index creation succeeded but pipeline creation failed. Running it again leaves the new index untouched and retries only the missing pipeline.

The command reports connection, index/mapping, and pipeline failures separately and returns a nonzero exit status with the OpenSearch error message. A restricted account without permission to inspect or create the pipeline therefore receives an actionable permission failure.

The command is a provisioning operation, not a general repair or migration operation. It does not inspect an existing index for mapping compatibility or replace existing mappings or pipeline definitions.

## Separate provisioning and operational credentials

A deployment may temporarily configure a privileged OpenSearch account, run `fulltextsearch_opensearch:initialize`, and then replace it with a restricted operational account.

Provisioning requires permission to:

- test connectivity and check whether the configured index exists;
- inspect the global `attachment` ingest pipeline;
- create the configured index with its settings and mappings when it is missing;
- create the global `attachment` ingest pipeline when it is missing.

Normal indexing and searching do not require permission to create indexes or manage pipelines after provisioning. The operational account requires permission to:

- check the configured index;
- inspect the pre-created `attachment` pipeline during initialization;
- search and retrieve documents;
- create, update, and delete documents in that index;
- execute the pre-created `attachment` pipeline when attachment content is indexed.

Reset operations and `fulltextsearch:test` additionally require delete-by-query permission. With the OpenSearch Security plugin this is commonly reported as `indices:data/write/delete/byquery`. Exact role and action names depend on the OpenSearch security configuration.

A full reset of all indexes also deletes the configured index and attachment pipeline and therefore requires the corresponding delete/manage permissions.

## Validation

The core Full Text Search command exercises indexing, searching, access control, updates, and cleanup:

```bash
sudo -u apache php occ fulltextsearch:test
```

This test writes and removes synthetic documents. It is useful after provisioning but should not be used as the provisioning mechanism for a restricted account because its cleanup requires delete-by-query access.

The core indexing and test runners also call the platform's `initializeIndex()` hook. As with the explicit command, that hook leaves existing resources untouched and provisions either resource when it is missing.
