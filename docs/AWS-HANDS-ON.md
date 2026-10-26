# AWS console checklist - you operate, I verify

Region: **Asia Pacific (Sydney), ap-southeast-2**. Resource suffix: **gab26g**.
Use the instructor-approved setup identity. Do not change unrelated classroom resources.

## 1. Queue pair

Create DLQ first:

| Setting | Value |
|---|---|
| Name | MarketingEmailDLQ-gab26g |
| Type | Standard |
| Message retention | 14 days |
| Access | Restricted; no public access |
| Encryption | Keep enabled/default |

Create main queue:

| Setting | Value |
|---|---|
| Name | MarketingEmailQueue-gab26g |
| Type | Standard |
| Visibility timeout | 180 seconds |
| Message retention | 4 days |
| Receive message wait time | 20 seconds |
| Dead-letter queue | Enabled; select MarketingEmailDLQ-gab26g |
| Maximum receives | 3 |
| Access and encryption | Restricted; keep encryption enabled |

Capture queue details/configuration as E05 and E06. Share main queue URL and both ARNs with the implementing agent. These are identifiers, not passwords. Do not poll messages to count them.

## 2. Application identity

The signed-in user was sam10-sqs-user. This does not prove it has setup permissions.
If your instructor supplied this user for the activity, establish whether it is the approved substitute for creating your own application IAM user. Record the instructor-approved credential arrangement.

Otherwise, using the approved setup identity:

1. IAM > Policies > Create policy > JSON: use iam-policy.json after replacing YOUR_ACCOUNT_ID with the correct account ID. Keep only the two queue ARNs.
2. Name: MarketingLabSQS-gab26g.
3. IAM > Users > Create user: marketing-lab-gab26g. Console access disabled.
4. Attach only MarketingLabSQS-gab26g. Do not add AdministratorAccess or AmazonSQSFullAccess.
5. Create local-code access key and enter it directly into `tools/aws.ps1 configure --profile marketing-lab`. Never send the key through chat or include it in screenshots.
6. Verify `tools/aws.ps1 sts get-caller-identity --profile marketing-lab` and GetQueueAttributes on your queue.
7. Capture user/policy attachment and redacted identity check as E07.

If denied, save the error action and ask the instructor for the approved setup identity. Do not broaden your own policies to bypass classroom restrictions.

## 3. Dashboard BEFORE publishing

CloudWatch > Dashboards > Create: MarketingLab-gab26g.
Select AWS/SQS metrics with QueueName matching the new main queue.

| Metrics | Statistic | Period |
|---|---|---|
| NumberOfMessagesSent, NumberOfMessagesReceived, NumberOfMessagesDeleted | Sum | 1 minute |
| ApproximateNumberOfMessagesVisible, ApproximateNumberOfMessagesNotVisible | Maximum | 1 minute |
| ApproximateAgeOfOldestMessage | Maximum | 1 minute; separate widget for seconds |
| DLQ ApproximateNumberOfMessagesVisible | Maximum | 1 minute |

Save and capture baseline E08. New/inactive queues can take several minutes to show metrics.

## 4. Alarms and notifications

CloudWatch > Alarms > Create:

| Setting | DLQ alarm | Main backlog alarm |
|---|---|---|
| Name | MarketingLab-DLQ-gab26g | MarketingLab-Backlog-gab26g |
| Metric | DLQ ApproximateNumberOfMessagesVisible | Main ApproximateAgeOfOldestMessage |
| Statistic / period | Maximum / 1 minute | Maximum / 1 minute |
| Threshold | Greater than or equal to 1 | Greater than or equal to 300 seconds |
| Evaluation | 1 datapoint in 1 period | 1 datapoint in 1 period |
| Missing data | Not breaching | Not breaching |

Use an approved SNS email topic and confirm the subscription from your inbox. Capture E09. If SNS is restricted, document the restriction and capture alarm states anyway.

## 5. Baseline and incident - wait for local readiness

Keep worker stopped for publication. Confirm only this lab's messages use this queue pair.
After enqueueing, capture E10: 50 QUEUED rows, queued events, and visible backlog.
After worker starts, capture E11: dashboard trend and representative Mailtrap messages early, before retention removes them. Final baseline must have 50 SENT outcomes.

After baseline only, send this exact controlled payload in SQS > main queue > Send message:

```json
{"campaignId":"CAMP-BROKEN","subject":"Test","body":"Missing IDs"}
```

Keep worker running. Allow several 180-second visibility intervals; do not poll the main queue manually. Capture failures, DLQ arrival, and alarm as E13.

The corrected recovery payload must contain all six fields, use a new 32-character hex requestId, campaignId CAMP-RECOVERY, customerId CUST-08001, promoCode PROMO15, subject Diagnostic recovery, body Consent check only. The agent will prepare it locally. Expected: SKIPPED, deleted, no SMTP submission.

After verifying recovery, delete only the original malformed DLQ test message as incident cleanup. Capture DLQ zero and alarm OK as E14. Do not purge queues or delete unrelated messages.
