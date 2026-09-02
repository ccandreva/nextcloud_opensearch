Project goal
------------
Maintain the OpenSearch backend for Nextcloud Full Text Search,
tracking the maintained Nextcloud Elasticsearch backend where
appropriate while preserving OpenSearch-specific behavior.

Reference upstream
------------------
https://github.com/nextcloud/fulltextsearch_elasticsearch

Porting strategy
----------------
Do not blindly merge/cherry-pick Elasticsearch changes.

Classify upstream changes as:
- generic Nextcloud compatibility
- search/index behavior
- Elasticsearch-specific
- build/tooling
- hygiene

Preserve:
- app ID fulltextsearch_opensearch
- namespace OCA\FullTextSearch_OpenSearch
- platform ID open_search
- OpenSearch PHP client
- OpenSearch-specific authentication/configuration
- existing configuration keys unless migration is intentional
- existing author, copyright, and license headers unless changing them is an explicit design decision

Known OpenSearch divergences
----------------------------
Verified during the Nextcloud 34 P1 runtime tests:

- Search highlight requests use `max_analyzer_offset` in OpenSearch.
  Elasticsearch stable34 uses `max_analyzed_offset`; copying that key causes
  an OpenSearch `x_content_parse_exception`.
- The OpenSearch attachment processor used by the supported test environment
  does not accept Elasticsearch's `remove_binary` option. Omit that option.
- Attachment, convert, and remove must remain separate sequential ingest
  processor entries.

Re-check these differences against the supported OpenSearch version before
adopting future Elasticsearch implementation changes.

Branches
--------
devel                     ongoing development
port-nextcloud-34          integration branch for NC34
port-nextcloud-34-pN       individual porting phases

Workflow
--------
1. Work from a defined issue/phase.
2. Keep changes within that scope.
3. Compare relevant behavior against fulltextsearch_elasticsearch/stable34.
4. Do not replace OpenSearch behavior with Elasticsearch-specific code.
5. Run applicable tests.
6. Report failures and suspected pre-existing bugs rather than expanding scope.
7. Changes are reviewed before merging.

Current target
--------------
Nextcloud 34.

See docs/PORTING.md for project history, decisions, and phase status.
See docs/TESTING.md for the runtime test environment and procedures.
