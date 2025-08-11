# MySQL Database Backup Tool - Claude Code Instructions

This document provides context and instructions for Claude Code when working on this PHP/Laravel + React/Inertia database backup utility.

## Project Overview

A web-based database backup tool that allows users to:
- Register external MySQL database connections
- Schedule automated backups using cron expressions
- Store backup files in S3-compatible storage
- View backup history and logs
- Trigger manual backups via UI or API

## Architecture Stack

- **Backend**: PHP/Laravel with queued jobs
- **Frontend**: React with Inertia.js, ShadCN components, Zod validation
- **Storage**: S3-compatible storage (AWS S3, MinIO, etc.)
- **Database**: MySQL/PostgreSQL for application data
- **Queue**: Redis/database for job processing

## Key Development Rules

**IMPORTANT RESTRICTIONS:**
- `/Users/shadowbane/projects/leapcount/accounting.leapcount` - REFERENCE ONLY, DO NOT EDIT
- `/Users/shadowbane/projects/leapcount/auth.leapcount` - REFERENCE ONLY, DO NOT EDIT
- All frontend TypeScript definitions must go in `/resources/js/types/`

## Database Schema

### Core Tables
1. **data_sources** - External database connections
2. **backup_schedules** - Cron-based backup scheduling  
3. **backup_logs** - History of all backup attempts

## Key Components

### Backend (Laravel)
- `CreateBackupJob` - Queued job for mysqldump execution
- `DispatchScheduledBackups` - Console command for cron scheduling
- API endpoints for external backup triggers
- S3 filesystem integration for file storage

### Frontend (React/Inertia)
- Dashboard with backup statistics
- Data source CRUD with form validation (Zod)
- Backup schedule management
- Backup history with download links

## Testing Commands

When making changes, run these commands to verify functionality:

```bash
# Laravel tests
php artisan test

# Frontend type checking (if available)
npm run type-check

# Code style/linting
composer run phpcs  # or similar PHP linting
npm run lint       # Frontend linting
```

## Common Development Tasks

### Adding New Features
1. Create migrations for database changes
2. Build Laravel models with proper relationships
3. Implement backend API endpoints
4. Create React components with Zod validation
5. Add Inertia page components
6. Test both manual and scheduled backup flows

### Security Considerations
- Database passwords must be encrypted in storage
- API endpoints require Sanctum authentication
- Process execution uses environment variables for sensitive data
- S3 credentials stored in .env file

### File Organization
- Models: `app/Models/`
- Jobs: `app/Jobs/`
- Controllers: `app/Http/Controllers/`
- React components: `resources/js/Components/`
- Pages: `resources/js/Pages/`
- Types: `resources/js/types/`

## Backup Process Flow

1. User configures data source connection
2. Schedule defined with cron expression
3. Laravel scheduler dispatches `CreateBackupJob`
4. Job connects to external database via mysqldump
5. Output compressed with gzip
6. File streamed directly to S3 storage
7. Results logged in `backup_logs` table

This architecture ensures scalable, reliable database backups with a modern web interface.