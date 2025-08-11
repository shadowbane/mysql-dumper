# Architectural Plan: Database Backup Tool

This document outlines the architecture and implementation strategy for a web-based database backup utility using PHP/Laravel for the backend and React/Inertia for the frontend.

---
**IMPORTANT NOTE:** The following directories are for reference ONLY. You MUST NOT edit any files within these paths:
- `/Users/shadowbane/projects/leapcount/accounting.leapcount`
- `/Users/shadowbane/projects/leapcount/auth.leapcount`

**Development Rule:** All new frontend type definitions (`.ts`, `.d.ts`) MUST be placed inside the `/resources/js/types/` directory.
---

## 1. Core Concept & Architecture

The system will be a web application where users can register external database connections. The Laravel backend will handle the core logic of connecting to these databases, running the backup process, and storing the resulting files in an S3-compatible storage service.

**High-Level Flow:**

1.  **User Interface (React/Inertia):** The user interacts with a dashboard to manage database connections, view backup history, and trigger manual backups.
2.  **Backend API (Laravel):** The frontend communicates with the Laravel backend. Laravel handles all business logic.
3.  **Backup Execution (Laravel Job):** A dedicated, queueable Laravel job will contain the logic to perform the backup using the `mysqldump` command-line utility. This ensures the UI remains responsive and long-running backups don't time out.
4.  **Scheduling (Laravel Scheduler):** Laravel's built-in scheduler will be used to dispatch the backup jobs automatically based on user-defined schedules.
5.  **Storage (S3-Compatible):** The generated backup files will be streamed directly to a configured S3 bucket, organized by database name and timestamp.

## 2. Backend (Laravel)

The Laravel backend is the heart of the application.

#### a. Database Models & Migrations

We'll need a few key database tables for our application:

1.  **`data_sources`**: Stores the connection details for the databases you want to back up.
    *   `id`: Primary Key
    *   `name`: A friendly name for the connection (e.g., "Production Web Server").
    *   `host`: IP address or hostname.
    *   `port`: e.g., 3306.
    *   `database`: The specific database name to back up.
    *   `username`: The database user.
    *   `password`: The user's password ( **must be encrypted** ).
    *   `is_active`: Boolean to easily enable/disable backups for this source.
    *   `timestamps`.

2.  **`backup_schedules`**: Defines when backups should run.
    *   `id`: Primary Key
    *   `data_source_id`: Foreign key to `data_sources`.
    *   `cron_expression`: The schedule frequency in cron format (e.g., `0 2 * * *` for 2 AM daily).
    *   `is_active`: Boolean.
    *   `timestamps`.

3.  **`backup_logs`**: A record of every backup attempt.
    *   `id`: Primary Key
    *   `data_source_id`: Foreign key to `data_sources`.
    *   `status`: `success`, `failed`, `in_progress`.
    *   `file_path`: The full path to the backup file in S3.
    *   `file_size`: Size of the backup in bytes.
    *   `duration`: How long the backup took in seconds.
    *   `error_message`: Stores any failure reason.
    *   `timestamps`.

#### b. The Backup Logic: `CreateBackupJob`

This will be a queueable job (`php artisan make:job CreateBackupJob`). Using a job is critical for performance and reliability.

**Key Logic inside the `handle()` method:**

1.  **Accept `DataSource`:** The job will accept a `DataSource` model instance in its constructor.
2.  **Set Dynamic DB Connection:** Configure a temporary database connection in `config/database.php` on the fly using the details from the `DataSource` model.
3.  **Execute `mysqldump`:** Use Laravel's `Process` facade (`Illuminate\Support\Facades\Process`) to safely build and execute the `mysqldump` command.
    *   **Command Structure:** `mysqldump --host={host} --port={port} --user={user} --password={password} {database_name}`
    *   **Security:** Pass the password via an environment variable (`DB_PASSWORD`) to the process to avoid it appearing in the process list.
4.  **Compression:** Pipe the output of `mysqldump` directly to `gzip` for compression. This saves space and transfer time. Example: `mysqldump ... | gzip`.
5.  **Stream to S3:** Use Laravel's Filesystem (`Storage::disk('s3')->put()`) to upload the compressed stream to your S3-like storage.
    *   **File Naming Convention:** `/{database_name}/{Y-m-d_H-i-s}.sql.gz`
6.  **Logging:** On success or failure, create an entry in the `backup_logs` table.

