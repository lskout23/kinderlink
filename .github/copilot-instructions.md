# KinderLink workspace setup

- [x] Verify workspace instructions exist.
- [x] Clarify requirements: independent PHP/MySQL fresh installation, compact UI, new KinderLink branding, no school data or credentials.
- [x] Scaffold project: selectively copied current source into KinderLink; original application remains untouched.
- [x] Customize configuration, complete 18-table fresh schema, secure administrator provisioning, branding and original SVG/PNG icons.
- [x] Extensions: no additional extensions required.
- [x] Validate: PHP lint (51 files), 134 regression assertions, 10 responsive screen checks, PWA isolation and Apache protection passed. Gitleaks found no secrets.
- [x] Created portable VS Code preview task. Task lookup was unavailable from parent workspace; started equivalent loopback preview directly.
- [x] Launched isolated preview at http://127.0.0.1:8093/; no live installation/database configured.
- [x] Completed README, INSTALL and USER_MANUAL. Original tracked application is unchanged; no commit/push/deployment.

## Boundaries

- Edit only this KinderLink folder for this project. Never modify the parent application, its environment, database or production deployment.
- Keep secrets, uploads, school records and database dumps out of source control and distribution.
- Fresh installs use a dedicated empty database, virtual email mode and no shared administrator password.
- PHP 8.3+, MySQL 8.0+, Apache with mod_rewrite. Keep the existing framework-free PHP architecture.
- Tests must use randomly named disposable databases and must never send real email.
- UI preview is loopback-only with fictional data and no writes.
- No automatic Git commit, push or deployment.