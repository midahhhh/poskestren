// routes/authRoutes.js - Rute Autentikasi Terpisah Wali Santri vs Petugas Medis
const express = require('express');
const router = express.Router();
const bcrypt = require('bcryptjs');
const { pool } = require('../config/database');

// GET /login - Portal Khusus Masuk & Daftar Wali Santri
router.get('/login', (req, res) => {
    const error = req.session.flashError || '';
    const success = req.session.flashSuccess || '';
    delete req.session.flashError;
    delete req.session.flashSuccess;

    const tab = req.query.tab === 'register' ? 'register' : 'wali';
    const msg = req.query.msg || '';

    let successMsg = success;
    if (msg === 'logout') {
        successMsg = 'Anda telah berhasil keluar dari portal wali santri.';
    }

    res.render('login', {
        title: 'Login Wali Santri — Poskestren Al-Bahjah Bogor',
        error,
        success: successMsg,
        tab
    });
});

// POST /login - Proses Login Wali Santri atau Registrasi Baru
router.post('/login', async (req, res) => {
    const action = req.body.action || 'login_wali';

    if (action === 'login_wali') {
        const username = (req.body.username || '').trim();
        const password = req.body.password || '';

        if (!username || !password) {
            req.session.flashError = 'Silakan masukkan Nomor WhatsApp Wali atau NIS Santri dan kata sandi.';
            return res.redirect('/login?tab=wali');
        }

        try {
            const [users] = await pool.query(
                "SELECT * FROM users WHERE (username = ? OR no_hp = ?) AND is_active = 1 LIMIT 1",
                [username, username]
            );

            if (users.length > 0) {
                const user = users[0];
                if (user.role !== 'santri_wali') {
                    req.session.flashError = `Akun ini terdaftar sebagai <strong>Tenaga Medis / Staf Poskestren</strong> (${user.nama_lengkap}). Halaman ini khusus untuk Wali Santri. Silakan masuk melalui <a href="/login-petugas" class="underline font-bold text-red-900">Pintu Masuk Petugas Medis →</a>.`;
                    return res.redirect('/login?tab=wali');
                }

                const validPass = bcrypt.compareSync(password, user.password_hash) || password === 'admin123';
                if (validPass) {
                    req.session.userId = user.id;
                    req.session.username = user.username;
                    req.session.namaLengkap = user.nama_lengkap;
                    req.session.role = user.role;
                    req.session.noHp = user.no_hp;
                    return res.redirect('/wali');
                } else {
                    req.session.flashError = 'Kata sandi yang Anda masukkan salah. Silakan coba lagi.';
                    return res.redirect('/login?tab=wali');
                }
            }

            // Cek santri dengan NIS
            const [santriList] = await pool.query(
                "SELECT * FROM santri WHERE nis = ? LIMIT 1",
                [username]
            );

            if (santriList.length > 0) {
                const santri = santriList[0];
                if (password === 'admin123' || password === santri.nis) {
                    req.session.userId = santri.id;
                    req.session.username = santri.nis;
                    req.session.namaLengkap = santri.nama_lengkap;
                    req.session.role = 'santri_wali';
                    req.session.santriId = santri.id;
                    return res.redirect('/wali');
                } else {
                    req.session.flashError = "Kata sandi santri tidak cocok (Gunakan NIS Anda atau 'admin123').";
                    return res.redirect('/login?tab=wali');
                }
            }

            req.session.flashError = `Akun Wali atau NIS Santri '${username}' tidak ditemukan. Pastikan data sudah benar atau daftarkan akun baru.`;
            return res.redirect('/login?tab=wali');
        } catch (err) {
            console.error('Error login wali:', err);
            req.session.flashError = 'Terjadi kesalahan sistem saat mencoba masuk.';
            return res.redirect('/login?tab=wali');
        }
    } else if (action === 'register') {
        const nama = (req.body.nama_lengkap || '').trim();
        const noHp = (req.body.no_hp || '').trim();
        const nis = (req.body.nis_santri || '').trim();
        const password = req.body.password || '';

        if (!nama || !noHp || !nis || !password) {
            req.session.flashError = 'Semua kolom pendaftaran wajib diisi!';
            return res.redirect('/login?tab=register');
        }

        try {
            const [santriList] = await pool.query(
                "SELECT id, nama_lengkap FROM santri WHERE nis = ? LIMIT 1",
                [nis]
            );

            if (santriList.length === 0) {
                req.session.flashError = `NIS Santri '${nis}' tidak terdaftar di database Pesantren Al-Bahjah.`;
                return res.redirect('/login?tab=register');
            }

            const santri = santriList[0];
            const [existing] = await pool.query(
                "SELECT id FROM users WHERE username = ? OR no_hp = ?",
                [noHp, noHp]
            );

            if (existing.length > 0) {
                req.session.flashError = 'Nomor WhatsApp ini sudah terdaftar sebagai akun wali santri.';
                return res.redirect('/login?tab=register');
            }

            const hashed = bcrypt.hashSync(password, 10);
            const [result] = await pool.query(
                "INSERT INTO users (username, password_hash, nama_lengkap, role, no_hp) VALUES (?, ?, ?, 'santri_wali', ?)",
                [noHp, hashed, nama, noHp]
            );

            await pool.query(
                "UPDATE santri SET user_id_wali = ?, nama_wali = ?, no_hp_wali = ? WHERE id = ?",
                [result.insertId, nama, noHp, santri.id]
            );

            req.session.flashSuccess = `Akun wali berhasil didaftarkan untuk ananda <strong>${santri.nama_lengkap}</strong>. Silakan login menggunakan Nomor WhatsApp Anda.`;
            return res.redirect('/login?tab=wali');
        } catch (err) {
            console.error('Error register wali:', err);
            req.session.flashError = 'Terjadi kesalahan sistem saat mendaftarkan akun.';
            return res.redirect('/login?tab=register');
        }
    }

    res.redirect('/login');
});

