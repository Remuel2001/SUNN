# SUNN Faculty Monitoring System - Project Guide

## Overview
AI-powered face recognition system for automated instructor attendance tracking and classroom monitoring.

## Architecture
- **Frontend**: HTML5, CSS3, Bootstrap 5, JavaScript, jQuery, AJAX, Chart.js
- **Backend**: PHP 8+, MySQL, PDO
- **AI**: Browser-based FaceDetector API + LBP histogram (no server-side ML)

## Setup Instructions

### 1. Database
- Run `database/init.php` in browser to create tables
- Or import `database/schema.sql` into MySQL manually
- Default login: `admin` / `admin123`

### 2. PHP (XAMPP)
- Place project in `htdocs/SUNN-faculty monitoring` (or clone into `htdocs/` with this name)
- Visit `http://localhost/SUNN-faculty monitoring/`

### 3. Face Recognition
- Uses **browser-based FaceDetector API** (Chrome/Edge) — no Python/OpenCV needed
- LBP histogram extraction (32-bin, L1-normalized, ~0.35 threshold) — stored as JSON in MySQL TEXT
- Register instructor faces via admin panel → live video capture + file upload fallback
- Attendance clock-in/out uses face verification against registered histograms
- Legacy `ai/` Python scripts (`face_recognition.py`, `train_model.py`) are unused/deprecated

## Directory Structure
| Directory | Purpose |
|-----------|---------|
| `admin/` | Admin dashboard and management pages |
| `instructor/` | Instructor attendance and schedule |
| `student/` | Student dashboard |
| `department_head/` | Department head dashboard |
| `api/` | AJAX/JSON API endpoints |
| `ai/` | Legacy Python face recognition (unused) |
| `config/` | Database and system configuration |
| `includes/` | Shared header, footer, navbar |
| `database/` | SQL schema and setup scripts |
| `uploads/` | Face images and temporary files |
| `SUNN-Android-App/` | Android WebView APK project |

## User Roles
- **admin**: Full system access (manage instructors, classrooms, schedules, reports, settings, users, special days)
- **instructor**: View schedule, clock in/out via face recognition, attendance history
- **student**: View dashboard with today's classes and notifications
- **department_head**: View department instructors and attendance overview

## Key Features
1. AI Face Recognition Attendance (browser-based)
2. Schedule-driven auto attendance with evidence upload
3. Dashboard & Analytics with Chart.js
4. Real-time Chat (Messenger-like) with typing indicators, read receipts, online status
5. Audio & Video Calls via WebRTC with MySQL polling signaling
6. Notifications & Alerts
7. Reports Generation (PDF/Excel)
8. User Management & RBAC (26 permissions)
9. Activity Logging
10. Special Days (holiday/suspension/no-class) with API enforcement
11. PWA Support (manifest.json + sw.js)

## API Endpoints
- `/api/chat.php` — Chat + Call API (contacts, messages, send, typing, online, start_call, check_call, update_call, send_signal, get_signals)
- `/api/attendance.php` — Clock in/out, stats
- `/api/face_recognition.php` — Register/verify face
- `/api/classroom.php` — Classroom presence
- `/api/notifications.php` — Mark read, count
- `/api/get_department_instructors.php` — List instructors by department

## Database Tables
users, instructors, departments, subjects, classrooms, schedules, facial_data, attendance_logs, classroom_presence, notifications, activity_logs, leave_requests, system_settings, calls, call_signals, permissions, role_permissions

## Key Implementation Notes
- **Chat**: `chat.php` unified UI, jQuery in `<head>` (not footer), `var lastMsgState` (not `let`) to avoid TDZ
- **WebRTC Calls**: MySQL polling signaling (800ms), 3 Google STUN servers, `getUserMedia` on both sides, offer→answer→ICE exchange via `call_signals` table, ringtone via Web Audio API (440Hz), auto-miss after 25s
- **Signal processing**: SDP (offer/answer) processed before ICE candidates in `startSignalPoll()` — was `forEach` with async race
- **Video play fix**: `ontrack` only sets `remoteVideo.srcObject` for video calls (not audio); explicit `loadedmetadata` → `.play()` for both local/remote video (Chrome autoplay policy)
- **Brand images**: `uploads/brand/logo.png` (200×60) and `header.png` (1200×200) — generate via PHP GD
- **Database migration**: `database/migrate.php` adds missing columns/tables for existing DBs (e.g., `receiver_id` in `call_signals`)
- **InfinityFree**: files in `htdocs/` root, `BASE_URL=''`, hardcoded `define()` constants (no env vars), MySQL via `sql312.infinityfree.com`
- **Android APK**: `SUNN-Android-App/` — WebView loads `sunntracking.infinityfreeapp.com`
- **Admin password**: Changed from `admin123` to `admin_4b8a3c2f`

## Critical Context
- Default login: **admin / admin123**
- Chrome blocks `getUserMedia` on HTTP unless localhost; InfinityFree uses HTTPS
- Upload dirs: `uploads/faces/`, `uploads/faces/evidence/{instructor_id}/`, `uploads/profiles/`, `uploads/brand/`, `uploads/temp/`
