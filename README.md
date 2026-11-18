SIPELDES — Sistem Informasi Pelayanan Desa
Aplikasi web pelayanan administrasi desa dengan fokus proses pengajuan dan penerbitan Surat Keterangan Tidak Mampu (SKTM), pelacakan status, notifikasi, dan bantuan AI.

1. Identitas Mahasiswa dan Proyek
Lengkapi bagian berikut sebelum repositori dikumpulkan.
Informasi	Keterangan

Nama Mahasiswa	Muhamad Riski Rizaldi

NIM	41257123

Kelas	1B

Kelompok	DEVLYN

Mata Kuliah	Rekayasa Perangkat Lunak

Dosen Pengampu	Yudhistira Arie Wijaya, M.Kom., CDS.

Tema Pelayanan Publik Desa Berbasis Web dengan Dukungan Kecerdasan Artifisial (AI)
Nama Aplikasi	SIPELDES — Sistem Informasi Pelayanan Desa


Pernyataan pengerjaan mandiri: Aplikasi dan dokumen proyek ini disiapkan sebagai hasil pengerjaan individu. Jika proyek berada dalam kelompok bertema sama, setiap mahasiswa tetap bertanggung jawab membangun dan memahami aplikasi utuh miliknya sendiri.
2. Deskripsi dan Latar Belakang Aplikasi
Pelayanan administrasi desa yang masih dilakukan secara manual dapat menimbulkan kendala seperti proses pengajuan yang memerlukan kunjungan berulang, dokumen persyaratan yang sulit dilacak, informasi status yang tidak transparan, serta pencatatan pemeriksaan yang tersebar.

SIPELDES dikembangkan untuk mendukung digitalisasi proses pelayanan administrasi desa. Fokus implementasi saat ini adalah layanan SKTM. Masyarakat dapat membuat pengajuan, melengkapi data dan dokumen, mengirim permohonan, memantau perkembangan, serta menerima dokumen PDF setelah permohonan disetujui. Petugas desa memeriksa dan memverifikasi pengajuan secara berjenjang sesuai peran.
Tujuan
1. Mempermudah masyarakat mengajukan permohonan SKTM secara daring.
2. Membantu petugas mengelola pemeriksaan dan verifikasi permohonan secara terstruktur.
3. Menyediakan riwayat status agar proses pelayanan dapat ditelusuri.
4. Mengurangi pemeriksaan administratif berulang melalui bantuan pemeriksaan dokumen berbasis AI.
5. Menyediakan informasi persyaratan dan alur pelayanan melalui AI Assistant.
Ruang Lingkup
Fitur utama yang tercakup pada aplikasi:
- Registrasi, autentikasi, dan pengelolaan profil penduduk.
- Pembuatan, penyimpanan draft, dan pengiriman pengajuan SKTM.
- Unggah dokumen identitas dan dokumen pendukung.
- Pemeriksaan berkas oleh staff dengan dukungan hasil pemeriksaan AI.
- Verifikasi berjenjang oleh staff, supervisor, dan keputusan akhir kepala desa.
- Pencatatan status dan riwayat pengajuan.
- Pembuatan dokumen SKTM dalam format PDF setelah disetujui.
- Notifikasi aplikasi dan integrasi notifikasi WhatsApp melalui konfigurasi gateway.
- AI Assistant untuk membantu menjawab pertanyaan seputar pelayanan desa.
- Pengelolaan pengguna, peran, dan konfigurasi sistem oleh administrator.
3. Fitur Kecerdasan Artifisial (AI)
AI digunakan sebagai fitur pendukung proses pelayanan, bukan sebagai pengambil keputusan akhir administrasi.
3.1 AI Document Checker
Fitur ini membantu pemeriksaan awal dokumen yang diunggah, khususnya dokumen identitas dan kesesuaian informasi dengan data pengajuan. Hasil pemeriksaan menjadi bahan pertimbangan petugas. Keputusan verifikasi dan persetujuan tetap dilakukan oleh petugas berwenang.

