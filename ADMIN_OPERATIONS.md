# Admin operations

## Available modules

The admin dashboard now includes:

- Finance review of payment proofs, refund request tracking, and CSV export.
- Refund requests capped at confirmed payments, approval/rejection audit history, and external transfer references.
- Review and service-listing moderation with visibility filtering and recalculated public ratings.
- Existing admin role/status management, category management, subscription-plan management, and platform contact settings.
- Before/after values in the immutable admin audit log.
- Provider-rejection email notifications through Laravel's configured mail transport.

## Deployment

The additive migration `2026_10_14_000001_add_admin_operations_tables.php` adds moderation fields to reviews and services and creates `admin_refunds` and `app_settings`. It does not drop tables or rewrite existing records; existing reviews default to visible and existing services default to not admin-hidden.

On a staging database, run:

```sh
php artisan migrate --pretend
php artisan migrate
php artisan test
```

Review the SQL preview and take the normal database backup before applying this migration to production. Do not use `migrate:fresh` on a production database.

## Mail delivery

Rejection email is sent using Laravel Notifications. The repository's example mailer defaults to `log`, which does not deliver email. Configure these Railway variables with the mail provider's values to send real email:

```text
MAIL_MAILER=smtp
MAIL_HOST=<smtp-host>
MAIL_PORT=<smtp-port>
MAIL_USERNAME=<smtp-username>
MAIL_PASSWORD=<smtp-password>
MAIL_SCHEME=tls
MAIL_FROM_ADDRESS=<verified-sender-address>
MAIL_FROM_NAME=Farah
```

Do not commit mail credentials. Push notifications are not wired: the project does not currently have device-token registration and a push provider integration.

## Refund and subscription boundaries

Refunds are audited as requested, approved/rejected, and manually marked processed with an external transfer reference. The app does not transfer funds; configure a payment gateway integration before representing refunds as automatic.

Plan records can be managed in the admin panel. The provider subscription API currently grants the existing one-month trial rather than collecting payment for paid plans. Do not advertise paid in-app subscription charging as operational until a payment gateway and its webhook/reconciliation flow are implemented.

Team management changes roles and active status for existing administrator accounts. Create additional accounts with the secure `php artisan admin:create` command; passwords are not created or displayed by the team page.
