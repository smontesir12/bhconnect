# BHConnect

*A Boarding House Management System with Proportional Utility Allocation Algorithm for Gramo's Boarding House*

## 📌 Overview

BHConnect is a web-based management system for Gramo's Boarding House in Dapawan, Odiongan, Romblon. It centralizes tenant records, room and vacancy monitoring, contract management, payment tracking, maintenance requests, QR-based occupancy logging, and shared utility allocation.

## 🎯 Core Features

### Landlord Module
- Dashboard with occupancy, payment, maintenance, and utility summaries
- Rooms, tenants, contracts, and payments management
- Proportional utility allocation with transparent formula
- Dynamic QR code generation for tenant occupancy logging
- Correction request review and approval
- Maintenance request tracking
- Digital payment receipts and due-date reminders

### Tenant Module
- Personal dashboard with room, contract, and billing info
- QR-based check-in / check-out logging
- Pending payment alerts with due dates
- Utility share breakdown per billing period
- Maintenance request submission
- Correction requests for missed scans
- In-app notifications

## 🧮 Utility Allocation Formula
Utility Share_i = (Approved Occupied Days_i / Total Approved Days) × Total Utility Bill

## 🛠 Technology Stack

- *Frontend:* HTML, CSS, JavaScript
- *Backend:* PHP 8.2
- *Database:* MySQL (MariaDB via XAMPP)
- *QR:* Dynamic tokens + external QR renderer
- *Quality Model:* ISO/IEC 25010:2023

## ⚙️ Local Setup

1. Install *XAMPP* and start *Apache* + *MySQL*.
2. Clone into C:\xampp\htdocs\bhconnect.
3. Create database bhconnect in phpMyAdmin.
4. Import sql/schema.sql.
5. Update config/db.php if needed (defaults work for XAMPP).
6. Visit http://localhost/bhconnect/index.php.

*Default landlord:* landlord / landlord123

## 👨‍💻 Author

[Sergio Jay Montesir]
BS Information Technology
[Romblon State University (main campus)] — 2026