Konfigurasi driver yang tersedia pada aplikasi:
- mock: driver simulasi untuk pengujian tanpa layanan AI eksternal.
- gemini: opsi integrasi model Google Gemini apabila API key dan konfigurasi yang diperlukan telah disediakan.
3.2 AI Assistant
AI Assistant membantu masyarakat memperoleh informasi terkait persyaratan dan alur layanan desa. Aplikasi mendukung sumber jawaban berbasis knowledge base dan opsi konfigurasi model AI eksternal.
3.3 Konfigurasi AI
Pengaturan utama pada .env:
AI_DOCUMENT_CHECKER_DRIVER=mock
AI_CHAT_DRIVER=knowledge_base
AI_API_KEY=
AI_MODEL=gemini-1.5-flash
Untuk menggunakan layanan AI eksternal, periksa kembali dukungan driver pada kode aplikasi, isi API key melalui environment lokal/server, dan lakukan pengujian. Jangan memasukkan API key atau kredensial rahasia ke repositori publik.

Catatan: Jika konfigurasi masih menggunakan mock atau knowledge_base, jangan menyatakan bahwa seluruh fitur AI eksternal telah berjalan real-time. Status demonstrasi harus mengikuti konfigurasi yang benar-benar digunakan saat presentasi.
4. Teknologi yang Digunakan
Bagian	Teknologi
Bahasa backend	PHP ^8.3
Framework backend	Laravel ^13.17
Database	SQLite untuk konfigurasi pengembangan bawaan; MySQL dapat digunakan setelah konfigurasi
ORM	Laravel Eloquent
Frontend	Blade, JavaScript, CSS, Vite, Tailwind CSS
Pembuatan PDF	Barryvdh Laravel DomPDF
Pengujian otomatis	PHPUnit melalui perintah php artisan test
Code formatting	Laravel Pint
Integrasi AI	Driver AI dokumen dan AI Assistant yang dapat dikonfigurasi
Notifikasi WhatsApp	Integrasi gateway Fonnte yang dapat dikonfigurasi


5. Aktor dan Hak Akses
Aktor	Tanggung Jawab Utama
Masyarakat	Registrasi, mengelola profil, membuat dan mengirim pengajuan SKTM, memperbaiki dokumen, melihat status, serta mengunduh surat yang telah disetujui
Staff Desa	Memeriksa kelengkapan berkas, meninjau hasil bantuan AI, memberikan catatan, dan melakukan verifikasi tahap staff
Supervisor	Melakukan pemeriksaan/verifikasi tahap supervisor dan memberikan hasil pemeriksaan sesuai kewenangannya
Kepala Desa	Memberikan keputusan akhir persetujuan atau penolakan permohonan
Administrator	Mengelola pengguna, peran, serta konfigurasi sistem sesuai fitur administrasi yang tersedia


