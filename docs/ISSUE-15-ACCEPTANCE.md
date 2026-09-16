# Issue #15 acceptance investigation — 2026-09-15/16

## Scope and comparison

Investigation of real Nextcloud Files indexing and search; no speculative backend
changes. Reference indexes and the shared attachment pipeline are protected.

- P0 baseline: `47a94641601048dcdff2b9ae69b20bdce50f769a`.
- Current: `0f901480d58fa497eb7f3265c6026c7d84db092b`.
- Issue branch: `issue-15-e2e-acceptance`; no merge.

Git history identifies `47a9464` as the P0 merge (#4). Its immediate successor,
`70d28922779a9981151c11bbc65e1f81816b3171`, is the squashed P1 integration (#13),
including indexing/search and provisioning/error handling. `0f90148` adds the
accepted admin UI work (#16) and was the integration branch HEAD at investigation
start. P0 metadata itself confirms the NC34 target despite its historical branch
name containing “35”. No deleted feature branch is needed for the comparison.

## Actual environment

| Component | Observed |
| --- | --- |
| Repository | `/var/www/cxcloud/apps/fulltextsearch_opensearch` |
| Initial branch | `port-nextcloud-34-FixIndex` (clean, at integration HEAD) |
| Nextcloud | `/var/www/cxcloud`, 34.0.3 (`34.0.3.2`) |
| Status | Installed; maintenance off; no database upgrade needed |
| OS | Fedora Linux 44 Server Edition |
| Apache | 2.4.68 |
| CLI PHP | 8.5.10 |
| Web/OCC account | `apache` |
| Full Text Search | 34.0.1 |
| Files Full Text Search | 34.0.1 |
| OpenSearch app | 34.0.0-dev.0 |
| OpenSearch PHP client | 2.3.1, confirmed in scoped `Client::VERSION`; vendor retained |
| Elasticsearch app | 34.0.1, enabled; OpenSearch explicitly selected |
| Initial OpenSearch index | `nextcloud34-test` |
| Endpoint | Configured HTTPS endpoint, port 9201; user information omitted |
| Server root response | Version `7.10.2`, Lucene `9.12.3`, RPM build dated 2026-07-01 |
| Actual node version | Unverified: node-info request denied with HTTP 403 |

Provider settings were retained: local files enabled; `files_external=2`; team
folders enabled; content size limit 20 MB; PDF and Office enabled; ZIP content
disabled; chunk depth 2; direct result opening enabled. Metadata-only records
can therefore be expected for some objects; an empty content field alone is not
evidence of a rejected indexing request. Apache and PHP-FPM configuration both
identify the `apache` user/group.

The root response must not be treated as the actual OpenSearch node version; it
may reflect compatibility mode. Initial root/count/pipeline requests returned
HTTP 401. The operator restored credentials through the existing configuration
path; the same probes then succeeded. No credentials were printed or committed.

Unprivileged sandbox PHP encountered an inaccessible OPcache file-cache directory.
A command-local `-d opcache.file_cache=` allowed version/lint probes; normal OCC
ran as `apache` outside the sandbox. No system PHP configuration was changed.
Browser/computer inventory returned no browsers or apps, so the host's Firefox
session was not exposed through the available automation.

## Indexing path and upstream comparison

`fulltextsearch:index` initializes the selected platform, selects providers/users,
and calls core `IndexService::indexProviderContentFromUser()`. Files generates
chunks, recursively enumerates indexable nodes, honors `.noindex` and source
settings, and returns documents. Core matches each against `fulltextsearch_index`
(provider/document/collection), skips ignored/error/up-to-date entries, fills
content/access metadata, and submits eligible documents individually.

OpenSearch uses IDs `files:<file-id>`. Plain content is indexed directly; encoded
content invokes the global `attachment` pipeline. The backend reports success,
warning (including contentless fallback), or failure to the runner and updates
Nextcloud state. Temporary platform exceptions propagate. Some other exceptions
are caught at core per-document/per-user boundaries, so command exit status alone
cannot establish completeness.

The installed Elasticsearch 34.0.1 `IndexMappingService.php` was byte-identical to
the fetched [upstream stable34 file](https://github.com/nextcloud/fulltextsearch_elasticsearch/blob/stable34/lib/Service/IndexMappingService.php)
(SHA-256 `2dd96935bff3e1fc511526b702bf24f8a92cb3ff3a856e65344646f24e30333a`).
Its individual indexing/ID strategy is consistent with the OpenSearch path.
OpenSearch-specific client, highlight key, and ingest processor behavior remain
unchanged.

## Initial evidence

- Existing OpenSearch index: **9 Files** documents at the initial successful read
  (provider aggregation confirmed separately);
  the earlier approximate 154 count was not its present count.
- Nextcloud state before reset: Files **8,969** rows: **8,958** `INDEX_OK` (1),
  **4** status 9 (`OK | CONTENT`), **7** `INDEX_FULL` (28). Deck: 3 `INDEX_OK`.
- Read-only Files enumeration: **25 users**, **19,122 chunks**, **12,835 offers**,
  **8,568 unique provider/document IDs**, no caught enumeration exceptions.
  Duplicate offers across users/chunks must not be mistaken for lost documents.
- Untouched Elasticsearch reference: **8,914** total, **8,911** Files documents.
  Of those Files IDs, **234** no longer have a current Nextcloud file-cache row;
  **8,677** still have a row. Presence in file cache does not establish current
  provider indexability. No reference index was changed or deleted.

These observations establish that the historical Elasticsearch count includes
stale IDs and that the real provider currently offers thousands of documents.
Retained core indexing state is a plausible cause of a sparsely populated newly
selected backend index: the state is not keyed by backend index name. The exact
historical invocation/state that produced about 154 documents has not been
reconstructed, so that causal explanation remains a hypothesis pending evidence.

## A/B preparation and measurement

Disposable indexes are `nextcloud34-ab-p0-issue15` and
`nextcloud34-ab-current-issue15`. P0 was activated with:

```bash
git switch --detach 47a94641601048dcdff2b9ae69b20bdce50f769a
```

P0's own mapping generator created its fresh index via the configured client.
This deliberate provisioning accommodation preserves the existing global pipeline:
P0's missing-index initializer can overwrite it and can delete it on failure.
The existing pipeline was inspected: attachment, convert, and remove are three
separate sequential processor entries. The pipeline digest before the baseline was
`d55cdf4930739ef60060feab41ce3796cacbc0a336376a8d8564baa16aecaa0a`.
The comparison therefore tests indexing with a common existing pipeline, not
P0's historical provisioning implementation.

For each target, reset uses the supported command after verifying the disposable
index selection:

```bash
sudo -n -u apache php /var/www/cxcloud/occ fulltextsearch:reset --provider files
# confirmations: y; reset files ALL
```

The real indexing command is:

```bash
sudo -n -u apache php /var/www/cxcloud/occ fulltextsearch:index \
  --no-readline '{"provider":"files"}'
```

For measured runs, `/tmp/issue15-run.php` instantiates a temporary subclass of the
installed OCC Index command, retains inherited execution, and invokes it with the
same Symfony arguments. It replaces only public result/error display callbacks
with aggregate counters and uses `NullOutput` to avoid printing private filenames.
It reports result types, distinct result IDs, error classes, and command exit.
No installed provider/core/backend code was patched. Independent enumeration and
refreshed OpenSearch scans supply counts outside those callbacks. Counts not
observable through these mechanisms are explicitly unmeasured.

The core runner's stored `totalDocuments` is not used: version 34.0.1 writes a
constant 42 in `RunningService::stop()`.

## Server-side search probes (baseline, while indexing)

These use the actual core `SearchService` and Files result enrichment in isolated
CLI processes with an existing user selected in memory. They do not change user
permissions or browser login state, and they are not substitutes for GUI tests.

- Plain-text content: target found; title, link, and excerpts present.
- Uppercase repeat of that content query: same target found.
- PDF extracted content: target found; title, link, and excerpts present.
- Shared content: target found for a non-owner with independently verified file
  access. An initial longest-word probe returned no results even for the owner;
  choosing a token verified to match the indexed content resolved that probe
  problem. It is not evidence of an ACL defect.
- Private document: owner finds the target; another existing user whose file tree
  does not expose the object does not find it. The owner control establishes that
  absence is not simply failed indexing.
- Mixed-case filename: target absent. An ID-restricted direct title wildcard
  matches the original-case token (count 1), but its lowercase form matches zero.
  P0 maps `title` with the keyword analyzer while query generation lowercases
  search words. This behavior also reproduced on current code,
  so it is not evidence of a modernization regression. This probe explicitly limits
  search to filenames; default title-and-content search may still find a target
  through its content.

Files 34.0.1 base64-encodes ordinary text too, so even a plain-text file can have
attachment metadata. Presence of `attachment` alone must not classify a sample as
PDF/office; the audit uses MIME metadata/content to distinguish examples.

## Completed P0 indexing result

- Reset and measured command both completed successfully; command exit 0.
- 8,569 result events: **8,567 success**, **2 warning**, **0 failure events**.
- **8,568 distinct result IDs** and final refreshed OpenSearch **8,568 documents**.
  One repeated ID produced an additional result event; this is not a missing
  document. Every unique ID counted by independent enumeration is accounted for
  numerically; the independent enumeration did not retain an ID set for exact
  membership comparison.
- Files state: **8,566 `INDEX_OK`**, **2 `INDEX_FULL` with one retained error each**.
  The 3 Deck state rows remained unchanged.
- Content failures: two HTTP 400 `BadRequest400Exception` events followed by
  successful metadata-only fallback. Stored reasons are
  `mapping.getTrueTypeFont() returns null, please report` and
  `Index 10 out of bounds for length 10`. Both failures also reproduced in the completed fixed run.
- All 8,568 sources contain provider/title/content/owner/users/groups/circles/links.
  `lastModified` is absent as expected in P0. Nonempty content: **2,798**;
  attachment metadata: **3,169**; nonempty users/groups: **852** documents.
  **15** titles are empty; a read-only file-cache check identified all 15 as
  directories, including 9 user Files roots. These are not ordinary files with
  missing filenames.
- Ordinary-text, attachment, and shared samples have expected ID shape, owner,
  title and extracted content. Example content lengths: 3,938, 1,072, and 103,156
  bytes respectively. The shared sample has one group access entry.
- Sorted hashed-ID-set digest:
  `76f4636c340da19a456036dfb575815bc5832e02a37c4b01fefc238202ab6730`.
- Attachment-pipeline digest unchanged from preparation.
- Run interval: 2026-09-16 00:02:29–00:28:46 UTC.

The corrected run-window log scan included 33 provider warnings that `stat()` was not an
array. It also contained 16 OpenSearch-client warnings from failed requests,
including deliberate permission probes and the two content failures; absence of
runner failure events does not mean absence of warnings. No structured exception
class was present in the selected log records. Node-info and explicit scroll
cleanup are denied with HTTP 403; search/scroll reads and index refresh work.
Scroll contexts use a two-minute expiry. No privileges were changed to bypass
these restrictions.

## Current-code preparation

Restored the issue branch at the exact current SHA. Verified the second target did
not exist, selected it, then ran:

```bash
sudo -n -u apache php /var/www/cxcloud/occ fulltextsearch_opensearch:initialize
```

The command created explicit mappings and reported that the existing attachment
pipeline was not modified. Before resetting Files state, an eight-document
runtime sample against this fresh empty target selected **0** documents with
ordinary core options and **8** with `force`, without backend requests or state
writes. This demonstrates the retained-state mechanism on current code, while
not claiming to reconstruct the historical approximate 154 result.

Then repeated `fulltextsearch:reset --provider files` with the same confirmations
and launched the same measured real indexing command. Current run began at
approximately 2026-09-16 00:31:52 UTC. The unmodified run subsequently terminated with a PHP `Error`; see the
regression and clean retest below.

## Unmodified current-code server-side search observations

While the full run continued, real plain-text and PDF content searches found their
selected targets with nonempty titles, links, and excerpts. The uppercase content
query also found its target. A non-owner found the same shared sample tested on
P0. A private sample was absent for a user whose file tree did not expose it and
present for its owner (paired probes used the same anonymized document ID).

Current code reproduces P0's **filename-only** mixed-case title mismatch: direct
original-case wildcard count 1, lowercased wildcard count 0. A normal combined
content/title query for a tested filename token **did** find the target via
content. The defect is therefore specifically title matching, not a universal
failure of ordinary filename searches. The relevant keyword mapping and lowercase
query-generation behavior predate P1. Recommend a separate focused search issue;
no speculative indexing change is justified by this result.

The independent current-code enumeration completed with the same **25 users**,
**19,122 chunks**, **12,835 offers**, and **8,568 unique IDs**, with no caught
exceptions. Its anonymized ID-set digest was
`76f4636c340da19a456036dfb575815bc5832e02a37c4b01fefc238202ab6730`, exactly matching
the completed P0 index. This supplies exact membership evidence beyond the first
enumeration's numerical comparison.

## Discovered regression and fix

The unmodified current run exited 1 after populating 4,489 Files state rows
and 4,489 OpenSearch documents,
leaving its runner marked `run`. Its initial measurement wrapper recorded only
`Error`, so the original exception message/stack is not available. The corrected
time-window log scan found `No alive nodes` at **2026-09-16 00:56:02 UTC**. The
site logs timestamps as `September 15, 2026 20:56:02` in `America/New_York`;
lexical ISO-date filtering is invalid for this format. Final log figures here
were recomputed using the configured timezone and explicit start/end instants.

A direct in-memory reproduction then established a concrete P1 regression:
`OpenSearchPlatform` catches `NoNodesAvailableException` and tries to instantiate
`OCP\FullTextSearch\Exceptions\PlatformTemporaryException`. That class is absent
in this actual NC34 installation. Full Text Search 34.0.1 instead catches
`OCA\FullTextSearch\Exceptions\PlatformTemporaryException`. The reproduction
raised `Error: Class "OCP\FullTextSearch\Exceptions\PlatformTemporaryException"
not found`, which the core cannot handle as a temporary failure.

The P0-to-P1 diff introduced this translation; P0 has neither this import nor the
NoNodes-specific catch. The installed Elasticsearch 34.0.1 backend uses the same
unavailable OCP name, which is not a reason to preserve a runtime failure here.
The observed real-run transport error is consistent with the reproduced fatal
path, although its original missing stack prevents claiming a direct stack trace
from that first interruption. The external reason for node exhaustion itself was
not established and no cluster/network settings were changed.

**Fix:** use the exception class actually caught by the installed runner. No
transport, credentials, host semantics, mappings, or document selection changed.
Implementation commit:
`d11c53a2e583f72e6d4f6d4addf79b522a392149` —
`Fix temporary indexing failures for the Nextcloud 34 runner (#15)`.

`tests/Runtime/TemporaryPlatformFailure.php` uses the real core service and an
in-memory fake client to cover node exhaustion on both the initial request and
the contentless fallback. Both cases failed with `Error` before the fix and
passed afterward, propagating the runner's temporary exception. The test does not
write backend documents or indexing state. PHP lint passed for the changed
platform and regression script.

After confirming the original process had exited, `fulltextsearch:stop` cleared
the stale task runner. The interrupted index is retained for inspection.
A third target, **`nextcloud34-ab-fixed-issue15`**, was verified nonexistent,
selected, and provisioned with the supported initialize command. The shared
pipeline digest stayed unchanged. Files state was reset with the same supported
provider-specific reset and confirmations. A clean full run of the committed fix
started at approximately **2026-09-16 01:06:07 UTC**. The temporary observer now
also prints aggregate totals and a redacted message/argument-free stack on an
interruption, avoiding the first wrapper's diagnostic limitation.

## Completed fixed-code comparison

The clean run of `d11c53a2e583f72e6d4f6d4addf79b522a392149` completed with exit 0
and runner status `stop`, from **01:06:07 to 01:31:23 UTC on 2026-09-16**.

| Measurement | P0 | Unmodified current | Fixed current |
| --- | ---: | ---: | ---: |
| Independent provider unique IDs | 8,568 | 8,568 | Same provider; not enumerated a third time |
| Result events | 8,569 | Interrupted; no complete event total | 8,569 |
| Success / warning / failure events | 8,567 / 2 / 0 | Incomplete | 8,567 / 2 / 0 |
| Unique result IDs | 8,568 | Incomplete | 8,568 |
| Persisted OpenSearch documents | 8,568 | 4,489 | 8,568 |
| Final Files state | 8,566 OK; 2 FULL with errors | 4,489 rows | 8,566 OK; 2 FULL with errors |

The fixed `_count` and full source scan both returned **8,568**. There are no IDs
exclusive to either complete index. Canonical per-field comparison found exactly
one changed field: **`lastModified` added to all 8,568 documents**. Excluding that
expected P1 field, zero documents differ from P0. Thus neither ID collision nor
missing provider submissions explains a population deficit in the completed runs.
The repeated result event does not represent an additional distinct document.
The independent enumeration measures offers, not a separate exact counter of all
core skip decisions or transport requests; those totals should not be inferred
by subtracting result events from offers.

All documents have provider, title, content, owner, users, groups, circles, links,
and lastModified fields. There are 2,798 nonempty content fields, 3,169 attachment
metadata objects, and 852 documents with explicit user/group sharing. All owners
and lastModified values are nonempty. The 15 empty titles match P0's directory
records. Ordinary text, attachment, and shared samples have numeric `files:` IDs,
nonempty titles/owners/content, and positive integer lastModified values. Their
content lengths were 1,582, 21,098, and 1,973 bytes respectively; the shared sample
had one explicit user. No private filenames or contents are recorded here.

The two retained content-extraction errors are identical to P0:

- `mapping.getTrueTypeFont() returns null, please report`
- `Index 10 out of bounds for length 10`

Both yielded successful contentless fallback documents and retained error state;
this is not complete content-extraction success. The common pipeline digest
remained `d55cdf4930739ef60060feab41ce3796cacbc0a336376a8d8564baa16aecaa0a`.

The exact fixed-run log window contained 8,611 relevant entries: 8,574 OpenSearch
info entries, four OpenSearch warnings, and 33 Files `stat() ... is not an array`
warnings. There were no `No alive nodes` entries, application error-level entries,
or structured exception classes in that window. These figures exclude later
acceptance probes and synthetic testing.

## Final search and contract checks

The supported command completed successfully on the fixed index:

```bash
sudo -n -u apache php /var/www/cxcloud/occ fulltextsearch:test
```

All keyword, phrase, inclusion/exclusion, group-access, and share-access cases
passed, including the mixed-case group case recorded as a historical P0 failure
elsewhere in TESTING.md. Cleanup and unlock succeeded. A subsequent provider
aggregation showed exactly 8,568 documents, all `files`, with no synthetic provider
remaining.

Final real-core search probes on the fixed index passed for plain text, its
uppercase variant, PDF content, non-owner shared access, and private-file exclusion.
The paired private-file probe found the same document for its owner and excluded
it for the user whose file tree lacked access. Positive results contained titles,
links, and excerpts. These checks used isolated CLI session contexts, with no
changes to stored accounts, shares, or permissions.

The filename-only mixed-case mismatch remained reproducible on fixed code. A
repeat of the combined filename/content probe was declined at command approval
and was not rerun; the earlier P0 and unmodified-current combined probes passed.
Server-side presence of a result link or excerpt does not establish browser link
correctness or visual highlight quality.

## Browser acceptance and remaining evidence

Browser automation inventory exposed no apps or browsers. The operator was asked
to check the populated fixed index in Firefox: known filename, plain text,
PDF/Office content and case variants; correct titles/excerpts and links opening the
intended objects; owned/shared positives and an inaccessible negative; any console
or network errors. No observations have yet been supplied for these checks.
Therefore GUI search, actual link opening, visual excerpts, and browser-level
access-control acceptance remain unverified. Server-side ACL checks passed as
recorded above; they are not substituted for the requested GUI evidence.

## Diagnosis, follow-ups, and handoff

The historical approximate 154-versus-8,914 discrepancy was **not reproduced**.
The untouched original OpenSearch index had nine Files documents when inspected.
Both complete clean runs contain exactly the currently enumerated 8,568 IDs.
Retained core state demonstrably skips documents on a fresh backend index, and
234 historical Elasticsearch Files IDs have no current file-cache row. These are
supported mechanisms explaining why raw historical counts are not equivalent;
the exact invocation/state that produced 154 is unavailable, so its cause remains
unproven. The discovered temporary-failure regression is independently demonstrated
and fixed, without claiming it caused that historical count.

Recommend separate focused follow-ups for the pre-existing filename-only case
mismatch and the two reproducible attachment extraction failures. Provider stat
warnings and the external node-exhaustion event may merit provider/environment
investigation if repeated. No unrelated fixes or new GitHub issues were made.

Changed files: `lib/Platform/OpenSearchPlatform.php`,
`tests/Runtime/TemporaryPlatformFailure.php`, `docs/TESTING.md`,
`docs/ADMINISTRATION.md`, `docs/PORTING.md`, and this report. The durable procedure
separates synthetic tests, real provider rebuilds, population/field checks, browser
search, and ACL acceptance. Implementation and regression coverage are committed
separately from these documentation updates, with #15 in commit subjects. No merge
was performed.

Resources retained for review:

- `nextcloud34-ab-p0-issue15`: 8,568 documents; disposable and inactive.
- `nextcloud34-ab-current-issue15`: 4,489 documents; disposable interrupted run.
- `nextcloud34-ab-fixed-issue15`: 8,568 documents; **selected active index**, matching
  rebuilt Files core state. Keep it for browser acceptance. Remove only after
  choosing a replacement and accounting for provider state.
- `/tmp/issue15-*`: credential-free measurement helpers; removable after review.

The first two task-created indexes can be removed after review without deleting
the shared pipeline. The existing OpenSearch and Elasticsearch reference indexes
were not written or deleted. Files indexing state was intentionally rebuilt;
Deck's three state rows were retained. No user files, permissions, system packages,
or system-wide configuration were changed by this work.

**Acceptance remains incomplete** — the missing evidence is the operator's actual
Firefox search, link/excerpt, and access-control observations described above.
