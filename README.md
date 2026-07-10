# RAKIT

**RAKIT — RAP & Katalog Intelligence Toolkit**

Developed by **Muhammad Raihan Nabawi**.

RAKIT is a catalog-driven RAP authoring and historical pricing-intelligence system. It standardizes reusable work items behind permanent Catalog IDs, maps alternate descriptions as aliases, imports historical RAP Excel files through a reviewed staging workflow, accumulates trusted price observations, assists users with historical price guidance, builds RAP documents, and generates final Excel outputs through versioned templates.

## Core principle

> RAKIT advises; humans price.

RAKIT does not autonomously choose the final RAP price. Users manually enter prices. Prices from finalized RAPs and finalized reviewed imports become trusted observations that improve future historical guidance.

## Repository intent

This repository contains the generic RAKIT application and synthetic demonstration data only. Organization-specific RAP files, prices, project data, customer data, and Excel templates must remain outside the public repository.

## Documentation source of truth

When documents appear to conflict, use this precedence order:

1. `DOMAIN_INVARIANTS.md`
2. `STATE_MACHINES.md`
3. `DATABASE_INTEGRITY.md`
4. Domain-specific policy documents
5. `ARCHITECTURE.md`
6. Phase implementation contracts under `docs/phases/`
7. General descriptive documents

A phase document may add implementation detail, but it must not weaken a higher-precedence invariant.

## Implementation status

Planning / Phase 0. Application code has not started.

See `PHASES.md`.
