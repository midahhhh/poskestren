const express = require('express');
const path = require('path');
const router = express.Router();

// GET /dashboard/admin — Dashboard Admin / Petugas Kesehatan
router.get('/admin', (req, res) => {
  res.sendFile(path.join(__dirname, '..', 'views', 'dashboard.html'));
});

// Alias route untuk nakes (petugas kesehatan)
router.get('/nakes', (req, res) => {
  res.sendFile(path.join(__dirname, '..', 'views', 'dashboard.html'));
});

// Placeholder untuk farmasi (Fase 4)
router.get('/farmasi', (req, res) => {
  res.sendFile(path.join(__dirname, '..', 'views', 'dashboard.html'));
});

// Placeholder untuk santri/wali (Fase 4)
router.get('/santri', (req, res) => {
  res.sendFile(path.join(__dirname, '..', 'views', 'dashboard.html'));
});

router.get('/wali', (req, res) => {
  res.sendFile(path.join(__dirname, '..', 'views', 'dashboard.html'));
});

// GET /dashboard — redirect ke admin
router.get('/', (req, res) => {
  res.redirect('/dashboard/admin');
});

module.exports = router;
