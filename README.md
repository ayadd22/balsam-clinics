# Balsam Clinics API

A Laravel-based RESTful API for managing multi-branch medical clinics, doctors, patients, appointments, medical records, reminders, and administrative operations.

## Table of Contents

- [Project Overview](#project-overview)
- [Tech Stack](#tech-stack)
- [User Roles](#user-roles)
- [Core Features](#core-features)
- [Database Entities](#database-entities)
- [Entity Relationships](#entity-relationships)
- [Architecture and Design](#architecture-and-design)
- [Caching and Performance](#caching-and-performance)
- [Queues and Background Jobs](#queues-and-background-jobs)
- [Scheduled Tasks](#scheduled-tasks)
- [API Endpoints](#api-endpoints)
- [Postman Collection](#postman-collection)
- [Installation and Setup](#installation-and-setup)
- [Demo Data](#demo-data)
- [Testing](#testing)

---

## Project Overview

Balsam Clinics API is designed to manage the daily operations of a medical clinic system with multiple branches.

The system supports appointment booking, doctor schedules, patient management, medical records, appointment reminders, audit logging, and automated administrative tasks.

The API uses role-based access control to ensure that each user can only perform actions permitted by their role and assigned branch.

The project focuses on maintainability, performance, data consistency, and reliable background processing.

## Tech Stack

| Technology | Purpose |
|---|---|
| PHP 8.2+ | Backend programming language |
| Laravel 12 | Application framework |
| MySQL | Main database |
| Laravel Sanctum | API authentication |
| Eloquent ORM | Database relationships and queries |
| Laravel Queues | Background job processing |
| Redis | Queue and cache backend |
| Laravel Horizon | Queue monitoring and management |
| Laravel Cache | Query and availability caching |
| Laravel Scheduler | Automated scheduled tasks |
| PHPUnit | Automated testing |
| Postman | API testing and documentation |

## User Roles

The system supports four main roles:

| Role | Responsibilities |
|---|---|
| Admin | Manage the system, access audit logs, and manage medical records across doctors |
| Receptionist | Manage appointments for assigned branches |
| Doctor | View assigned appointments and manage their own medical records |
| Patient | View available doctors and book or cancel their own appointments |

Authorization is implemented through Laravel Policies and middleware, with additional branch-level access validation.

## Core Features

### Authentication and Authorization

- User registration and login.
- Token-based authentication using Laravel Sanctum.
- Role-based access control.
- Branch-level authorization for receptionists.
- Ownership checks for patient appointments and doctor medical records.

### Doctor Management

- List doctors by branch.
- Filter doctors by specialty.
- Paginated doctor results.
- Retrieve doctor schedules and availability.

### Appointment Management

- Check appointment availability before booking.
- Book appointments as a patient, receptionist, or admin.
- Validate doctor schedules and prevent overlapping appointments.
- Generate unique appointment reference numbers.
- Cancel appointments with authorization checks.
- Track appointment statuses:
  - Booked
  - Arrived
  - Completed
  - Cancelled
  - No-show

### Medical Records

- Create medical records for completed appointments.
- Allow doctors to manage their own records.
- Allow administrators to manage medical records.
- Maintain an audit history of medical record changes.

### Audit Logging

- Track model creation, updates, and deletions.
- Store the user responsible for each operation when available.
- Record old and new values for changed attributes.
- Provide administrator-only audit log endpoints.

### Automated Operations

- Send appointment reminders.
- Automatically mark missed appointments as no-show.
- Unblock patients after their blocking period expires.
- Generate monthly branch reports in JSON or CSV format.

## Database Entities

The main entities used in the system are:

| Entity | Responsibility |
|---|---|
| User | Authentication and user account information |
| Branch | Represents a clinic branch |
| Doctor | Stores doctor-specific information and professional details |
| DoctorSchedule | Defines the working schedule of a doctor at a branch |
| Patient | Stores patient-specific information |
| Appointment | Represents a booking between a patient, doctor, and branch |
| MedicalRecord | Stores diagnosis, prescription, and medical notes |
| AppointmentReminder | Tracks scheduled appointment reminders |
| AppointmentSequence | Maintains appointment reference number sequences per branch |
| AuditLog | Stores the history of tracked model operations |

## Entity Relationships

The database is designed around the relationships between users, branches, doctors, patients, and appointments.

### 1. User

The `User` entity stores authentication information and the user's role.

Relationships:

- A user can have one doctor profile.
- A user can have one patient profile.
- A user can belong to multiple branches through a many-to-many relationship, mainly for administrators and receptionists.
- A user can be associated with multiple audit log entries.

**Why?**

Separating authentication data from doctor and patient profiles avoids placing role-specific fields in the main users table and keeps the account structure flexible.

### 2. Branch

The `Branch` entity represents a physical clinic location.

Relationships:

- A branch has many doctor schedules.
- A branch has many appointments.
- A branch belongs to many users.
- A branch has many appointment sequences.

**Why?**

A many-to-many relationship with users allows administrators to access multiple branches and receptionists to be assigned to specific branches without duplicating user accounts.

### 3. Doctor

The `Doctor` entity stores professional information such as specialty, license number, and active status.

Relationships:

- A doctor belongs to one user.
- A doctor has many schedules.
- A doctor has many appointments.
- A doctor has many medical records.

**Why?**

The doctor profile is separated from the user account because professional information is specific to doctors, while authentication information is shared across all roles.

### 4. DoctorSchedule

The `DoctorSchedule` entity defines when a doctor is available at a particular branch.

Relationships:

- A schedule belongs to one doctor.
- A schedule belongs to one branch.

**Why?**

Keeping schedules in a separate entity supports multiple working days, different working hours, and doctor availability across clinic branches.

### 5. Patient

The `Patient` entity stores patient-specific information.

Relationships:

- A patient belongs to one user.
- A patient has many appointments.

**Why?**

The patient profile separates patient-specific information from authentication details and allows the same user-account structure to support different roles.

### 6. Appointment

The `Appointment` entity is the central entity of the booking process.

It stores:

- Patient ID
- Doctor ID
- Branch ID
- Appointment date
- Start and end times
- Appointment status
- Unique reference number
- Check-in timestamp

Relationships:

- An appointment belongs to one patient.
- An appointment belongs to one doctor.
- An appointment belongs to one branch.
- An appointment can have a medical record.
- An appointment can have reminder records.
- An appointment can be tracked through audit logs.

**Why?**

The appointment connects the patient, doctor, and branch. Keeping these relationships explicit allows the system to validate doctor availability, branch assignments, and appointment ownership.

The appointment reference number provides a human-readable identifier independent of the database ID.

### 7. MedicalRecord

The `MedicalRecord` entity stores the medical information associated with an appointment.

It includes:

- Diagnosis
- Prescription
- Notes

Relationships:

- A medical record belongs to one appointment.
- A medical record belongs to one doctor.
- Medical record operations can be tracked through audit logs.

**Why?**

Medical records are kept separate from appointments to distinguish booking information from clinical information. This also allows medical records to have their own authorization and audit history.

### 8. AppointmentReminder

The `AppointmentReminder` entity tracks reminders associated with appointments.

It stores reminder information such as its type and delivery status.

**Why?**

Keeping reminders separate from appointments allows reminder scheduling and delivery attempts to be tracked without adding reminder-specific fields to the appointment entity.

### 9. AppointmentSequence

The `AppointmentSequence` entity maintains appointment numbering sequences for branches.

**Why?**

Branch-specific sequences help generate organized appointment reference numbers without relying on a single global sequence.

### 10. AuditLog

The `AuditLog` entity records changes made to tracked models.

It uses Laravel polymorphic relationships:

- `auditable` can refer to different tracked model types.
- `user` identifies the user responsible for the operation when available.

It stores:

- Event type (`created`, `updated`, or `deleted`)
- Auditable model type and ID
- Old values
- New values
- Associated user
- Creation timestamp

**Why?**

A polymorphic relationship allows one audit log table to track multiple entity types without creating a separate audit table for every entity.

---

## Architecture and Design

The project follows a service-oriented structure to keep controllers focused on HTTP requests and responses.

### Services

Business logic is separated into dedicated services, including:

- AppointmentService
- AppointmentAvailabilityService
- DoctorCacheService
- AuditLogService
- MonthlyBranchReportService
- PatientUnblockService
- AppointmentReminderService

This separation improves code organization, reusability, and testability.

### Form Requests

Laravel Form Requests handle request validation and authorization before controller actions are executed.

### Policies

Policies enforce resource-level permissions, including:

- Appointment creation and cancellation.
- Medical record creation and updates.
- Audit log access.

### Observers

Observers handle model lifecycle events.

Implemented observers include:

- AppointmentObserver
- DoctorObserver
- DoctorScheduleObserver
- MedicalRecordObserver

They handle audit logging and cache invalidation where applicable.

Database-dependent cache invalidation is deferred until the transaction commits, preventing cache changes when a transaction rolls back.

### API Response Formatting

The `ApiResponse` helper provides a consistent JSON response structure for successful and failed API requests.

API exceptions are also rendered using a consistent JSON format.

---

## Caching and Performance

Laravel Cache is used to reduce repeated database queries and improve API performance.

The caching implementation includes:

- Doctor listing cache.
- Appointment availability cache.
- Cache invalidation when relevant doctors, schedules, or appointments change.
- Version-based cache invalidation.
- Database transaction-aware invalidation using `afterCommit`.

Cache expiration:

| Cache | TTL |
|---|---|
| Doctor listing | 3600 seconds |
| Appointment availability | 300 seconds |

Redis is configured as the cache backend through Laravel's cache configuration.

Eloquent eager loading is also used to reduce unnecessary database queries.

## Queues and Background Jobs

The application uses Laravel Queues with Redis to process time-consuming operations in the background.

Examples include:

- Sending appointment booking SMS notifications.
- Sending appointment cancellation SMS notifications.
- Sending appointment reminders.

SMS integration is abstracted through `SmsServiceInterface`, allowing the SMS provider implementation to be replaced without changing the business logic.

The current development binding uses `LogSmsService`.

Queued jobs and listeners support retry and backoff configuration to improve reliability when temporary failures occur.

### Queue Priorities

The application uses separate queues to prioritize background tasks:

| Queue | Purpose |
|---|---|
| high | Important appointment notifications |
| default | General background tasks |
| low | Lower-priority reminder operations |

### Laravel Horizon

Laravel Horizon is used to monitor and manage Redis queues.

The Horizon configuration defines separate supervisors for the `high`, `default`, and `low` queues, with configurable process limits, retry attempts, timeouts, and memory limits.

To start Horizon in a compatible production environment:

```bash
php artisan horizon
```

Horizon requires a compatible Redis client and a supported process-control environment. Production deployment should use a Linux environment with the required PHP extensions.

## Scheduled Tasks

The Laravel Scheduler automates recurring business operations.

| Task | Schedule | Timezone |
|---|---|---|
| Send appointment reminders | Daily at 18:00 | Asia/Damascus |
| Mark missed appointments as no-show | Every 15 minutes | Asia/Damascus |
| Unblock expired patients | Every hour | Asia/Damascus |
| Generate monthly branch reports | First day of each month at 00:10 | Asia/Damascus |

Scheduled tasks use overlap prevention and single-server execution protection where configured.

The monthly report covers the previous month and supports JSON and CSV output.

---

## API Endpoints

All API routes use the `/api` prefix.

| Method | Endpoint | Description |
|---|---|---|
| POST | `/api/auth/register` | Register a patient |
| POST | `/api/auth/login` | Authenticate a user |
| POST | `/api/auth/logout` | Logout the current user |
| GET | `/api/user` | Get the authenticated user |
| GET | `/api/doctors` | List and filter doctors |
| GET | `/api/appointments/availability` | Check appointment availability |
| POST | `/api/appointments` | Create an appointment |
| PATCH | `/api/appointments/{appointment}/cancel` | Cancel an appointment |
| GET | `/api/medical-records` | List medical records |
| POST | `/api/medical-records` | Create a medical record |
| GET | `/api/medical-records/{medicalRecord}` | Show a medical record |
| PUT | `/api/medical-records/{medicalRecord}` | Update a medical record |
| GET | `/api/audit-logs` | List audit logs |
| GET | `/api/audit-logs/{auditLog}` | Show an audit log |

Protected endpoints require a valid Laravel Sanctum Bearer Token.

## Postman Collection

A Postman collection is included in the repository:

`docs/postman/Balsam_Clinics_API.postman_collection.json`

### How to Import

1. Open Postman.
2. Click **Import**.
3. Select the collection JSON file from the project.
4. Configure the collection variables.
5. Log in using the appropriate account to obtain an authentication token.
6. Use the collection requests to test the API.

The collection includes requests for authentication, doctors, appointments, medical records, and audit logs.

---

## Installation and Setup

### Requirements

- PHP 8.2 or later
- Composer
- MySQL
- Redis server
- A compatible Redis PHP client, such as PhpRedis
- Laravel-compatible PHP extensions

### 1. Clone the Repository

```bash
git clone <repository-url>
cd balsam-clinics-api
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Configure Environment

Create your environment file:

```bash
cp .env.example .env
```

Configure your database connection and other environment variables in `.env`.

For Redis-backed queues and caching, configure the Redis connection settings and ensure the Redis service is available.

### 4. Generate Application Key

```bash
php artisan key:generate
```

### 5. Run Migrations and Seeders

```bash
php artisan migrate --seed
```

### 6. Start the Development Server

```bash
php artisan serve
```

The API will be available at:

`http://127.0.0.1:8000`

### 7. Start the Queue Worker

For a standard Redis queue worker:

```bash
php artisan queue:work redis
```

For environments using Horizon, start Horizon instead:

```bash
php artisan horizon
```

### 8. Run the Scheduler

For local development:

```bash
php artisan schedule:work
```

In production, configure the Laravel Scheduler to execute every minute.

---

## Demo Data

The project includes `ClinicDemoSeeder`, which creates sample data for development and API testing.

It includes:

- Three clinic branches.
- One administrator.
- Three receptionists.
- Five doctors.
- Fifteen demo patients.
- Doctor schedules.
- Appointments with different statuses.
- Medical records.
- Pending appointment reminders.
- Patient blocking scenarios.

The seeder prints Sanctum access tokens for the generated users.

**Demo administrator account:**

| Field | Value |
|---|---|
| Email | `admin@balsam.test` |
| Password | `password` |

These credentials are for local development and testing only. Do not use demo credentials or generated access tokens in production.

To run the demo seeder separately:

```bash
php artisan db:seed --class=ClinicDemoSeeder
```

---

## Testing

The project includes automated tests covering:

- Authentication and authorization.
- Appointment booking and cancellation.
- Appointment availability.
- Doctor filtering and schedules.
- Medical record operations.
- Audit logging.
- Observer behavior.
- Cache invalidation.
- Queue integration.
- Scheduled commands.
- Failure handling and dry-run scenarios.

Tests use an isolated SQLite in-memory database where configured.

Run the test suite:

```bash
php artisan test
```

**Latest verified test result:**

- 169 tests passed.
- 402 assertions passed.

---

## License

This project is developed as a clinic management API project using the Laravel framework.