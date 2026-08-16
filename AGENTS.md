# THE EXPAT NETWORK — Agent Operating Instructions

This repository contains current strategy, implementation code, specifications, research rules and legacy material for THE EXPAT NETWORK.

## Start Here

Before making strategic, product, research, content, design or engineering decisions, read:

`docs/canonical/README.md`

Treat `docs/canonical/` as the current strategic source-of-truth layer.

## Source Precedence

When sources conflict, use this order:

1. current official / primary source for factual rules
2. current canonical TEN strategy in `docs/canonical/`
3. accepted ADRs
4. current domain, workflow and product specifications
5. source registry
6. live implementation / current code
7. latest research dossier
8. legacy strategy or audit material
9. AI-generated suggestions

Do not silently allow legacy documents or AI-generated suggestions to override higher-priority sources.

## Locked Portfolio Architecture

- `theexpatnetwork.org` = ORCHESTRATE
- `theexpatnetwork.de` = TOOLS
- Founder LinkedIn = AUTHORITY + RELATIONSHIPS
- TEN LinkedIn Page = INSTITUTIONAL IDENTITY
- Instagram = DISCOVERY + HUMAN TRUST

Do not change these roles unless an explicit strategic decision or ADR supersedes them.

## Core Orchestration Rule

For each user problem, route to the correct next-step class:

A. TEN information on `.org`  
B. TEN tool on `.de`  
C. official source/programme  
D. qualified specialist/partner

TEN explains and orchestrates.

Official bodies own official decisions.

Qualified, authorised or certified specialists own regulated or expert fulfilment.

## Official-Source-First

For immigration, residence, recognition, labour rules, tax, social security, insurance, government programmes and other high-volatility or regulated topics:

- verify against current primary or official sources;
- use exact dates where material;
- distinguish TEN explanation from official determination;
- do not invent eligibility or approval outcomes;
- record or display verification dates where required.

## Partner and Trust Boundaries

Never imply:

- endorsement;
- affiliation;
- cooperation;
- partnership;
- institutional support;

unless documented.

Never invent:

- partner logos;
- testimonials;
- user counts;
- statistics;
- outcomes;
- institutional relationships.

## Product Boundaries

Do not build or recommend:

- generic job marketplace;
- ATS;
- employer or candidate dashboards;
- social network;
- AI auto-apply;
- generic visa bot;
- complex SaaS/CRM without validated need;
- empty SEO taxonomy pages;
- fake trust counters or partner walls;
- premature newsletter.

## Tool Doctrine

Prefer:

- low-cost MVPs;
- deterministic logic where practical;
- browser-local/stateless processing;
- no account by default;
- no unnecessary uploads;
- minimal personal data;
- transparent assumptions;
- transparent limitations;
- official-source links;
- human review where appropriate.

Do not add AI simply because it is available.

## Social Content Rule

Founder LinkedIn content should generally follow:

Evidence  
→ meaning  
→ practical action  
→ source  
→ relevant CTA

Avoid:

- unsupported labour-shortage claims;
- generic motivational filler;
- fake urgency;
- guaranteed outcomes;
- engagement bait;
- founder self-promotion without user value.

## Engineering Rule

Before changing production code:

1. confirm the feature belongs on the correct TEN surface;
2. check whether an official or specialist system already solves the problem better;
3. preserve the existing secure backend architecture unless a concrete defect requires change;
4. minimise data collection;
5. test before merge;
6. use a branch and pull request;
7. preserve rollback capability.

Never commit secrets, credentials or personal lead data.

## Scope-Control Test

Before recommending or implementing a feature, answer:

1. Which surface owns it?
2. Which user/stage does it serve?
3. What exact next action does it enable?
4. Does an official/specialist system already do it better?
5. Is TEN adding orchestration or merely duplicating the market?
6. Is it MVP NOW, NEXT, LATER or DO NOT BUILD?

If the answer is unclear, do not build.

## AI Rule

AI tools are execution accelerators, not sources of truth.

Any AI-generated output that conflicts with current official sources, canonical strategy or accepted ADRs must be rejected or explicitly reviewed before adoption.
