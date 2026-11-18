# YCC Hub documentation

Everything written for YCC Hub, the club management system for the Young Conservationist Club (YCC), Universiti Malaysia Terengganu.

## For users

| Document | What it covers |
|----------|----------------|
| [System User Manual (PDF)](manual/YCC_Hub_System_User_Manual.pdf) · [Word](manual/YCC_Hub_System_User_Manual.docx) | Step-by-step guide with 63 screenshots for members, the MT, the superadmin and the advisor, plus troubleshooting. Version 1.0, 2 November 2026. |

Not in manual version 1.0 yet (added after it was written):
- **Event report**: MT › Manage events or an event's Attendance page › Event report, then Print / Save as PDF.
- **Phone notifications**: members turn them on in My profile › Phone notifications. On iPhone the app must be added to the home screen first (iOS 16.4+).
- **Cron job card** in Settings, showing whether the background jobs are running.

## Project documents

Written at handover. The editable originals live in Claude Docs; these PDFs are snapshots.

| No. | Document | What it covers |
|-----|----------|----------------|
| 1 | [PRD: Product Requirements](project/1-PRD-Product-Requirements.pdf) | The problem, goals, roles, functional requirements (FR-01 to FR-19), out of scope |
| 2 | [TRD: Technical Requirements](project/2-TRD-Technical-Requirements.pdf) | Architecture, technology stack, hosting and scheduled jobs, security and access, integrations, non-functional requirements |
| 3 | [AFD: App Flow](project/3-AFD-App-Flow.pdf) | Site map, member journey, event lifecycle, activity status states, key screens |
| 4 | [Design Brief](project/4-Design-Brief.pdf) | Design direction and brand, colour palette, typography and components, mobile-first layout and accessibility, screens |
| 5 | [BSD: Backend Schema](project/5-BSD-Backend-Schema.pdf) | Data model (ER diagram), table reference, business rules stored in data, migrations and data safety |
| 6 | [IPD: Implementation Plan](project/6-IPD-Implementation-Plan.pdf) | Delivery timeline, release history, deployment procedure, handover and support plan, roadmap, risks |

## For developers

- [Install and setup](../README.md): first install on cPanel, local setup, Instagram feed, privacy.
- [Deployment notes](DEPLOYMENT.md): how updates are shipped, the cron job, and the server's limits.
- Tests: `vendor/bin/phpunit` (77 tests).
