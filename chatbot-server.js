/**
 * Chatbot Pelayanan Desa Sukamaju
 * Backend: Node.js (ES Module)
 * Provider: Nous Research / NVIDIA NIM
 * 
 * Configured with:
 * - API Key: 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
 * - Key ID:  c13e9746-5a9a-492c-b622-9a76fd963fee
 */

import http from 'node:http';
import https from 'node:https';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Helper untuk membaca .env jika ada
function loadEnv() {
    const envPath = path.join(__dirname, '.env');
    const env = {};
    if (fs.existsSync(envPath)) {
        const lines = fs.readFileSync(envPath, 'utf8').split('\n');
        for (const line of lines) {
            const trimmed = line.trim();
            if (!trimmed || trimmed.startsWith('#')) continue;
            const eqIndex = trimmed.indexOf('=');
            if (eqIndex > 0) {
                const key = trimmed.slice(0, eqIndex).trim();
                let val = trimmed.slice(eqIndex + 1).trim();
                if ((val.startsWith('"') && val.endsWith('"')) || (val.startsWith("'") && val.endsWith("'"))) {
                    val = val.slice(1, -1);
                }
                env[key] = val;
            }
        }
    }
    return env;
}

const env = loadEnv();

const CONFIG = {
    PORT: parseInt(process.env.CHATBOT_NODE_PORT || env.CHATBOT_NODE_PORT || '3001', 10),
    HOST: '0.0.0.0',
    API_KEY: process.env.NOUS_RESEARCH_API_KEY || env.NOUS_RESEARCH_API_KEY || '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',
    KEY_ID: process.env.NOUS_RESEARCH_KEY_ID || env.NOUS_RESEARCH_KEY_ID || 'c13e9746-5a9a-492c-b622-9a76fd963fee',
    NVIDIA_ENDPOINT: 'https://integrate.api.nvidia.com/v1/chat/completions',
    PREFERRED_MODELS: [
        'nousresearch/hermes-3-llama-3.1-405b',
        'nousresearch/hermes-3-llama-3.1-70b',
        'meta/llama-3.1-8b-instruct',
        'mistralai/mistral-7b-instruct-v0.3'
    ]
};

