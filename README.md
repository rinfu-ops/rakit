# RAKIT

### RAP & Katalog Intelligence Toolkit

RAKIT is a Laravel-based toolkit for building, managing, and learning from **Rencana Anggaran Pelaksanaan (RAP)** data.

It turns scattered item descriptions, inconsistent naming, and historical pricing into a structured Catalog that can support more reliable RAP preparation over time.

RAKIT does not automatically decide prices. It organizes trusted historical information so users can make better-informed decisions.

That philosophy is summarized in RAKIT’s core principle: 

> **RAKIT advises; humans price.**

Users remain responsible for entering and approving prices. Prices from finalized RAPs and reviewed, finalized imports become trusted observations that strengthen future historical guidance.

---

## What RAKIT Solves

RAP data commonly exists across many Excel files, with problems such as:

* The same item being written in several different ways
* Inconsistent units and descriptions
* Historical prices being difficult to search and compare
* Repeated manual work when preparing new RAP documents
* No permanent identity connecting similar items across projects

RAKIT introduces a permanent Catalog layer between historical RAP data and future RAP creation.

```text
Historical RAP data
        ↓
Catalog Items and Aliases
        ↓
Trusted Price Observations
        ↓
Price Guidance
        ↓
New RAP creation
        ↓
Excel generation and export
```

Human review remains part of every important decision.

---

## Core Capabilities

### Universal Catalog

Each approved item receives a permanent Catalog ID:

```text
[DISCIPLINE]-[ITEM TYPE]-[GROUP]-[SEQUENCE]
```

Example:

```text
EL-PKG-KBL-0042
```

Catalog identities are immutable and allocated through concurrency-safe database counters.

### Alias Mapping

Different descriptions can refer to the same Catalog Item.

```text
Cable termination work
Termination service for power cable
Power cable end termination
```

Aliases allow historical wording to remain traceable without creating duplicate master items.

### Historical Price Observations

RAKIT records trusted historical prices together with their context, including:

* Catalog Item
* Unit
* Quantity
* Currency
* Price basis
* Tax context
* Observation date
* Source identity

Prices remain historical evidence rather than automatic recommendations.

### Safe Baseline Import

Approved Catalog data can be imported through a controlled administrative workflow featuring:

* Strict `.xlsx` validation
* Formula rejection
* Bounded workbook size and row limits
* Idempotent reconciliation
* Source-drift detection
* Transactional rollback
* Exact integer and decimal handling
* Confidential logging protection

### Operational Safety

RAKIT supports two operational modes:

* `NORMAL`
* `READ_ONLY`

Critical Catalog mutations are blocked while the system is in read-only mode.

---

## Current Status

RAKIT is under active development.

| Phase                               | Status   |
| ----------------------------------- | -------- |
| Foundation and authentication       | Complete |
| Architecture and integrity skeleton | Complete |
| Baseline Catalog import             | Complete |
| Catalog management                  | Complete |
| Pricing foundation                  | Next     |
| RAP import and review               | Planned  |
| RAP Builder                         | Planned  |
| Excel generation and export         | Planned  |

The current version establishes the trusted Catalog foundation. User-facing Catalog management screens are still under development.

---

## Design Principles

RAKIT is built around several non-negotiable rules:

* Database constraints are the final source of truth
* Catalog identities must never be silently changed
* Trusted records must not be overwritten by changed source data
* Financial values must never use floating-point arithmetic
* Imports must fail completely rather than leave partial data
* Uploaded Excel files must be treated as untrusted input
* Users remain responsible for final prices and approvals
* Critical actions must be attributable through Audit Events

---

## Technology

* Laravel 13
* PHP 8.3
* PostgreSQL 18
* `pg_trgm`
* Blade
* Sneat
* PhpSpreadsheet
* PHPUnit
* Laravel Pint

RAKIT currently follows a modular-monolith architecture, with separate domains for Catalog, Pricing, RAP, Import, Export, Audit, and System operations.

---

## Data Privacy

This public repository does not contain private organizational RAP files, approved production Catalog data, confidential prices, or generated private import workbooks.

Tests and examples use synthetic data only.

Each organization should use an isolated deployment and database unless a future multi-tenant architecture is introduced intentionally.

---

## Project Direction

RAKIT is intended to grow into a complete RAP workflow:

```text
Import historical RAP
→ review Catalog matches
→ approve new knowledge
→ build a new RAP
→ consult trusted price history
→ finalize a revision
→ generate the required Excel output
```

The long-term goal is not simply to store spreadsheets.

It is to turn accumulated RAP experience into a structured, auditable, and reusable organizational knowledge base.

---

## Author

Developed by **Muhammad Raihan Nabawi**.

---

## License

This project is currently provided for development, learning, and portfolio purposes. Licensing terms may be updated as the project matures.
