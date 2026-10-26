# AWS setup verification - Step 7

Verified on October 26, 2026 using read-only AWS API calls and the signed-in AWS console. No resources were changed and no SQS/SMTP messages were sent.

## Queues and application credentials

- Local profile marketing-lab identifies arn:aws:iam::408852558600:user/marketing-lab-gab26g.
- That profile successfully retrieved both queue configurations.
- Main queue MarketingEmailQueue-gab26g: Standard; visibility 180 seconds; retention 345600 seconds (four days); receive wait 20 seconds; delay zero; SSE-SQS encryption enabled; account-owner restricted resource policy.
- Main redrive policy: MarketingEmailDLQ-gab26g with maxReceiveCount 3.
- DLQ: retention 1209600 seconds (14 days), SSE-SQS encryption enabled, account-owner restricted resource policy.
- Both queues had zero visible, in-flight, and delayed messages at inspection.
- IAM console: marketing-lab-gab26g console access disabled. Exactly one attached customer-managed policy, MarketingLabSQS-gab26g, attached directly; no permissions boundary set.
- Policy JSON: exactly SendMessage, ReceiveMessage, DeleteMessage, GetQueueAttributes, GetQueueUrl, limited to the main and DLQ ARNs. No broad administrator/SQS-full-access policy was present in the user's policy list.
- The PHP application's ignored local settings now explicitly select AWS_PROFILE=marketing-lab and the verified main queue URL.

## Dashboard

MarketingLab-gab26g exists and is saved. Source inspected in the view/edit-source dialog without updating or saving it. All metric widgets use ap-southeast-2 and 60-second periods:

| Widget | Queue | Metrics | Statistic |
|---|---|---|---|
| Main Queue Operations | Main | NumberOfMessagesSent, NumberOfMessagesReceived, NumberOfMessagesDeleted | Sum |
| Main backlog | Main | ApproximateNumberOfMessagesVisible, ApproximateNumberOfMessagesNotVisible | Maximum |
| Oldest Message Age | Main | ApproximateAgeOfOldestMessage | Maximum |
| DLQ Visible Backlog | DLQ | ApproximateNumberOfMessagesVisible | Maximum |

Age is separate from message-count widgets.

## Alarms and SNS

| Setting | MarketingLab-DLQ-gab26g | MarketingLab-Backlog-gab26g |
|---|---|---|
| Queue | MarketingEmailDLQ-gab26g | MarketingEmailQueue-gab26g |
| Metric | ApproximateNumberOfMessagesVisible | ApproximateAgeOfOldestMessage |
| Statistic | Maximum | Maximum |
| Period | 1 minute | 1 minute |
| Threshold | >= 1 | >= 300 seconds |
| Evaluation | 1 out of 1 | 1 out of 1 |
| Missing data | Good / not breaching | Good / not breaching |
| State at inspection | OK | OK |
| Actions | Enabled | Enabled |
| In-alarm notification | MarketingLabAlerts-gab26g | MarketingLabAlerts-gab26g |

SNS topic arn:aws:sns:ap-southeast-2:408852558600:MarketingLabAlerts-gab26g has one EMAIL subscription with status **Confirmed**. Email endpoint omitted here for privacy. No notification was deliberately sent by this verification.

## Evidence and next gate

Actual queue/API verification is stored locally in evidence/aws-setup-verification.json. Its AccessDenied entries for IAM/CloudWatch belong to the older payroll deployer profile; these areas were independently verified using the approved signed-in console identity instead. Do not grant more access to the application user to resolve those unrelated read restrictions.

Capture or retain E05-E09 screenshots of your queue configurations, policy, dashboard baseline, alarm details, and confirmed subscription. Exclude access keys and secrets. Configuration success does not yet prove alarm delivery during an incident or SMTP acceptance.

Next: confirm at least 50 Mailtrap sandbox submissions remain, configure SMTP privately, complete offline evidence, then capture the monitoring baseline before publishing. Overall plan estimate: approximately 60%, with physical offline evidence and all live campaign/incident tests still pending.
