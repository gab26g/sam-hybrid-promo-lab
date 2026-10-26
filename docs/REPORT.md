# Systems Administration and Maintenance - Hybrid Email Laboratory - [Your Name]

Name: ____________________  
Student number: ____________________  
Section: ____________________  
Date: October 26, 2026  
Group members, if applicable: ____________________

**Working report:** completed local results are recorded below. Replace pending fields only after the corresponding live evidence exists. Fill the identification fields and reflection before submission.

## 1. Overview and architecture

This application prepares a promotional campaign using a local MySQL database and object-oriented PHP. Faker supplies fictional customer names and numbered example.com addresses. A campaign prepares 50 requests for randomly selected opted-in customers. The publisher sends customer identifiers and persisted campaign content to Amazon SQS. A single local worker resolves customer details, checks current consent, submits eligible messages to Mailtrap Email Sandbox, saves the outcome locally, and acknowledges the SQS message. Failed messages remain available for retries and can move to a dead-letter queue.

The database stays local. SQS is the online work queue, Mailtrap captures test messages, and CloudWatch provides queue-level monitoring. Local history and logs establish individual outcomes. Queue receipt alone does not establish email success.

[Insert architecture diagram from README and caption it Figure 1.]

## 2. OOP completion

| Class | Responsibility | Completion |
|---|---|---|
| Database | Local UTC PDO connection | Implemented |
| Settings | Environment validation, SQS and SMTP configuration | Implemented |
| LocalDatabaseTools | Initialize schema and inspect local records | Implemented |
| CustomerSeeder | Faker names and exact consent population | S1-S2 complete |
| CampaignPreparer | Atomic 50-recipient selection and stable outbox | C1-C3 complete |
| QueuePublisher | Publish PENDING rows and store acknowledgments | Q1-Q2, M1 complete |
| EmailSender | Personalized plain-text Mailtrap SMTP messages | E1-E2 complete |
| EmailHistory | Latest outcome for each campaign/customer | Implemented |
| MarketingProcessor | Validate, check completion and consent, send/save | P1-P5, M2 complete |
| QueueWorker | Receive, process, acknowledge and classify errors | W1-W2, M3 complete |
| ActivityLogger | Filtered UTC JSONL operational events | Implemented |

Constructor injection is visible in MarketingProcessor: its constructor receives PDO, EmailHistory, EmailSender, ActivityLogger, and send delay. The root worker script constructs these dependencies once; the processor does not fetch global database or SMTP objects. Tests inject a PHPMailer factory that builds real messages while replacing external SMTP submission.

Selected implementation excerpts:

```php
$ids = $this->db->query(
    'SELECT customer_id FROM customers WHERE marketing_opt_in=1 ORDER BY RAND() LIMIT 50'
)->fetchAll(PDO::FETCH_COLUMN);
```

The selection runs inside a transaction. A complete existing selection returns unchanged; a partial selection reports an inconsistency. Use one preparation process at a time.

```php
$this->history->save($job, 'SKIPPED', ['reason' => $reason]);
$this->logger->record('skipped', $meta + ['reason' => $reason]);
```

Outcome storage precedes the corresponding log event. The worker deletes only after the processor returns normally and uses the current ReceiptHandle.

## 3. MySQL design

D1 is complete in schema.sql, which contains table definitions only. Tables use InnoDB and utf8mb4:

- customers: customer ID primary key, unique fictional email, consent constrained to 0 or 1.
- marketing_outbox: persistent request ID, customer foreign key, unique campaign/customer pair, PENDING/QUEUED publication status.
- sent_marketing_emails: composite campaign/customer primary key, latest SENT/SKIPPED/FAILED outcome. The customer association is deliberately not an enforced foreign key so unknown-customer requests can be recorded as SKIPPED.

Actual server: **MySQL Community Server 8.4.13, localhost port 3308**. A separate instance was used after the XAMPP MariaDB server reported an internal Aria checksum error while granting lab permissions. Existing databases were not repaired or replaced.

[Insert E01 table structures and ERD.]

## 4. Faker generation and recipient selection

Actual local inspection on October 26, 2026 established:

| Measurement | Actual result |
|---|---:|
| Total customers | 10,000 |
| Marketing opted in | 8,000 |
| Marketing opted out | 2,000 |
| CAMP-001 requests prepared | 50 |
| Distinct recipients | 50 |
| Ineligible selected recipients | 0 |
| Initial publication status | 50 PENDING |
| Promo code | PROMO15 |

Repeated generation preserves existing records. Repeated preparation preserves the original recipients, request IDs, and promo code. A local evidence capture records equality and a SHA-256 fingerprint of the stored selection. These operations do not contact SQS or SMTP.

