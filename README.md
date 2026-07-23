# NEXA

> **NEXA** adalah aplikasi internal milik **XNet (PT. XPlus Network
> Indonesia)** yang dikembangkan sebagai pusat administrasi dan
> operasional perusahaan ISP.

> **Status:** Draft Perencanaan Arsitektur (Living Document)

---

# Tujuan Project

NEXA bukan sekadar aplikasi billing, tetapi dirancang sebagai
**Operating System** untuk seluruh proses bisnis XNet.

Tahap awal akan mencakup:

- Manajemen pelanggan
- Manajemen produk & layanan
- Billing
- Pembayaran melalui Xendit
- Dashboard administrasi

Selanjutnya akan dikembangkan menjadi platform terpadu yang mencakup
operasional ISP secara menyeluruh.

---

# Teknologi

Komponen Teknologi

---

Backend Laravel 13
Frontend Blade
Styling Tailwind CSS
UI Interaction Alpine.js
Component Kompleks Vue
daisyUI
Database MySQL
Payment Gateway Xendit
Development OS Ubuntu 20.04
Local Server Laravel Valet 2.3.10
Production VPS (contoh: IDCloudHost)

---

# Visi Arsitektur

NEXA akan dibangun sebagai aplikasi modular yang mudah dikembangkan
dalam jangka panjang.

Prinsip utama:

- Clean Architecture
- Modular Design
- Separation of Concerns
- Domain-Based Module
- Scalability
- Security First
- Maintainability
- API First untuk aplikasi pelanggan

---

# Scope Tahap Awal

## Master Data

- Customer
- Product
- Service Package

## Billing

- Invoice
- Tagihan
- Pembayaran
- Integrasi Xendit

## Customer

- Data pelanggan
- Status layanan
- Riwayat pembayaran

---

# Roadmap Modul

- Authentication
- Authorization
- User
- Role & Permission
- Customer
- Product
- Service
- Installation
- Billing
- Invoice
- Payment
- Xendit
- Mikrotik Integration
- Monitoring
- Ticketing
- Notification
- Automation
- Reporting
- Audit Log
- System Setting

---

# Arsitektur Frontend

Gunakan teknologi sesuai tanggung jawabnya.

## Blade

- Layout
- Dashboard
- CRUD
- Form
- Table

## Alpine.js

- Modal
- Dropdown
- Accordion
- Toast
- Toggle
- Interaksi ringan

## Vue

Digunakan hanya jika diperlukan, seperti:

- Dashboard realtime
- Monitoring jaringan
- Grafik
- Wizard multi-step
- Komponen kompleks

---

# API

Aplikasi pelanggan akan mengakses sistem melalui REST API.

Contoh:

    NEXA Admin (Blade)
            │
            │ REST API
            ▼
    Customer App
    Android / iOS / Web

Gunakan versioning sejak awal.

    /api/v1

---

# Integrasi Network

Tahap awal:

- MikroTik

Target desain:

- Driver berbasis adapter agar mudah menambah vendor baru tanpa
  mengubah modul bisnis.

---

# Standar Pengembangan

Seluruh developer wajib mengikuti standar berikut, dan semua proses, penjelasan, dan dokumentasi harus selalu dalam bahasa Indonesia agar semua orang yang membacanya bisa memahami dengan jelas dan tidak salah tafsir:

## Coding

- Gunakan Form Request untuk validasi.
- Hindari business logic di Controller.
- Gunakan Service Layer untuk proses bisnis.
- Gunakan Eloquent secara konsisten.
- Dependency Injection.

## Database

- Engine InnoDB
- utf8mb4
- Foreign Key
- Index yang sesuai
- Timestamp standar Laravel
- Hard Delete hanya bila sangat diperlukan, sisanya adalah menggunakan soft delete untuk menghapus data

## Naming

Gunakan nama yang konsisten. untuk penamaan url routing menggunakan kolom code dari masing-masing table dan bukan menggunakan kolom ID.

Contoh:

    CustomerController
    CustomerService
    CustomerPolicy
    CustomerRequest

---

# Prinsip Pengembangan

Setiap fitur harus melewati tahapan berikut:

1.  Analisis kebutuhan bisnis
2.  Desain arsitektur
3.  Desain database
4.  Desain alur proses
5.  Analisis keamanan
6.  Analisis performa
7.  Implementasi Laravel

Jangan langsung menulis kode tanpa memahami proses bisnis.

---

# Dokumentasi yang Akan Disusun

- Software Architecture Document (SAD)
- Module Blueprint
- Entity Relationship Diagram (ERD)
- Laravel Development Standard
- API Standard
- Database Standard
- Security Standard
- UI Design System
- Deployment Guide
- Backup & Recovery Guide

---

# Tujuan Jangka Panjang

NEXA diharapkan menjadi platform terpadu yang mampu menangani seluruh
proses operasional XNet dalam satu aplikasi dengan arsitektur yang
konsisten, aman, mudah dipelihara, dan siap berkembang.

---

## Update Arsitektur

### Login

- Login menggunakan nomor telepon.
- OTP dikirim melalui WhatsApp.
- WhatsApp Gateway berjalan pada server terpisah menggunakan **go-whatsapp-web-multidevice**.
- Komunikasi dilakukan melalui REST API.
- NEXA bertanggung jawab atas pembuatan, penyimpanan, masa berlaku, dan validasi OTP.

### Payment Gateway

- Menggunakan **Xendit Payment Requests API v3**.
- Integrasi menggunakan **Raw HTTP API**.
- Tidak menggunakan SDK resmi Xendit.
- Seluruh request dibungkus melalui service internal agar mudah dipelihara.

## Roadmap

- Authentication
- Authorization
- Customer
- Billing
- Payment
- Xendit Integration
- WhatsApp Integration
- OTP Management
- MikroTik Integration
- Monitoring
- Ticketing
- Reporting
- Audit Log

> Dokumen ini merupakan **living document** dan akan diperbarui
> mengikuti perkembangan proyek.