6. Alur Proses Pengajuan SKTM
7. Masyarakat melakukan registrasi atau login.
8. Masyarakat melengkapi profil dan mengisi formulir pengajuan SKTM.
9. Pengajuan dapat disimpan sebagai draft sebelum dikirim.
10. Masyarakat mengunggah dokumen persyaratan dan mengirim pengajuan.
11. Sistem menyimpan data pengajuan dan memperbarui status.
12. Staff memeriksa dokumen serta dapat menggunakan hasil pemeriksaan AI sebagai bantuan.
13. Jika dokumen perlu diperbaiki, pengajuan dikembalikan kepada masyarakat dengan catatan.
14. Jika memenuhi pemeriksaan staff, pengajuan diteruskan ke supervisor.
15. Supervisor melakukan verifikasi tahap berikutnya.
16. Kepala Desa memberikan keputusan akhir.
17. Jika disetujui, sistem membuat dokumen SKTM PDF dan menyimpan riwayat perubahan status.
18. Masyarakat memperoleh notifikasi dan dapat mengakses dokumen sesuai hak aksesnya.
Status yang digunakan pada alur aplikasi mencakup DRAFT, SUBMITTED, UNDER_REVIEW, NEED_REVISION, VERIFIED_BY_STAFF, VERIFIED_BY_SUPERVISOR, APPROVED, dan REJECTED.
7. Kebutuhan Sistem
7.1 Perangkat Lunak
- PHP versi 8.3 atau lebih baru yang sesuai dengan batas versi proyek.
- Composer 2.x.
- Node.js dan npm.
- SQLite atau MySQL.
- Web browser modern.
- Git untuk pengelolaan versi.
7.2 Ekstensi PHP
Pastikan ekstensi yang diperlukan Laravel dan dependensi proyek tersedia, termasuk pdo, pdo_sqlite (untuk SQLite), mbstring, openssl, fileinfo, tokenizer, xml, ctype, json, dan curl.
8. Petunjuk Instalasi (Setup Instruction)
8.1 Clone Repositori
Ganti URL di bawah dengan URL repositori GitHub milik mahasiswa.
git clone [URL_REPOSITORY_GITHUB]
cd "pelayanan desa"
Atau, jika proyek diperoleh sebagai ZIP, ekstrak arsip kemudian masuk ke folder proyek.
8.2 Instal Dependensi
composer install
npm install
8.3 Siapkan Environment
cp .env.example .env
php artisan key:generate
Pada Windows Command Prompt, salin .env.example menjadi .env secara manual atau gunakan:
copy .env.example .env
8.4 Konfigurasi Database
Konfigurasi bawaan .env.example menggunakan SQLite:
DB_CONNECTION=sqlite
Pastikan file database SQLite tersedia. Jika belum ada, buat file database/database.sqlite.

Untuk menggunakan MySQL, ubah DB_CONNECTION dan isi host, port, nama database, username, serta password sesuai lingkungan lokal.
8.5 Migrasi Database
php artisan migrate --seed
Perintah --seed menjalankan data awal yang disediakan proyek. Gunakan hanya pada database pengembangan atau database yang memang disiapkan untuk pengujian.
8.6 Build Frontend
npm run build
8.7 Jalankan Aplikasi
php artisan serve
Buka alamat berikut pada browser:
http://127.0.0.1:8000
Untuk pengembangan frontend secara langsung, jalankan Vite pada terminal terpisah:
npm run dev
8.8 Menjalankan Test
php artisan test
Perintah tersebut menjalankan test suite yang tersedia di folder tests. Hasil pengujian harus diperiksa pada lingkungan masing-masing. Jangan mencantumkan klaim seluruh test berhasil sebelum perintah tersebut benar-benar selesai dengan status lulus.
9. Struktur Direktori Penting
pelayanan desa/
├── app/                  # Kode aplikasi: model, controller, service, dan lainnya
├── bootstrap/            # Bootstrap aplikasi Laravel
├── config/               # Konfigurasi aplikasi
├── database/
│   ├── migrations/       # Struktur tabel database
│   ├── seeders/          # Data awal pengembangan
│   └── factories/        # Factory data pengujian
├── public/               # Entry point dan aset publik
├── resources/
│   ├── views/            # Tampilan Blade
│   ├── css/              # CSS
│   └── js/               # JavaScript
├── routes/               # Definisi route
├── storage/              # File dan log aplikasi
├── tests/
│   ├── Feature/          # Pengujian fitur
│   └── Unit/             # Pengujian unit
├── .env.example          # Contoh konfigurasi environment
├── artisan               # CLI Laravel
├── composer.json         # Dependensi PHP
└── package.json          # Dependensi frontend
10. Dokumen Perancangan dan Proyek
Lengkapi tautan sesuai berkas atau halaman yang benar-benar sudah diunggah.
Dokumen / Artefak	Tautan
Dokumen PRD/SRS	[ISI LINK PRD/SRS PDF]
Dokumen SDD / UML	[ISI LINK SDD PDF]
Prototipe UI/UX Figma	[ISI LINK FIGMA — AKSES VIEW]
Repositori GitHub Publik	[ISI LINK REPOSITORY GITHUB]