// Basis Pengetahuan Resmi Desa Sukamaju
const VILLAGE_KNOWLEDGE = [
    {
        topic: 'definisi_sktm',
        keywords: ['apa itu sktm', 'pengertian sktm', 'definisi sktm', 'arti sktm', 'fungsi sktm', 'kegunaan sktm'],
        answer: `Surat Keterangan Tidak Mampu (SKTM) adalah dokumen resmi yang diterbitkan oleh Pemerintah Desa Sukamaju untuk menerangkan bahwa suatu keluarga atau warga benar-benar berada dalam kondisi ekonomi kurang mampu/rentan.\n\nFungsi utama SKTM:\n1. Keringanan atau pembebasan biaya perawatan kesehatan/Rumah Sakit dan pendaftaran BPJS PBI (Penerima Bantuan Iuran).\n2. Pengajuan beasiswa pendidikan, keringanan biaya sekolah, atau Uang Kuliah Tunggal (UKT).\n3. Persyaratan permohonan bantuan sosial (bansos) resmi dari pemerintah.`
    },
    {
        topic: 'persyaratan_sktm',
        keywords: ['apa saja persyaratan', 'syarat sktm', 'persyaratan sktm', 'syaratnya apa', 'syarat pengajuan', 'apa yang dibutuhkan', 'syarat'],
        answer: `Persyaratan resmi permohonan SKTM di Desa Sukamaju:\n\n1. e-KTP Pemohon (Asli atau foto/scan yang jelas dan masih berlaku).\n2. Kartu Keluarga (KK) yang masih berlaku dan memuat nama pemohon.\n3. Formulir pengajuan online yang diisi lengkap (identitas, pekerjaan, penghasilan bulanan, jumlah tanggungan, dan rincian kondisi ekonomi).\n4. Dokumen pendukung seperti Surat Pengantar RT/RW setempat (opsional tetapi sangat disarankan untuk mempercepat verifikasi).`
    },
    {
        topic: 'cara_mengajukan',
        keywords: ['bagaimana cara mengajukan', 'cara ajukan', 'cara buat sktm', 'alur pengajuan', 'langkah pengajuan', 'tahap pengajuan', 'cara mendaftar'],
        answer: `Langkah-langkah mengajukan SKTM secara mandiri di sistem Pelayanan Desa:\n\n1. Masuk (Login) ke akun warga Anda pada menu Beranda / Halaman Masuk.\n2. Klik menu "Ajukan SKTM" pada bilah navigasi akun warga.\n3. Lengkapi data permohonan: tujuan peruntukan, data pekerjaan, estimasi penghasilan, dan tanggungan keluarga.\n4. Unggah foto atau scan dokumen e-KTP dan Kartu Keluarga (format JPG, PNG, atau PDF).\n5. Klik tombol "Kirim Pengajuan Sekarang".\n6. Pengajuan Anda akan otomatis masuk ke antrian pemeriksaan Staff Desa.`
    },
    {
        topic: 'estimasi_waktu',
        keywords: ['berapa lama prosesnya', 'berapa hari', 'durasi proses', 'lama pembuatan', 'kapan selesai', 'estimasi waktu', 'jam pelayanan', 'jam buka', 'jam kantor'],
        answer: `Estimasi waktu pemrosesan SKTM adalah 1 hingga 3 hari kerja pada hari dan jam operasional kantor desa.\n\nAlur tahapan:\n1. Pemeriksaan berkas oleh Staff Desa.\n2. Verifikasi faktual dan kelayakan oleh Kasi Pelayanan (Supervisor).\n3. Persetujuan akhir dan tanda tangan/penomoran surat oleh Kepala Desa.\n\nJam Pelayanan Kantor Desa:\nSenin – Jumat, pukul 08.00 – 15.00 WIB (Hari Sabtu, Minggu, dan Libur Nasional tutup).`
    },
    {
        topic: 'cara_melihat_status',
        keywords: ['bagaimana cara melihat status', 'cara cek status', 'status pengajuan', 'lacak pengajuan', 'tracking', 'pantau berkas', 'cek berkas'],
        answer: `Anda dapat memantau status pengajuan SKTM Anda secara langsung (real-time):\n\n1. Buka menu "Riwayat Pengajuan" pada dashboard warga Anda.\n2. Pilih pengajuan yang ingin dilihat, lalu klik "Lacak Status".\n3. Anda akan melihat timeline transparan 6 tahapan:\n   • 1. Pengajuan Dibuat\n   • 2. Diajukan oleh Pemohon\n   • 3. Pemeriksaan Staff\n   • 4. Verifikasi Supervisor\n   • 5. Persetujuan Kepala Desa\n   • 6. SKTM Diterbitkan (siap diunduh PDF)\n4. Anda juga akan menerima notifikasi setiap ada perubahan status atau catatan dari petugas.`
    },
    {
        topic: 'mengapa_perlu_perbaikan',
        keywords: ['mengapa pengajuan saya perlu diperbaiki', 'perlu perbaikan', 'need_revision', 'kenapa revisi', 'alasan perbaikan', 'berkas salah'],
        answer: `Status "Perlu Perbaikan" (NEED_REVISION) diberikan oleh petugas apabila berkas atau data permohonan belum memenuhi syarat administrasi, antara lain:\n\n1. Foto dokumen e-KTP atau Kartu Keluarga buram, gelap, miring, atau tidak terbaca.\n2. Terdapat perbedaan data antara formulir dengan dokumen fisik (misal: NIK atau Nomor KK salah ketik).\n3. Keterangan alasan/keperluan permohonan belum diisi dengan jelas.\n\nCara memperbaiki:\nBuka detail pengajuan Anda di dashboard, baca catatan perbaikan dari petugas, klik "Perbaiki Berkas", lalu perbarui data atau unggah dokumen yang benar.`
    },
    {
        topic: 'dokumen_upload',
        keywords: ['dokumen apa yang harus di-upload', 'dokumen apa yang harus diupload', 'file apa yang diupload', 'dokumen wajib', 'upload apa saja'],
        answer: `Dokumen yang wajib diunggah pada saat mengajukan SKTM:\n\n1. Foto/Scan e-KTP Pemohon:\n   • Format file: JPG, PNG, atau PDF.\n   • Ukuran maksimal: 2 MB.\n   • Pastikan seluruh tulisan, foto, dan 16 digit NIK terbaca dengan sangat jelas.\n\n2. Foto/Scan Kartu Keluarga (KK):\n   • Format file: JPG, PNG, atau PDF.\n   • Ukuran maksimal: 2 MB.\n   • Pastikan seluruh lembar terlihat utuh dan nomor KK terbaca jelas.\n\n3. Dokumen Pendukung (Opsional):\n   • Surat Pengantar RT/RW atau foto kondisi rumah tinggal (maksimal 2 MB).`
    },
    {
        topic: 'biaya_pelayanan',
        keywords: ['biaya', 'apakah bayar', 'berapa biayanya', 'gratis atau bayar', 'tarif sktm', 'pungutan'],
        answer: `Pelayanan pembuatan Surat Keterangan Tidak Mampu (SKTM) di Pemerintah Desa Sukamaju adalah 100% GRATIS dan bebas dari segala bentuk pungutan liar (pungli). Jika ada pihak yang memungut biaya, silakan laporkan ke kantor desa.`
    }
];