[Insert E02 and E04. Physical disconnected-internet demonstration E03 is still pending; record actual result after performing it.]

## 5. IAM and SQS setup

Region: ap-southeast-2 (Sydney). Planned names: MarketingEmailQueue-gab26g, MarketingEmailDLQ-gab26g, and MarketingLabSQS-gab26g.

Main queue requirements: Standard; 180-second visibility; four-day retention; 20-second receive wait; DLQ maximum receives three. DLQ retention: 14 days. Application permissions are limited to SendMessage, ReceiveMessage, DeleteMessage, GetQueueAttributes, and GetQueueUrl on the two queue ARNs.

**Actual queue setup:** User created MarketingEmailQueue-gab26g. Read-only console inspection verified Standard type, SSE-SQS encryption, owner-restricted resource policy, four-day retention, 180-second visibility (displayed as 3 Minutes), 20-second receive wait, and zero delivery delay. Its dead-letter target is arn:aws:sqs:ap-southeast-2:408852558600:MarketingEmailDLQ-gab26g, with maximum receives 3. Available, in-flight and delayed message counts were all zero at inspection. A subsequent read-only API check verified the DLQ retention as 14 days.

Main URL: https://sqs.ap-southeast-2.amazonaws.com/408852558600/MarketingEmailQueue-gab26g

Main ARN: arn:aws:sqs:ap-southeast-2:408852558600:MarketingEmailQueue-gab26g

**Verified application identity:** Local AWS profile marketing-lab resolves to arn:aws:iam::408852558600:user/marketing-lab-gab26g and successfully inspects both queues. IAM console confirms console access disabled and the customer-managed MarketingLabSQS-gab26g policy attached directly, containing exactly the five required SQS actions and only the two queue ARNs. The older payroll profile is not used by this application. DLQ retention is independently verified as 14 days, and both queues were empty at inspection.

[Insert E05-E07; record the actual queue URL/ARNs and approved profile arrangement, with no credentials.]

## 6. Publishing and processing

**Live publication verified:** At 19:31 Manila time on October 26, 2026, the first publisher run acknowledged 50 requests and the second run acknowledged zero. Local outbox shows 50 QUEUED requests for 50 distinct recipients, with 50 queued log events and unique request IDs. SQS reports 50 available and zero in-flight messages. Processing history is empty and the worker remains stopped for E10 screenshots. No real SMTP submission has occurred yet.

**Completed baseline:** Processing subsequently finished at approximately 19:54 Manila time: **50 SENT, zero SKIPPED, zero FAILED**. All SENT rows have SMTP acceptance details, all outcomes match their stored request and promo code, and all 50 have ordered queued, received, processing_started, sent and deleted traces. The queue returned to zero available and zero in-flight requests. A later duplicate test at 22:19:45 Manila produced duplicate_skipped followed by deleted, with the baseline staying at 50 SENT and no additional SMTP submission. The operator reports saving E11 screenshots.

Mailtrap account allowance was inspected after sign-in: the Sandbox card and sandbox usage show **0/50 monthly submissions**, leaving 50 for the baseline. Save the usage screenshot as E17. The user subsequently saved SMTP credentials privately, and `php tools/preflight.php smtp` passed: STARTTLS connection and SMTP authentication were accepted. This used connection/authentication only, with no email submission or quota consumption. The real 50-message campaign remains pending.

Before publishing, capture the dashboard baseline with the worker stopped. After enqueueing, verify 50 QUEUED rows and zero new sends on repeat publication. During processing, trace queued -> received -> processing_started -> sent -> deleted using request, campaign, customer, and SQS identifiers. The MIME Message-ID differs from the SQS MessageId.

Expected baseline: 50 SENT for the selected customers with PROMO15, no unresolved failures, no outcomes for nonselected customers, and the main queue eventually empty. SENT means sandbox SMTP acceptance, not delivery to an actual inbox.

[Insert E10-E11, representative Mailtrap messages while retained, actual timestamps and final counts.]

## 7. Monitoring

**Cloud configuration verified:** MarketingLab-gab26g has all four required widgets, correct queue dimensions and Sydney Region, 60-second periods, Sum for operation counts, and Maximum for backlog/age. Both alarms have the required queue/metric, Maximum statistic, one-minute period, one-of-one evaluation, missing data not breaching, and enabled notification actions to MarketingLabAlerts-gab26g. Both were OK at inspection. The SNS topic's single EMAIL subscription is Confirmed. Live processing trends and incident notification delivery are still pending. See AWS-SETUP-VERIFIED.md for the configuration verification record.