Diagram perancangan yang disertakan pada dokumen SDD meliputi:
1. Use Case Diagram dan skenario use case.
2. Activity Diagram alur bisnis dan proses AI.
3. Class Diagram.
4. Sequence Diagram.
5. Entity Relationship Diagram (ERD).
6. Tautan Video Presentasi (Wajib Diisi)
Sesuai panduan proyek akhir, cantumkan tautan video yang dapat diakses dosen. Video dapat diunggah ke YouTube dengan status Unlisted/Public atau Google Drive dengan akses Anyone with the link.
Tahap	Materi dan Durasi	Tautan Video
Video Progres 1	PRD dan konsep aplikasi, 3–5 menit	[ISI LINK VIDEO PROGRES 1]
Video Progres 2	Penjelasan UML dan UI/UX Figma, 5–7 menit	[ISI LINK VIDEO PROGRES 2]
Video Final	Presentasi dan live demo aplikasi, 8–12 menit	[ISI LINK VIDEO FINAL]


Ketentuan perekaman: tampilkan wajah melalui webcam selama presentasi, gunakan resolusi minimal 720p, dan pastikan audio terdengar jelas. Video final perlu memperlihatkan demonstrasi aplikasi dan pengujian fitur AI secara langsung (real-time), bukan hanya slide atau screenshot.
12. Pengujian dan CI/CD
12.1 Pengujian
Proyek menyediakan test otomatis pada direktori tests/. Untuk menjalankan seluruh pengujian:
php artisan test
Contoh pengujian yang terdapat pada proyek mencakup autentikasi, hak akses berbasis peran, pengajuan SKTM, alur staff/supervisor/kepala desa, pemeriksaan dokumen AI, notifikasi, pembuatan PDF, dan aspek keamanan.

Status aktual pengujian: hasil harus diambil dari eksekusi test pada mesin pengembang atau pipeline. README ini tidak menyatakan test telah lulus karena hasilnya belum diverifikasi pada lingkungan pengumpulan.
12.2 CI/CD GitHub Actions
Panduan tugas meminta pipeline GitHub Actions pada:
.github/workflows/ci.yml
Pada salinan proyek yang menjadi dasar README ini, file workflow tersebut belum tersedia. Oleh karena itu, CI/CD belum dapat dinyatakan sudah aktif. Tambahkan workflow, jalankan pipeline pada GitHub, dan pastikan hasil pemeriksaan tampil sukses sebelum mengubah status bagian ini.
13. Keamanan dan Pengelolaan Konfigurasi
- Jangan mengunggah file .env ke repositori publik.
- Jangan menuliskan API key, token WhatsApp, password database, atau kredensial pribadi ke README.
- Gunakan .env.example untuk mendokumentasikan nama variabel tanpa nilai rahasia.
- Batasi akses dokumen penduduk sesuai hak akses pengguna.
- Gunakan akun dan data dummy untuk demonstrasi.
- Jangan gunakan kredensial bawaan pengembangan pada server produksi.
- Pastikan konfigurasi APP_DEBUG=false pada lingkungan produksi.
14. Batasan dan Pengembangan Lanjutan
15. Fokus layanan yang terdokumentasi pada proyek ini adalah pengajuan SKTM; layanan administrasi desa lain dapat dikembangkan kemudian.
16. Integrasi AI eksternal bergantung pada driver, API key, koneksi internet, dan konfigurasi layanan yang digunakan.
17. Notifikasi WhatsApp bergantung pada konfigurasi gateway dan kredensial penyedia.
18. Pengujian dan CI/CD perlu dijalankan serta diverifikasi pada repositori final.
19. Pengembangan selanjutnya dapat mencakup penambahan jenis surat, dashboard statistik pelayanan, dan peningkatan pemantauan kinerja sistem.
