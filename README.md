# Coremail Sync

Coremail Sync is a Nextcloud app that synchronizes Coremail CardDAV contacts and
CalDAV calendar items into native Nextcloud Contacts and Calendar data stores.

The app is intentionally one-way:

```text
Coremail DAV -> Nextcloud native Contacts / Calendar
```

## Deployment Modes

The app uses one codebase with two administrator-selected modes.

### Production / Public

Use this mode for public or app-store style deployments.

- HTTPS Coremail DAV URLs are required.
- Localhost, link-local, and private-network DAV hosts are blocked.
- TLS certificate verification is enabled.
- Detailed DAV discovery attempts are hidden from users.
- The Coremail DAV URL should be configured by an administrator.

### Demo / VM

Use this mode for local demos, VM testing, and temporary internal validation.

- HTTP Coremail DAV URLs are allowed.
- Private-network DAV hosts such as `172.16.19.250` are allowed.
- TLS certificate verification is disabled for compatibility with self-signed
  or local test deployments.
- DAV discovery attempts are shown in sync results to help debugging.
- Existing Coremail legacy DAV path probing remains enabled.

Demo mode is not intended as the public default.

## User Settings

Each user configures:

- Coremail account
- Coremail client password
- Optional Coremail DAV URL override
- Contacts/calendar sync toggles
- Cron interval

If the user DAV URL override is blank, the administrator's default Coremail DAV
URL is used.

## Current Scope

The current implementation is a Coremail sync bridge, not a generic DAV sync
engine. It keeps the existing Coremail legacy DAV path probing so existing VM
and internal test environments can continue to work while the public deployment
defaults become safer.
