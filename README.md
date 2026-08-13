# StoQ - Backend (API & Business Logic)

**StoQ** adalah Sistem Manajemen Inventaris dan Stok Gudang (*Inventory & Warehouse Management System*) berbasis Web. Repositori `StoQ_BE` bertindak sebagai backend RESTful API yang menangani manajemen gudang, produk, batch, stok, mutasi, transfer, restock, pengembalian (return), serta autentikasi & otorisasi pengguna berbasis role.

---

## 📌 Deskripsi & Fitur Utama

- **Authentication & Authorization**: REST API Auth menggunakan Laravel Sanctum & Role-Based Access Control (RBAC) via Spatie Permission.
- **Multi-Warehouse Support**: Pengelolaan data persediaan produk yang terpisah/terhubung antar gudang.
- **Stock & Inventory Operations**:
  - Manajemen Produk, Kategori, Satuan (Unit), dan Supplier.
  - Tracking Batch Persediaan & Masa Kadaluarsa.
  - Pembelian & Penerimaan Barang (*Purchase Order & Product Receiving*).
  - Mutasi, Transfer Stok antar Gudang, Restock, dan Distribusi Stok.
  - Retur Stok (*Stock Returns*).
- **API Documentation**: Terintegrasi dengan Scramble (`/docs/api`) untuk dokumentasi API otomatis.

---

## 👥 Informasi User & Akses (Default Seeders)

Aplikasi memiliki 3 tingkatan peran (*Role*):

| Role | Username / Email Contoh | Password | Deskripsi Akses |
| :--- | :--- | :--- | :--- |
| **Super Admin** | `superadmin` / `superadmin@example.com` | `password` | Akses penuh ke seluruh gudang, manajemen master data (Kategori, Unit, User, Gudang). |
| **Admin Gudang** | `admin.gudang1` / `admin1@example.com` | `password` | Pengelolaan penuh persediaan, restock, transfer, dan operasi gudang spesifik (misal: Gudang 1). |
| **Staff Gudang** | `staff.gudang1` / `staff1@example.com` | `password` | Penginputan operasional harian gudang, pencatatan mutasi/penerimaan barang. |

---

## 🛠️ Spesifikasi Teknologi

- **Framework**: PHP 8.2+ / Laravel 12.x
- **Authentication**: Laravel Sanctum
- **Permissions**: Spatie Laravel Permission
- **Database**: MySQL / PostgreSQL / SQLite
- **Cache & Queue**: Redis / Predis
- **API Docs**: Dedoc Scramble

---

## 🚀 Panduan Instalasi & Jalankan

### Prasyarat
- PHP >= 8.2 (dengan ekstensi `pdo`, `mbstring`, `openssl`, dll.)
- Composer >= 2.x
- Node.js >= 18.x & NPM
- Database MySQL / MariaDB (misal via Laragon/XAMPP)

### Langkah Instalasi

1. **Clone repository & masuk ke direktori**:
   ```bash
   git clone <repository-url> StoQ_BE
   cd StoQ_BE
   ```

2. **Install dependensi PHP & Node.js**:
   ```bash
   composer install
   npm install
   ```

3. **Konfigurasi Environment**:
   Salin `.env.example` ke `.env` dan atur koneksi database:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   Pastikan pengaturan `DB_DATABASE`, `DB_USERNAME`, dan `DB_PASSWORD` sesuai dengan database lokal Anda.

4. **Migrasi Database & Seeding Data Awal**:
   ```bash
   php artisan migrate:fresh --seed
   ```

5. **Jalankan Server**:
   Anda dapat menjalankan server development menggunakan command bawaan `composer dev` (menjalankan server, queue, logs, dan vite secara paralel):
   ```bash
   composer dev
   ```
   Atau jalankan server Laravel biasa:
   ```bash
   php artisan serve
   ```

---

## 📄 Dokumentasi API

Setelah aplikasi berjalan (`php artisan serve`), dokumentasi API interaktif dapat diakses melalui browser pada link:
`http://localhost:8000/docs/api` (atau URL server lokal Anda).
