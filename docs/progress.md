# Implementation ledger

Plan: user-approved Hybrid Promotional Campaign Laboratory, 2026-10-26.
Specification: C:/Users/Gab/Downloads/PHP-SQS-Mailtrap-Laboratory.pdf, 24 pages.

- Project isolated in marketing-email-lab, ignored by the parent payroll repository.
- Ruling: use a new independent repository rather than a payroll worktree because these are unrelated applications; this follows the approved plan.
- Local environment: PHP 8.2.12, PDO MySQL/SQLite, OpenSSL, mbstring, Composer available.
- XAMPP database binary is MariaDB 10.4.32; no instructor approval confirmed. MySQL 8 is preferred.
- AWS profile payroll-lab exists, but its permissions are unverified; browser session is signed out.
- AWS CLI not on PATH. Mailtrap account and remaining allowance unverified.
- Tests must mock SMTP/SQS boundaries before the baseline so no sandbox submissions are consumed.
- Shared interfaces: campaign/outbox -> six-field job -> publisher -> processor/history -> worker acknowledgment; preserve campaign/customer terminal outcomes and receipt handles.
- Runtime database, credentials, logs and evidence remain ignored and local.
- User confirmed MariaDB is instructor-approved and requested blank report identity fields.
- User prefers to perform AWS console actions hands-on; no AWS provisioning by agent. Guide prepared in docs/AWS-HANDS-ON.md.
- XAMPP MariaDB returned Aria checksum error on GRANT. Ruling: do not repair existing system tables; isolated official MySQL 8.4.13 at localhost:3308 used instead. Archive MD5 matched official download page.
- Application classes, schema and entry scripts implemented. Initial 9 tests failed for absent classes; then passed outside sandbox (Windows is_readable false inside sandbox prevented AWS SDK loading).
- Independent review found malformed identifier logging risk. Regression failed before fix and passed after metadata value validation. Final suite: 10 tests, 0 failures.
- Review's concurrent preparation concern is outside spec: guide explicitly requires one preparation process. Documented that constraint rather than adding a new locking subsystem.
- Real MySQL schema checks PASS in dedicated marketing_lab_test: exact population, CHECK, UNIQUE, foreign key, ENUM, unknown-customer history, stable selection.
- Actual marketing_lab: 10,000 customers (8000/2000), 50 PENDING distinct eligible CAMP-001/PROMO15 requests. No live SQS or SMTP actions performed.
- Portable MySQL root and application passwords randomly generated into ignored local storage; no credential values printed.
- AWS CLI installed privately; browser console identity sam10-sqs-user. Existing local payroll-lab profile verifies as gab-payroll-deployer; do not assume it is the restricted application identity.
- GitHub browser is signed in; CLI device login offered. Mailtrap browser opened to security verification. Quota and SMTP configuration pending.
- Physical internet-disconnected demonstration, cloud baseline, live processing, incident recovery, publication and final submission remain pending.
- PHP syntax verification: all 24 project PHP files passed.
- Precommit scan staged 39 source/documentation files. Two generic credential-assignment matches were manually reviewed: EmailSender assigns an injected settings value to PHPMailer's Password property; setup-local-db reads an ignored local setting or generates random bytes. Neither match contains a credential literal. Confirmed/credible secrets: none in staged content. Private .local environment/admin files, evidence, and vendor are ignored and untracked. Pattern scan alone does not establish absence of all secrets.
- Publication status: local implementation can be committed after contextual review; remote repository and authentication remain pending. User continues AWS setup hands-on.
