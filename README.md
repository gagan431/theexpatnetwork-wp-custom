# The Expat Network — Custom WordPress & Project Repository

This repository contains the custom WordPress code, product specifications, canonical strategy documentation, QA material and release artefacts for **THE EXPAT NETWORK**.

## Start Here

For current product strategy and operating rules, read:

**[`docs/canonical/README.md`](docs/canonical/README.md)**

The `docs/canonical/` directory is the current strategic source-of-truth layer for THE EXPAT NETWORK.

Older strategy files outside that directory may be retained for historical or implementation context, but they must not override current canonical strategy, accepted ADRs or current official sources.

## Current Portfolio Architecture

- **theexpatnetwork.org = ORCHESTRATE**
  - UNDERSTAND → NAVIGATE → DISCOVER → CONNECT → CONTINUE

- **theexpatnetwork.de = TOOLS**
  - CHECK → ASSESS → CALCULATE → PREPARE

- **Founder LinkedIn = AUTHORITY + RELATIONSHIPS**
- **TEN LinkedIn Page = INSTITUTIONAL IDENTITY**
- **Instagram = DISCOVERY + HUMAN TRUST**

TEN explains and orchestrates.

Official bodies own official decisions.

Qualified, authorised or certified specialists own regulated or expert fulfilment.

## Repository Structure

### `docs/canonical/`

Current canonical strategy and operating documents, including:

- master strategy
- portfolio/domain roles
- accepted ADRs
- `.org` orchestration model
- `.de` tools strategy
- research/source policy
- legal and trust boundaries
- partnerships and monetisation rules
- social strategy
- KPI framework
- roadmap
- AI and automation governance

### `plugins/`

Custom WordPress plugin source.

Current production code must be changed only through a reviewed implementation workflow.

### `docs/specs/`

Implementation and product specifications.

### `docs/qa/`

QA and validation material.

### `docs/rollback/`

Rollback documentation.

### `codex/`

Engineering-agent prompts and related material.

### `releases/`

Packaged release artefacts.

## Source Precedence

When information conflicts, use this order:

1. current official / primary source for factual rules
2. current canonical TEN strategy in `docs/canonical/`
3. accepted ADRs
4. current domain, workflow and product specifications
5. source registry
6. live implementation / current code
7. latest research dossier
8. legacy strategy or audit material
9. AI-generated suggestions

## Core Product Boundaries

Do not build THE EXPAT NETWORK into:

- a generic job board
- an ATS
- employer or candidate dashboards
- a social network
- an AI auto-apply platform
- a generic visa bot
- a complex SaaS/CRM without validated need
- an unsupported programme directory
- a content farm

## Product Doctrine

Prefer:

- low-cost MVPs
- browser-local/stateless tools where practical
- no account by default
- no unnecessary uploads
- minimal personal data
- transparent assumptions and limitations
- official-source routing
- human review where appropriate

## Trust & Compliance

THE EXPAT NETWORK is not a government authority.

Do not claim:

- final visa or residence eligibility
- qualification-recognition outcomes
- guaranteed jobs
- guaranteed visas
- personalised regulated advice outside the appropriate authorised process
- institutional partnerships or endorsements that are not documented

High-volatility immigration, recognition, labour-market, tax, social-security and similar claims must be verified against current primary sources.

## Do Not Commit

Never commit:

- WordPress core
- production database backups
- production uploads
- cache files
- `wp-config.php`
- credentials
- API keys
- passwords
- personal lead data
- CVs or identity documents
- banking or health data
- tax identifiers
- confidential partner contracts
- private internal commercial schedules

## AI / Agent Rule

AI systems and coding agents should start with:

`docs/canonical/README.md`

AI output is an execution aid, not a source of truth.

Do not let legacy repository documents or AI-generated suggestions silently override canonical TEN decisions.