#### c. Scheduling Backups

In `app/Console/Kernel.php`, we'll define a custom command (`php artisan make:command DispatchScheduledBackups`).

*   This command will run every minute.
*   It will query the `backup_schedules` table for active schedules that are due to run.
*   For each due schedule, it will dispatch the `CreateBackupJob` to the queue.

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->command('backups:dispatch')->everyMinute();
}
```

#### d. API for External Triggers

To allow an external app to trigger a backup, we'll create a secure API endpoint.

**Route:** `POST /api/v1/backups/trigger/{dataSource}`

**Authentication:** Use Laravel Sanctum to issue API tokens for your external apps. The endpoint should be protected by the `auth:sanctum` middleware.

**Controller:** The controller method will simply find the `DataSource` and dispatch the `CreateBackupJob`.

## 3. Frontend (React, Inertia, ShadCN, Zod)

The frontend will be a clean, component-based interface.

#### a. Pages & Components

*   **`Dashboard.jsx`**: Main landing page showing stats: recent backups, failures, total storage used.
*   **`DataSources/Index.jsx`**: A table (`<Table>` from ShadCN) listing all configured data sources. Will include buttons to Edit, Delete, and "Backup Now".
*   **`DataSources/Create.jsx` / `DataSources/Edit.jsx`**: A form inside a ShadCN `<Dialog>` or on a separate page to add/edit a data source.
*   **`Schedules/Index.jsx`**: Table listing all backup schedules, linked to their data sources.
*   **`BackupLogs/Index.jsx`**: A detailed table of all backup history. It should be filterable by data source and status. Each row will have a download link.

#### b. Forms & Validation with Zod

This is where `zod` shines. We'll use it with a library like `react-hook-form` for a great developer experience.

**Define Schema:** For your `DataSource` form, you'll define a Zod schema.

```javascript
// Example Zod schema for the DataSource form
import { z } from 'zod';

export const dataSourceSchema = z.object({
  name: z.string().min(3, "Name must be at least 3 characters."),
  host: z.string().min(1, "Host is required."),
  port: z.coerce.number().min(1).max(65535),
  database: z.string().min(1, "Database name is required."),
  username: z.string().min(1, "Username is required."),
  password: z.string().optional(), // Optional on edit, required on create
});
```

**Inertia Forms:** Use Inertia's form helper (`useForm`) and integrate it with `react-hook-form` and the `zodResolver` to get seamless validation from your React components to your Laravel backend.

## 4. S3-like Storage Integration

This is straightforward with Laravel.

**Install Driver:** `composer require --with-all-dependencies league/flysystem-aws-s3-v3`

**Configure `config/filesystems.php`:** Add a new disk configuration.

```php
's3' => [
    'driver' => 's3',
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION'),
    'bucket' => env('AWS_BUCKET'),
    'url' => env('AWS_URL'),
    'endpoint' => env('AWS_ENDPOINT'), // For MinIO, DigitalOcean, etc.
    'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
],
```

**Set `.env` Variables:** Populate your `.env` file with the credentials for your S3-compatible service.

## 5. Step-by-Step Implementation Roadmap

1.  **Setup:** New Laravel project, install Inertia (React preset), install Tailwind CSS, and set up ShadCN.
2.  **Backend Foundation:**
    *   Create the `DataSource`, `BackupSchedule`, and `BackupLog` models and migrations. Run migrations.
    *   Add encryption mutators to the `DataSource` model for the `password` field.
3.  **Core Backup Logic:**
    *   Create the `CreateBackupJob`.
    *   Implement the `mysqldump` logic using the `Process` facade.
    *   Configure the S3 filesystem.
4.  **Frontend - Data Sources:**
    *   Create the Inertia pages for listing, creating, and editing `DataSources`.
    *   Build the form with ShadCN components and validate it using Zod.
5.  **Connect Frontend to Backend:**
    *   Create the Laravel controllers and routes to handle CRUD for `DataSources`.
    *   Add the "Backup Now" button and its corresponding controller method to trigger the job manually.
6.  **Implement Scheduling:**
    *   Build the UI for managing schedules.
    *   Create the `backups:dispatch` console command.
    *   Register the command in the Kernel.
7.  **Finalize:**
    *   Build the `BackupLogs` page.
    *   Create the secure API endpoint for external triggers.
    *   Refine the UI/UX and add dashboard widgets.