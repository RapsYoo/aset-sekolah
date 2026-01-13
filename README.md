# Sistem Monitoring Aset Sekolah Berbasis Web

Aplikasi web untuk monitoring aset sekolah per kategori KIB (A-F) dengan fitur input bulanan dan visualisasi data menggunakan chart.

## 📋 Fitur Utama

- ✅ **Sistem Login** - Admin & Pegawai (role-based access)
- ✅ **Input Aset Bulanan** - Per KIB (A-F) dengan validasi server-side
- ✅ **Dashboard Chart** - Visualisasi data aset per KIB menggunakan Chart.js
- ✅ **Manajemen User** - Admin dapat menambah/edit/hapus pengguna
- ✅ **Responsif** - UI Bootstrap 5 (mobile-friendly)
- ✅ **Keamanan** - Password hashing, CSRF token, prepared statements, session-based auth

## 🛠️ Tech Stack

- **Backend:** PHP Native (MySQLi)
- **Database:** MySQL/MariaDB
- **Frontend:** Bootstrap 5, Chart.js
- **Server:** Laragon (Apache + PHP)

## 📦 Instalasi & Setup

### 1. **Buka Laragon & Aktifkan MySQL**

```bash
# Buka terminal Laragon atau cmd
cd C:\laragon
laragon start
```

### 2. **Import Database**

Impor file `migrations/schema.sql` ke MySQL:

```bash
# Metode 1: PhpMyAdmin (via browser)
# Buka: http://localhost/phpmyadmin
# - Klik "Import"
# - Pilih file: migrations/schema.sql
# - Klik "Go"

# Metode 2: Command line
mysql -u root -p < C:\laragon\www\aset-sekolah\migrations\schema.sql
# (Tekan Enter jika tidak ada password)
```

### 3. **Akses Aplikasi**

Buka browser:
```
http://localhost/aset-sekolah
```

## 🔐 Akun Default (Demo)

| Email | Password | Role |
|-------|----------|------|
| admin@sekolah.com | admin123 | Admin |
| pegawai@sekolah.com | pegawai123 | Pegawai |

> ⚠️ **Ganti password** setelah login pertama kali!

## 📁 Struktur Folder

```
aset-sekolah/
├── index.php                 # Halaman login
├── dashboard.php             # Dashboard dengan chart
├── logout.php               # Logout
├── profile.php              # Edit profil user
├── inc/
│   ├── config.php          # Konfigurasi koneksi DB
│   ├── db.php              # Database helper functions
│   ├── auth.php            # Autentikasi & session functions
│   └── helpers.php         # Utility functions
├── api/
│   └── assets.php          # API endpoint data aset (JSON)
├── assets/
│   ├── index.php           # Daftar aset per KIB
│   ├── create.php          # Form input aset baru
│   ├── edit.php            # Form edit aset
│   └── delete.php          # Delete aset
├── admin/
│   ├── users.php           # Manajemen user (admin only)
│   ├── edit_user.php       # Edit user
│   └── delete_user.php     # Delete user
├── migrations/
│   └── schema.sql          # SQL untuk setup database
├── public/
│   ├── css/               # Custom CSS (opsional)
│   └── js/                # Custom JavaScript (opsional)
└── README.md              # File ini
```

## 🔑 Fitur Detail

### **Login & Autentikasi**
- Form login dengan validasi email & password
- Session-based authentication
- Role-based access control (admin vs pegawai)
- CSRF token protection

### **Dashboard**
- Menampilkan 6 chart (KIB A-F) dengan data 12 bulan
- Statistik ringkas (total aset, pengguna, role)
- Tombol "Input Aset" untuk tambah data baru
- Responsive grid layout

### **Input & Edit Aset**
- Pilih KIB (A-F), tahun, bulan, dan total aset
- Validasi client & server-side
- Edit data yang sudah ada
- Hapus data dengan konfirmasi
- Form dilindungi CSRF token

### **API Endpoint**
```
GET /api/assets.php?kib=A&year=2025
Response: [
  {month: 1, total: 150},
  {month: 2, total: 155},
  ...
]
```

### **Manajemen User (Admin Only)**
- Tambah user baru dengan role assignment
- Edit user (nama, role, status aktif/nonaktif)
- Hapus user
- Lihat daftar semua user

## 🔒 Keamanan

- ✅ Password hashing dengan `password_hash()` (bcrypt)
- ✅ Prepared statements (MySQLi) → SQL injection prevention
- ✅ CSRF tokens di semua form
- ✅ Session-based auth dengan login check
- ✅ Output escaping dengan `htmlspecialchars()`
- ✅ Input sanitization & validation

## 📝 Penggunaan Aplikasi

### **Alur Admin**
1. Login dengan akun admin
2. Masuk ke Dashboard (lihat chart KIB A-F)
3. Klik "Input Aset" → Isi form (KIB, tahun, bulan, total)
4. Lihat data di "Data Aset" → Edit/Hapus sesuai kebutuhan
5. Kelola user di "Manajemen User" (bawah sidebar)

### **Alur Pegawai**
1. Login dengan akun pegawai
2. Lihat Dashboard dengan chart
3. Input data aset (jika diizinkan)
4. Edit profil sendiri saja

## 🐛 Troubleshooting

### **Error: Connection failed**
- Pastikan MySQL di Laragon sudah running
- Cek username/password di `inc/config.php` (default: user=root, pass=kosong)

### **Error: Table doesn't exist**
- Impor ulang `migrations/schema.sql` ke database `aset_sekolah`

### **Chart tidak muncul**
- Buka DevTools (F12) → Check console untuk error
- Pastikan API endpoint `/api/assets.php?kib=A&year=2025` bisa diakses

### **Login tidak berhasil**
- Cek apakah email ada di database
- Cek password (gunakan akun demo jika lupa)

## 📦 Update & Maintenance

### **Backup Database**
```bash
# Command line
mysqldump -u root aset_sekolah > backup_aset_sekolah.sql
```

### **Restore Database**
```bash
mysql -u root aset_sekolah < backup_aset_sekolah.sql
```

## 🚀 Development Tips

- Aktifkan `APP_DEBUG=true` di `inc/config.php` untuk debugging
- Gunakan `error_log()` untuk debug tanpa output ke browser
- Test semua fitur di browser berbeda (Chrome, Firefox, Safari)
- Validate email input lebih ketat dengan regex jika diperlukan

## 📧 Support & Kontribusi

Untuk bug report atau feature request, silakan hubungi tim development.

---

**Dibuat dengan ❤️ untuk Sistem Monitoring Aset Sekolah**  
*Version 1.0 - January 2026*