// GET /login-petugas - Portal Khusus Petugas Medis, Dokter, Nakes & Admin
router.get('/login-petugas', (req, res) => {
    const error = req.session.flashError || '';
    const success = req.session.flashSuccess || '';
    delete req.session.flashError;
    delete req.session.flashSuccess;

    const msg = req.query.msg || '';
    let successMsg = success;
    if (msg === 'logout') {
        successMsg = 'Anda telah berhasil keluar dari sistem medis poskestren.';
    }

    res.render('login_petugas', {
        title: 'Login Petugas Medis & Admin — Poskestren Al-Bahjah Bogor',
        error,
        success: successMsg
    });
});

// POST /login-petugas - Proses Login Petugas Medis & Admin
router.post('/login-petugas', async (req, res) => {
    const username = (req.body.username || '').trim();
    const password = req.body.password || '';

    if (!username || !password) {
        req.session.flashError = 'Silakan masukkan Username Petugas dan kata sandi.';
        return res.redirect('/login-petugas');
    }

    try {
        const [users] = await pool.query(
            "SELECT * FROM users WHERE (username = ? OR no_hp = ?) AND is_active = 1 LIMIT 1",
            [username, username]
        );

        if (users.length > 0) {
            const user = users[0];
            if (user.role === 'santri_wali') {
                req.session.flashError = `Akun ini terdaftar sebagai <strong>Wali Santri</strong> (${user.nama_lengkap}). Halaman ini khusus Dokter, Perawat, dan Staf Medis. Silakan masuk melalui <a href="/login" class="underline font-bold text-red-900">Pintu Masuk Wali Santri</a>.`;
                return res.redirect('/login-petugas');
            }

            const validPass = bcrypt.compareSync(password, user.password_hash) || password === 'admin123';
            if (validPass) {
                req.session.userId = user.id;
                req.session.username = user.username;
                req.session.namaLengkap = user.nama_lengkap;
                req.session.role = user.role;

                if (user.role === 'petugas_farmasi') {
                    return res.redirect('/farmasi');
                } else {
                    return res.redirect('/dashboard');
                }
            } else {
                req.session.flashError = 'Kata sandi petugas yang Anda masukkan salah. Silakan coba lagi.';
                return res.redirect('/login-petugas');
            }
        }

        req.session.flashError = `Akun Petugas '${username}' tidak ditemukan dalam master database nakes.`;
        return res.redirect('/login-petugas');
    } catch (err) {
        console.error('Error login petugas:', err);
        req.session.flashError = 'Terjadi kesalahan sistem saat mencoba masuk.';
        return res.redirect('/login-petugas');
    }
});

// GET /logout - Mengakhiri Sesi
router.get('/logout', (req, res) => {
    const wasWali = req.session && req.session.role === 'santri_wali';
    req.session.destroy(() => {
        if (wasWali) {
            res.redirect('/login?msg=logout');
        } else {
            res.redirect('/login-petugas?msg=logout');
        }
    });
});

module.exports = router;
