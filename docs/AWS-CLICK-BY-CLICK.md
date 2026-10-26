# AWS click-by-click guide

You can perform these console steps while the local application and report are being prepared. Complete sections 1-4 first. Do not send messages, start a worker, purge a queue, or delete any classroom resource during setup.

Console labels for queue creation were checked against your current signed-in AWS screen on October 26, 2026. Your account is shared with other students, so use only names ending in **gab26g**. The observed console identity is **sam10-sqs-user**, account **408852558600**. Being signed in does not prove IAM/CloudWatch setup permission.

## 1. Create the dead-letter queue (about 5 minutes)

1. Open the **AWS** tab beside the chat. It is already on **SQS > Create queue**; no resource has been created by the agent.
2. Look at the top-right Region selector, immediately left of the account/user menu. It must say **Asia Pacific (Sydney)**. If it does not, click it and select Sydney, code **ap-southeast-2**.
3. If you are on the AWS home page instead, open https://ap-southeast-2.console.aws.amazon.com/sqs/v3/home?region=ap-southeast-2#/queues . Click **Create queue**, above the queue list on the right.
4. In the **Details** section, leave the **Standard** radio option selected. Do not choose FIFO.
5. In **Name**, type exactly `MarketingEmailDLQ-gab26g`.
6. Scroll to **Configuration**. Find **Message retention period**. Change its number from `4` to `14`. Its unit dropdown must say **Days**.
7. Leave the other Configuration values at their defaults for this DLQ.
8. In **Encryption**, keep **Server-side encryption: Enabled** and **Amazon SQS key (SSE-SQS)** selected.
9. In **Access policy**, leave **Basic** selected. For both send and receive, keep **Only the queue owner**. Do not add public principals or other student identities.
10. Leave **Redrive allow policy - Optional** at its default. This is different from the next **Dead-letter queue - Optional** section.
11. In **Dead-letter queue - Optional**, leave **Disabled** for this DLQ itself. You will enable this setting on the main queue later.
12. Leave **Tags - Optional** empty. Scroll to the bottom-right and click the orange **Create queue** button.
13. After creation, the queue details page should show its exact name. Under **Details**, find **Amazon Resource Name (ARN)** and click its copy icon. Save that ARN in a local note. It should end in `:MarketingEmailDLQ-gab26g`.
14. Capture a screenshot showing this queue's name, type, ARN, and 14-day retention. Label it **E05-DLQ**. Do not capture credentials.

Expected ARN: `arn:aws:sqs:ap-southeast-2:408852558600:MarketingEmailDLQ-gab26g`.

## 2. Create the main queue (about 5-10 minutes)

1. Click **Queues** in the breadcrumb near the top-left of the content area. This returns to the queue list.
2. Click **Create queue** above the list on the right.
3. Under **Details**, choose **Standard** and enter `MarketingEmailQueue-gab26g` in **Name**.
4. Scroll to **Configuration** and set:

| Field | Number | Unit |
|---|---:|---|
| Visibility timeout | 180 | Seconds |
| Message retention period | 4 | Days |
| Delivery delay | 0 | Seconds |
| Receive message wait time | 20 | Seconds |

5. Leave **Maximum message size** unchanged.
6. Keep **Encryption > Enabled > Amazon SQS key (SSE-SQS)**.
7. Keep the **Basic** access policy and both **Only the queue owner** selections.
8. Scroll past **Redrive allow policy - Optional**; leave it unchanged. Find the separate heading **Dead-letter queue - Optional** immediately below it.
9. In **Dead-letter queue - Optional**, click **Enabled**. This expands the destination controls.
10. Open the destination queue selector and choose **MarketingEmailDLQ-gab26g**. Depending on the console layout, it may display the ARN. Select the ARN ending in this exact name.
11. In **Maximum receives**, enter `3`.
12. Capture the configuration/redrive fields as **E06**. Then scroll to the bottom-right and click **Create queue**.
13. On the resulting details page, copy **URL** and **Amazon Resource Name (ARN)** using their copy icons. Save them beside the DLQ ARN.
14. Under **Configuration** (or the equivalent details area), verify 180-second visibility, four-day retention, and 20-second wait. Open the **Dead-letter queue** tab/section and verify the correct DLQ and maximum receives three.

