# Evidence index and capture checklist

Save screenshots locally under evidence/. Keep passwords, keys, tokens, and SMTP credentials out of every capture. Use actual timestamps and IDs; do not present simulated tests as cloud results.

| Ref | Capture | Suggested caption | Status |
|---|---|---|---|
| E01 | Table structures and ERD | The local schema enforces consent, unique email, campaign/customer uniqueness and the outbox customer foreign key; history retains a logical customer association. | Definitions captured in evidence/local-verification.json; screenshot pending |
| E02 | 10,000 total and 8,000/2,000 counts, small sample | The generated local population contains 10,000 fictional customers with exactly 8,000 opted in and 2,000 opted out. | Actual counts captured; screenshot pending |
| E03 | Internet disconnected, repeat seed and inspection | Generation and inspection run locally without internet, and repeated generation preserves existing records. | Operator reports disconnected demonstration and screenshots completed |
| E04 | 50 selection, zero ineligible, repeated preparation | The same campaign preserves all 50 recipients, request IDs, and persisted PROMO15 code on repeat preparation. | Actual repeat equality/hash captured; screenshot pending |
| E05 | Both queue names and details | A dedicated Standard main queue and Standard DLQ isolate this lab's requests. | Live configuration verified; screenshots in operator document |
| E06 | Visibility/retention/wait/redrive settings | The main queue uses 180-second visibility, four-day retention, 20-second polling, and a DLQ after three receives. | Pending |
| E07 | IAM policy attachment and identity check | The application identity is limited to the two specified queue resources. | Pending |
| E08 | Dashboard before publication | The baseline records queue conditions before the 50 campaign messages are published. | Pending |
| E09 | Both alarm configurations and SNS status | The DLQ alarm detects at least one visible message; the backlog alarm detects an oldest-message age of at least 300 seconds. | Pending |
| E10 | 50 QUEUED, publication log and backlog | SQS acknowledgments are stored locally; a second publisher run adds no messages. | Pending |
| E11 | One full trace, 50 SENT, Mailtrap samples and queue trend | Stored outcomes and acknowledgment events correlate with queue processing; Mailtrap samples show the persisted promo code. | Pending |
| E12 | duplicate_skipped plus acknowledgment | Redelivery of a completed campaign/customer pair does not submit another email. | PASS live: duplicate_skipped and deleted, no additional SMTP |
| E13 | Invalid payload, retries, DLQ and ALARM | An invalid request remains undeleted, is retried, and reaches the monitored dead-letter queue. | PASS live: three failures, DLQ 1, operator saved ALARM and SNS email evidence |
| E14 | Recovery SKIPPED, deleted, DLQ zero, alarm OK | A corrected opted-out diagnostic request is acknowledged without SMTP; the original malformed incident is then cleared. | PASS live: SKIPPED and deleted; DLQ API counts 0/0; operator saved alarm OK evidence |
| E15 | Tests and source review | Ten automated workflow tests and real MySQL schema checks pass; external sends are mocked in the automated suite. | Text output captured locally |
| E16 | Repository and document access | The instructor can open the completed source repository and report. | Pending |
| E17 | Mailtrap Home Sandbox card or Sandboxes usage | Monthly sandbox usage is 0 out of 50, leaving exactly 50 submissions for the baseline campaign. | Verified in signed-in Mailtrap; user screenshot requested before SMTP setup |

The local verification HTML is a readable view of actual captured database output. It explicitly marks disconnected operation and cloud execution as unverified.

Final evidence note: E10 publication, E11 baseline and E12 duplicate tests passed live. The operator stores screenshots in the Google Docs report. E16 repository is published; final document access and assembly remain to be checked. E01 schema/ERD capture still needs review. Older pending labels identify capture-review work, not failed application tests.
