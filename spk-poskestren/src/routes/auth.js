const express = require('express');
const path = require('path');
const router = express.Router();

// GET /auth/login — Login Page
router.get('/login', (req, res) => {
  res.sendFile(path.join(__dirname, '..', 'views', 'login.html'));
});

// POST /auth/login — Demo Login Handler
router.post('/login', (req, res) => {
  const { username, password, role } = req.body;

  if (!username || !password || !role) {
    return res.status(400).json({
      success: false,
      message: 'Username, password, dan role wajib diisi.'
    });
  }

  // Demo credentials (Fase 1 — tidak ada database)
  const demoAccounts = {
    admin:    { username: 'admin',   password: 'demo123', redirect: '/dashboard/admin' },
    nakes:    { username: 'nakes',   password: 'demo123', redirect: '/dashboard/admin' },
    farmasi:  { username: 'farmasi', password: 'demo123', redirect: '/dashboard/farmasi' },
    santri:   { username: 'santri',  password: 'demo123', redirect: '/dashboard/santri' },
    wali:     { username: 'wali',    password: 'demo123', redirect: '/dashboard/wali' },
  };

  const account = demoAccounts[role];

  if (account && username === account.username && password === account.password) {
    return res.json({
      success: true,
      message: `Login demo berhasil sebagai ${role}.`,
      redirect: account.redirect,
      role
    });
  }

  return res.status(401).json({
    success: false,
    message: 'Username atau password salah. Gunakan kredensial demo yang tertera.'
  });
});

// GET /auth/logout
router.get('/logout', (req, res) => {
  res.redirect('/');
});

module.exports = router;