const FALLBACK_OFFICIAL = `Mohon maaf, pertanyaan Anda belum tercantum dalam basis data resmi pelayanan SKTM kami. Untuk memastikan keakuratan informasi dan menghindari aturan yang tidak sesuai, silakan menghubungi petugas pelayanan desa kami secara langsung:

🏛️ Kantor Pemerintah Desa Sukamaju
📍 Alamat: Jl. Raya Sukamaju No. 1, Kec. Cibinong, Kab. Bogor, Jawa Barat
🕒 Jam Operasional: Senin – Jumat, pukul 08.00 – 15.00 WIB
📞 WhatsApp Pelayanan: 0812-3456-7890
✉️ Email Resmi: pelayanan@sukamaju.desa.id`;

function findLocalAnswer(message) {
    const text = message.toLowerCase().trim();

    // Salam / Sapaan
    if (/^(halo|hai|pagi|siang|sore|malam|assalamu[\w\s]+|permisi|tes)/i.test(text)) {
        return `Halo, selamat datang di Pelayanan Desa Sukamaju! Saya adalah **Asisten AI Pelayanan Desa (Nous Research)**.\n\nSaya siap membantu Anda memahami informasi seputar Surat Keterangan Tidak Mampu (SKTM). Anda dapat menanyakan:\n• Apa itu SKTM & fungsinya?\n• Apa saja persyaratan yang dibutuhkan?\n• Bagaimana alur dan cara mengajukan?\n• Berapa lama proses verifikasi?\n• Dokumen apa saja yang harus di-upload?\n\nAda yang bisa saya bantu hari ini?`;
    }

    // Terima kasih
    if (/^(terima kasih|makasih|thanks|matur nuwun|nuhun)/i.test(text)) {
        return `Sama-sama! Senang dapat membantu Anda. Jangan ragu bertanya kembali jika ada hal lain seputar pelayanan Desa Sukamaju yang ingin diketahui. Semoga harinya menyenangkan!`;
    }

    // Cocokkan kata kunci
    for (const item of VILLAGE_KNOWLEDGE) {
        for (const kw of item.keywords) {
            if (text.includes(kw)) {
                return item.answer;
            }
        }
    }

    // Heuristik intent
    if (text.includes('upload') || text.includes('unggah') || text.includes('dokumen') || text.includes('berkas')) {
        return VILLAGE_KNOWLEDGE.find(i => i.topic === 'dokumen_upload')?.answer;
    }
    if (text.includes('syarat')) {
        return VILLAGE_KNOWLEDGE.find(i => i.topic === 'persyaratan_sktm')?.answer;
    }
    if (text.includes('cara') || text.includes('alur') || text.includes('daftar') || text.includes('tahap')) {
        return VILLAGE_KNOWLEDGE.find(i => i.topic === 'cara_mengajukan')?.answer;
    }
    if (text.includes('lama') || text.includes('waktu') || text.includes('durasi') || text.includes('jam')) {
        return VILLAGE_KNOWLEDGE.find(i => i.topic === 'estimasi_waktu')?.answer;
    }
    if (text.includes('status') || text.includes('lacak') || text.includes('tracking')) {
        return VILLAGE_KNOWLEDGE.find(i => i.topic === 'cara_melihat_status')?.answer;
    }
    if (text.includes('biaya') || text.includes('bayar') || text.includes('tarif') || text.includes('gratis')) {
        return VILLAGE_KNOWLEDGE.find(i => i.topic === 'biaya_pelayanan')?.answer;
    }

    return FALLBACK_OFFICIAL;
}

