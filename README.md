# NullCare EMR

A safety-first Electronic Medical Record system built for the future MUST Teaching Hospital, developed by **Team Nullbit Technologies** for the MUST–GSL Electronic Medical Record Innovation Challenge.

Full-stack application: a Laravel (PHP) REST API backend and a React progressive web app frontend, covering registration, triage, consultation, laboratory, pharmacy, wards & ICU, dialysis, billing, and audit/governance — offline-capable and built around real patient-safety checks (allergy alerts, critical-result acknowledgement, sex-restricted ward validation) rather than just data entry.

## Getting started

See **[SETUP_GUIDE.md](SETUP_GUIDE.md)** for full local setup instructions, prerequisites, demo login credentials, and troubleshooting.

For more detail, see:
- [docs/PROJECT_DOCUMENTATION.md](docs/PROJECT_DOCUMENTATION.md) — full project guide
- [API_REFERENCE.md](API_REFERENCE.md) — API endpoint reference

## Stack

- **Backend:** Laravel (PHP), Sanctum token auth, MySQL
- **Frontend:** React (Vite), Tailwind CSS, offline-first PWA with local sync queue
- **Auth:** username + password, role-based access control enforced server-side on every route

## License

Built on the open-source Laravel framework (MIT licensed). This application and its source code are developed under the MUST–GSL EMR Innovation Challenge joint intellectual property framework — see the challenge brief for terms.