The dashboard combines main queue send/receive/delete operation counts, visible/in-flight backlog, oldest-message age, and DLQ visible backlog. Counts use Sum; backlog and age use Maximum, with one-minute periods. Age uses a separate seconds widget. The DLQ alarm triggers at at least one visible message; the backlog alarm triggers at an age of at least 300 seconds. Both use one evaluation period and missing data as not breaching. Record confirmed SNS notification evidence or the actual classroom restriction.

CloudWatch counts are approximate operational indicators. Database history establishes unique campaign/customer outcomes, while structured local events establish processing and acknowledgment order.

[Insert E08-E09 and backlog trend from E11.]

## 8. Incident record

**Incident execution: PENDING.** Do not describe the expected sequence below as an observed result.

| Field | Record after the actual test |
|---|---|
| Detection | [UTC timestamp, processing_failed event, DLQ metric/alarm] |
| Identification | [SQS MessageId; malformed payload lacks requestId/customerId/promoCode] |
| Symptoms and impact | [Retries, no email submission, whether other work continued] |
| Root cause | Missing required job fields; attach actual validation/log evidence. |
| Corrective action | Submit a complete new CAMP-RECOVERY request for opted-out CUST-08001. Do not redrive the unchanged invalid payload. |
| Recovery evidence | [SKIPPED reason, deleted event, original malformed DLQ message removed after verification, backlog zero, alarm OK] |
| Prevention | Validate producer payloads before publication and retain structured phase/identifier logs alongside the DLQ alarm. |

[Insert E13-E14. Keep diagnostic outcomes separate from the 50 SENT baseline.]

## 9. Test results

| Test/action | Expected result | Actual result | Evidence |
|---|---|---|---|
| Local schema constraints | Correct keys, consent and status restrictions | PASS on actual MySQL 8.4.13 | E01, E15 |
| Population generation | 10,000 / 8,000 opted in / 2,000 opted out | PASS locally | E02 |
| Repeat generation | No extra records; existing values preserved | PASS automated and repeated local run | E02, E15 |
| Physically offline generation/inspection | Same counts without internet | Operator reports disconnected demonstration and screenshots completed; attach evidence | E03 |
| Random eligible selection | 50 distinct, zero ineligible | PASS on actual database | E04 |
| Repeat and offline preparation | Same IDs/code; 50 PENDING; no network calls | Stable local repeat PASS; physical offline evidence PENDING | E03-E04 |
| Publish and repeat | 50 QUEUED, second run sends zero | PASS live: first run 50, repeat 0, queue visible 50/in-flight 0 | E10, E15 |
| Process campaign | 50 SENT with promo, correct acknowledgments | PASS live: 50 SENT, zero SKIPPED/FAILED, all 50 ordered traces, queue empty | E11, E15 |
| Sequential duplicate | duplicate_skipped, deleted, no SMTP | PASS live at 22:19:45 Manila: duplicate skipped and deleted; baseline stayed 50 SENT; queue empty | E12, E15 |
| SMTP and delete failure behavior | Retry without premature acknowledgment or repeat terminal send | PASS with simulated external failures | E15 |
| Invalid message and DLQ | Retries, DLQ arrival and alarm | Local invalid rejection PASS; live PENDING | E13, E15 |
| Recovery and consent | Opted-out SKIPPED, acknowledgment, no SMTP, incident cleared | Mocked behavior PASS; live PENDING | E14, E15 |
| Log privacy | No payload content, credentials, or malformed PII identifiers | PASS automated regression | E15 |

The automated suite currently reports **10 tests, zero failures**. Separate real-server tests verify schema constraints and stable selection in marketing_lab_test. Neither result substitutes for live AWS, Mailtrap, or physically offline evidence.

## 10. Reflection and GitHub repository

### Personal reflection - 150-200 words

[Write in your own words after completing the activity. Explain how the objects and local/cloud components cooperated; what you learned from Faker and stable selection; how you distinguished arrival, processing, acknowledgment and failure; and which actual log/metric helped diagnose the incident and what you would improve.]

### GitHub Repository

https://github.com/gab26g/sam-hybrid-promo-lab

## Final submission reminders

- Fill name, student number, section, and group members where applicable.
- Replace every PENDING result only after collecting its actual evidence.
- Add numbered readable screenshots and one- or two-sentence captions.
- Complete your 150-200-word reflection.
- Paste the verified GitHub URL and ensure the instructor can open it.
- Share the Google Doc with the instructor and submit its link through the class channel.
- Do not upload the database, generated customer files, runtime logs, or credentials.