// Handler request ke API Nous Research / NVIDIA NIM
async function requestNousResearchAi(userMessage, conversationHistory = []) {
    const systemPrompt = `Anda adalah Asisten Virtual Pelayanan Desa Sukamaju (ditenagai oleh model AI Nous Research).
Tugas Anda adalah melayani dan menjawab pertanyaan warga mengenai pengajuan Surat Keterangan Tidak Mampu (SKTM) dan administrasi desa.
Ketentuan:
1. Jawab berdasarkan informasi resmi Desa Sukamaju: Pelayanan SKTM 100% GRATIS, butuh e-KTP dan KK (maks 2MB JPG/PNG/PDF), proses 1-3 hari kerja (Senin-Jumat 08.00-15.00 WIB).
2. Bersikap sangat ramah, santun, jelas, dan membantu.
3. Jika pertanyaan di luar kewenangan atau tidak Anda ketahui dengan pasti, arahkan pengguna ke Kantor Desa Sukamaju (Jl. Raya Sukamaju No. 1, WA: 0812-3456-7890).`;

    const messages = [
        { role: 'system', content: systemPrompt },
        ...conversationHistory.slice(-6).map(h => ({
            role: h.role === 'user' ? 'user' : 'assistant',
            content: String(h.content || '')
        })),
        { role: 'user', content: userMessage }
    ];

    for (const model of CONFIG.PREFERRED_MODELS) {
        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 7000); // 7 detik timeout

            const response = await fetch(CONFIG.NVIDIA_ENDPOINT, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${CONFIG.API_KEY}`,
                    'X-Key-Id': CONFIG.KEY_ID,
                    'User-Agent': 'PelayananDesa-Chatbot/1.0 (NousResearch)'
                },
                body: JSON.stringify({
                    model: model,
                    messages: messages,
                    temperature: 0.5,
                    max_tokens: 600
                }),
                signal: controller.signal
            });

            clearTimeout(timeoutId);

            if (response.ok) {
                const data = await response.json();
                const replyText = data.choices?.[0]?.message?.content;
                if (replyText && replyText.trim().length > 0) {
                    return {
                        success: true,
                        reply: replyText.trim(),
                        provider: `Nous Research (${model})`,
                        key_id: CONFIG.KEY_ID
                    };
                }
            }
        } catch (err) {
            // Lanjut ke fallback jika network atau timeout
        }
    }

    // Jika upstream API Nous Research sedang maintenance / quota / unavailable, gunakan basis pengetahuan desa
    return {
        success: true,
        reply: findLocalAnswer(userMessage),
        provider: 'Nous Research (Village Knowledge Base)',
        key_id: CONFIG.KEY_ID,
        is_fallback: true
    };
}

// Buat HTTP Server Native Node.js
const server = http.createServer(async (req, res) => {
    // Header CORS standar
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-CSRF-TOKEN, X-Key-Id');

    if (req.method === 'OPTIONS') {
        res.writeHead(204);
        res.end();
        return;
    }

    const url = new URL(req.url, `http://${req.headers.host || 'localhost'}`);

    // Endpoint Health Check
    if (req.method === 'GET' && (url.pathname === '/health' || url.pathname === '/api/health')) {
        res.writeHead(200, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({
            status: 'online',
            service: 'Pelayanan Desa AI Chatbot (Node.js)',
            provider: 'Nous Research / NVIDIA NIM',
            key_id: CONFIG.KEY_ID,
            port: CONFIG.PORT,
            timestamp: new Date().toISOString()
        }));
        return;
    }

    // Endpoint Chat API
    if (req.method === 'POST' && (url.pathname === '/api/chat' || url.pathname === '/chat')) {
        let body = '';
        req.on('data', chunk => {
            body += chunk.toString();
            // Cegah body terlalu besar (maks 50KB)
            if (body.length > 50000) {
                req.destroy();
            }
        });

        req.on('end', async () => {
            try {
                const parsed = JSON.parse(body || '{}');
                const userMessage = (parsed.message || '').trim();

                if (!userMessage) {
                    res.writeHead(400, { 'Content-Type': 'application/json' });
                    res.end(JSON.stringify({
                        success: false,
                        error: 'Pesan pertanyaan tidak boleh kosong.'
                    }));
                    return;
                }

                if (userMessage.length > 1000) {
                    res.writeHead(400, { 'Content-Type': 'application/json' });
                    res.end(JSON.stringify({
                        success: false,
                        error: 'Panjang pesan maksimal 1000 karakter.'
                    }));
                    return;
                }

                const history = Array.isArray(parsed.history) ? parsed.history : [];
                const result = await requestNousResearchAi(userMessage, history);

                res.writeHead(200, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify({
                    success: true,
                    data: {
                        reply: result.reply,
                        provider: result.provider,
                        key_id: result.key_id,
                        created_at: new Date().toISOString()
                    }
                }));
            } catch (err) {
                res.writeHead(500, { 'Content-Type': 'application/json' });
                res.end(JSON.stringify({
                    success: false,
                    error: 'Terjadi kesalahan saat memproses pesan: ' + err.message
                }));
            }
        });
        return;
    }

    // Route tidak ditemukan
    res.writeHead(404, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({
        success: false,
        error: 'Endpoint tidak ditemukan'
    }));
});

server.listen(CONFIG.PORT, CONFIG.HOST, () => {
    console.log(`====================================================`);
    console.log(`🤖 Chatbot Pelayanan Desa (Node.js) Berjalan!`);
    console.log(`📍 Endpoint: http://127.0.0.1:${CONFIG.PORT}/api/chat`);
    console.log(`🔑 Key ID  : ${CONFIG.KEY_ID}`);
    console.log(`🧠 Provider: Nous Research / NVIDIA NIM`);
    console.log(`====================================================`);
});