Expected main URL: `https://sqs.ap-southeast-2.amazonaws.com/408852558600/MarketingEmailQueue-gab26g`.

Expected main ARN: `arn:aws:sqs:ap-southeast-2:408852558600:MarketingEmailQueue-gab26g`.

**Checkpoint:** send the agent the actual main URL and both ARNs. These are safe identifiers. Stop here temporarily if anything is denied or the settings differ.

## 3. Configure the IAM application identity (about 10-20 minutes)

The guide calls for an IAM application user with a restricted queue policy. Use your approved setup identity for this step. Your instructor may have supplied sam10-sqs-user as an approved alternative; if so, confirm the arrangement and document it rather than inventing new permissions.

### 3A. Create the restricted policy

1. Open a separate browser tab so your SQS page stays available.
2. Go to https://console.aws.amazon.com/iam/ . IAM is global, so its Region display may differ; your SQS ARNs must still use Sydney.
3. In IAM's left navigation, find **Access management**, then click **Policies**. If the left navigation is hidden, click the three-line menu near the upper-left of the content area.
4. Click **Create policy** near the upper-right.
5. On the permissions editor, select the **JSON** tab instead of Visual.
6. Replace the editor's contents with the JSON below, after confirming the two ARN values match the queues you actually created:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "sqs:SendMessage",
        "sqs:ReceiveMessage",
        "sqs:DeleteMessage",
        "sqs:GetQueueAttributes",
        "sqs:GetQueueUrl"
      ],
      "Resource": [
        "arn:aws:sqs:ap-southeast-2:408852558600:MarketingEmailQueue-gab26g",
        "arn:aws:sqs:ap-southeast-2:408852558600:MarketingEmailDLQ-gab26g"
      ]
    }
  ]
}
```

7. Click **Next**, usually at the bottom-right.
8. In **Policy name**, enter `MarketingLabSQS-gab26g`.
9. Review that the resources are only your two queues. Click **Create policy**.

### 3B. Create and configure the user

1. In IAM's left navigation, click **Users**.
2. Click **Create user** near the upper-right.
3. Enter `marketing-lab-gab26g` in **User name**.
4. Leave **Provide user access to the AWS Management Console** unchecked. Click **Next**.
5. On **Set permissions**, choose **Attach policies directly**.
6. In the policy search box, type `MarketingLabSQS-gab26g`, press Enter if needed, and select the checkbox next to that exact policy.
7. Click **Next**, review the name and attached policy, then click **Create user**.
8. Click the new user's name to open its details.
9. Open the **Permissions** tab and capture the user name and attached policy as **E07**.
10. Open the **Security credentials** tab. Scroll to **Access keys** and click **Create access key**.
11. Choose **Local code** or the matching command-line use case shown. Follow the acknowledgment prompt and click **Next**. An optional description can be `Hybrid promotion lab`.
12. Click **Create access key**. This shows sensitive values. Do not screenshot this page or paste either value into chat.
13. Open PowerShell in the local marketing-email-lab folder and run:

```powershell
.\tools\aws.ps1 configure --profile marketing-lab
```

14. Enter the access key ID and secret directly into those terminal prompts. Use `ap-southeast-2` for Region and `json` for output. Keep this terminal/private credential entry out of evidence screenshots.
15. Run:

```powershell
.\tools\aws.ps1 sts get-caller-identity --profile marketing-lab
.\tools\aws.ps1 sqs get-queue-attributes --profile marketing-lab --region ap-southeast-2 --queue-url https://sqs.ap-southeast-2.amazonaws.com/408852558600/MarketingEmailQueue-gab26g --attribute-names QueueArn
```

16. Capture the successful identity and queue ARN result, with no keys. Tell the agent the profile is configured so it can verify the application's selected profile before any send.

**If AccessDenied appears:** record the denied action, resource and error, and tell the agent/instructor. Do not attach AdministratorAccess, change another student's identity, or create replacement credentials through an unapproved account.

## 4. Create the CloudWatch dashboard (about 15-20 minutes)

Use the approved setup identity, not the restricted application user.

1. Open https://ap-southeast-2.console.aws.amazon.com/cloudwatch/home?region=ap-southeast-2 in a new tab.
2. Confirm **Sydney** in the top-right Region selector.
3. In the left navigation, click **Dashboards**. Expand the three-line navigation menu if necessary.
4. Click **Create dashboard**.
5. Enter `MarketingLab-gab26g`, then click **Create dashboard**.
6. In the widget dialog choose **Line** (sometimes **Line graph**) and click **Next**. If a data-source choice appears, select **Metrics**.
7. In **Add metric graph**, use **Browse** or **All metrics**. Click **SQS**, then the group named **Queue Metrics** or **QueueName**.
8. Search for `MarketingEmailQueue-gab26g` and select only its rows for:
   - NumberOfMessagesSent
   - NumberOfMessagesReceived
   - NumberOfMessagesDeleted
9. Open **Graphed metrics**. For each selected row set **Statistic = Sum**, **Period = 1 minute**.
10. Click **Create widget**. If widget title can be edited from the three-dot menu, call it `Main queue operations`.
11. Click **Add widget**, choose **Line / Metrics**, and repeat the selection process for the main queue's ApproximateNumberOfMessagesVisible and ApproximateNumberOfMessagesNotVisible. On **Graphed metrics**, set **Maximum**, **1 minute**. Title: `Main queue backlog`.
12. Add another **Line / Metrics** widget for the main queue's ApproximateAgeOfOldestMessage. Set **Maximum**, **1 minute**. Title: `Oldest message age (seconds)`. Keep it separate from message counts.
13. Add another **Line / Metrics** widget for **MarketingEmailDLQ-gab26g > ApproximateNumberOfMessagesVisible**. Set **Maximum**, **1 minute**. Title: `DLQ visible backlog`.
14. Click **Save dashboard** in the top-right.
15. Capture the dashboard before publication as **E08**. A new/idle queue can initially show no metrics; wait several minutes and refresh. Do not send dummy messages to make the graph populate.

The DLQ arrival indicator is **ApproximateNumberOfMessagesVisible**, not NumberOfMessagesSent.

## 5. Create the DLQ alarm and email subscription (about 10 minutes)

1. Stay in CloudWatch. In the left navigation expand **Alarms**, then click **All alarms**.
2. Click **Create alarm**, usually near the upper-right.
3. Click **Select metric**.
4. Choose **SQS > Queue Metrics** (or **QueueName**).
5. Search for `MarketingEmailDLQ-gab26g`. Select only **ApproximateNumberOfMessagesVisible** for that queue, then click **Select metric** at the bottom.
6. Under metric settings, select **Statistic = Maximum** and **Period = 1 minute**.
7. Under **Conditions**, choose **Threshold type = Static**.
8. For the comparison select **Greater/Equal (>=)** and enter `1` as the threshold.
9. Expand **Additional configuration**. Set **Datapoints to alarm = 1 out of 1**. Under **Missing data treatment**, select **Treat missing data as good (not breaching threshold)** or its equivalent not-breaching wording.
10. Click **Next**.
11. In **Configure actions**, choose alarm state **In alarm**.
12. Under **Send a notification to the following SNS topic**, select **Create new topic** if an approved topic does not already exist.
13. Topic name: `MarketingLabAlerts-gab26g`. Enter your own approved email under **Email endpoints that will receive the notification**. Click **Create topic** if that button appears.
14. Open your inbox and find the AWS notification subscription email. Click **Confirm subscription** yourself. Do not share the confirmation URL or sign-in details.
15. Return to CloudWatch and click **Next**. For **Alarm name**, enter `MarketingLab-DLQ-gab26g`.
16. Click **Next**, review the metric, queue, threshold, missing-data behavior and notification topic, then click **Create alarm**.
17. Capture alarm configuration and initial state as **E09-DLQ**. An initial INSUFFICIENT_DATA state can occur while metrics/evaluation catch up; record the actual state.

If SNS creation is restricted, record the restriction and the instructor-approved notification method. Still create/capture the alarm if permitted; do not claim an email subscription was confirmed when it was not.

## 6. Create the backlog-age alarm (about 5-10 minutes)

1. Return to **Alarms > All alarms**, click **Create alarm > Select metric**.
2. Choose **SQS > Queue Metrics** and select **MarketingEmailQueue-gab26g > ApproximateAgeOfOldestMessage**.
3. Set **Statistic = Maximum**, **Period = 1 minute**.
4. Under Conditions choose **Static**, **Greater/Equal (>=)**, threshold `300` seconds.
5. Expand Additional configuration: **1 out of 1** datapoints, missing data **not breaching**.
6. Click **Next** and select **In alarm**, then **Select an existing SNS topic**. Choose `MarketingLabAlerts-gab26g` or the approved topic used above.
7. Click **Next**, name the alarm `MarketingLab-Backlog-gab26g`, click **Next**, review, then **Create alarm**.
8. Capture the configuration/state as **E09-Backlog**.

You do not need to deliberately trigger this backlog alarm before the baseline. Its configuration is required; the controlled incident later exercises the DLQ alarm.

## 7. Stop and report setup readiness

Send the agent:

- Actual main queue URL and both ARNs.
- Whether the application IAM profile is configured, or the exact restriction/instructor-approved alternative.
- Whether the dashboard and both alarms exist.
- Whether SNS subscription is confirmed, or why it is unavailable.

Do not send access keys, passwords, downloaded credential files, or SMTP credentials. Keep worker stopped and messages unsent until local SMTP configuration and monitoring baseline are verified.

## 8. Later AWS actions - only when the agent confirms readiness

### Observe baseline

1. The agent publishes the 50 local requests with the worker stopped.
2. In SQS > Queues, open only MarketingEmailQueue-gab26g. Use queue details/counts and CloudWatch to observe backlog. Do not click **Poll for messages** to count them.
3. Capture the backlog and local 50 QUEUED result as E10.
4. After the worker starts, capture the CloudWatch backlog trend. Capture representative Mailtrap messages while retained. Final local campaign result must be 50 SENT.

### Duplicate test

1. The agent prepares the actual completed job with `php tools/diagnostic-payload.php duplicate`.
2. Open SQS > your main queue > **Send and receive messages**.
3. Paste the supplied job in **Message body**, then click **Send message**. Do not edit its campaign/customer pair.
4. Verify duplicate_skipped and deleted evidence with the agent; no new email should be sent.

### Controlled failure

1. After baseline and duplicate checks, open the main queue's **Send and receive messages** page.
2. In **Message body**, paste exactly:

```json
{"campaignId":"CAMP-BROKEN","subject":"Test","body":"Missing IDs"}
```

3. Click **Send message** once. Keep worker running. Do not click Poll for messages on the main queue.
4. Allow several 180-second visibility intervals, plus CloudWatch metric delay. The agent records local failure events.
5. When the DLQ count is nonzero, open **MarketingEmailDLQ-gab26g > Send and receive messages > Poll for messages**. Here one deliberate receive is for inspecting the incident, not estimating baseline counts.
6. Select the malformed message and inspect its body. Capture its SQS ID, body, receive-count information if shown, DLQ backlog, alarm ALARM state, and notification if received as E13.

### Recovery and resolution

1. The agent supplies a complete recovery payload generated by `php tools/diagnostic-payload.php recovery`.
2. Send it once to the main queue through **Send and receive messages**. Its customer must be CUST-08001 and campaign CAMP-RECOVERY.
3. The agent verifies SKIPPED and deleted with zero SMTP submissions for that request.
4. Return to the DLQ. Identify only the original CAMP-BROKEN malformed test message, select it, click **Delete**, and confirm the exact selected test message. Do not click Purge or delete queues.
5. Wait for metric evaluation, then capture DLQ visible backlog zero and the DLQ alarm returning to OK as E14.

## References

- Activity requirements: PHP-SQS-Mailtrap-Laboratory.pdf, especially pages 11-23.
- AWS queue creation: https://docs.aws.amazon.com/AWSSimpleQueueService/latest/SQSDeveloperGuide/creating-sqs-standard-queues.html
- AWS CloudWatch dashboards: https://docs.aws.amazon.com/AmazonCloudWatch/latest/monitoring/create_dashboard.html
- AWS SQS alarms: https://docs.aws.amazon.com/AWSSimpleQueueService/latest/SQSDeveloperGuide/set-cloudwatch-alarms-for-metrics.html
