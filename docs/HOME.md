# Casa Paraiso Docs Home

Index of the project documentation. Links are relative Markdown links so they work in Obsidian and on GitHub.

## Core

- [MVP Scope](MVP_SCOPE.md)
- [Tech Stack](TECH_STACK.md)
- [Implementation Roadmap](IMPLEMENTATION_ROADMAP.md)
- [Project Memory](PROJECT_MEMORY.md)
- [Tasks To Do](TASKS_TO_DO.md)

## Data

- [Database Design](DATABASE_DESIGN.md)
- [MariaDB Migration](MARIADB_MIGRATION.md)
- [CRUD Data Repair Plan](CRUD_DATA_REPAIR_PLAN.md)
- [CRUD Remediation Checklist](CRUD_REMEDIATION_CHECKLIST.md)

## UI

- [Screen Flow](SCREEN_FLOW.md)
- [Brand UI Guide](BRAND_UI_GUIDE.md)
- [Frontend Consistency Audit](FRONTEND_CONSISTENCY_AUDIT.md)

## Operations and Deployment

- [Docker Workflow](DOCKER_WORKFLOW.md)
- [Hostinger Deployment](HOSTINGER_DEPLOYMENT.md)
- [Render and Oracle Deployment](RENDER_ORACLE_DEPLOYMENT.md)
- [Security Hardening](SECURITY_HARDENING.md)
- [Google Auth Setup](GOOGLE_AUTH_SETUP.md)
- [Local Demo Credentials](LOCAL_DEMO_CREDENTIALS.md) (local development only)

## Sentiment Analysis

- [Sentiment Analysis Implementation Plan](SENTIMENT_ANALYSIS_IMPLEMENTATION_PLAN.md)
- [Sentiment Data Governance](SENTIMENT_DATA_GOVERNANCE.md)
- [Sentiment Dataset Manifest](sentiment-dataset-manifest.json)

## Session Notes

- [Session Index](notes/SESSIONS.md): per-task logs, newest first. Read the latest entries before starting a task.

## Architecture

Source: [system-architecture.mmd](system-architecture.mmd)

```mermaid
flowchart LR
    Browser["Guests and staff browsers"] --> Web["Laravel 12 Blade web application"]
    Web --> Auth["Breeze and Socialite authentication"]
    Web --> Domain["Scheduling, payments, attendance, rewards, reports"]
    Domain --> MariaDB[("MariaDB 10.11 LTS")]
    Web --> Mailpit["Mailpit in local development"]
```
