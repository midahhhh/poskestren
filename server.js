// server.js — Entry Point Express.js SPK Triase Poskestren Al-Bahjah Bogor
require("dotenv").config();

const express = require("express");
const session = require("express-session");
const path    = require("path");

const app = express();

// View Engine
app.set("view engine", "ejs");
app.set("views", path.join(__dirname, "views"));

// Static Assets
app.use(express.static(path.join(__dirname, "public")));

// Body Parsers
app.use(express.urlencoded({ extended: true }));
app.use(express.json());

// Session
app.use(session({
    secret: process.env.SESSION_SECRET || "poskestren_albahjah_secret_2026",
    resave: false,
    saveUninitialized: false,
    cookie: { maxAge: 8 * 60 * 60 * 1000, httpOnly: true, sameSite: "lax" }
}));

// Global Template Locals
app.use((req, res, next) => {
    res.locals.session      = req.session;
    res.locals.userRole     = req.session.role || null;
    res.locals.userNama     = req.session.namaLengkap || null;
    res.locals.flashError   = req.session.flashError   || null;
    res.locals.flashSuccess = req.session.flashSuccess || null;
    delete req.session.flashError;
    delete req.session.flashSuccess;
    next();
});

// Routes
const authRoutes       = require("./routes/authRoutes");
const dashboardRoutes  = require("./routes/dashboardRoutes");
const santriRoutes     = require("./routes/santriRoutes");
const nakesRoutes      = require("./routes/nakesRoutes");
const kriteriaRoutes   = require("./routes/kriteriaRoutes");
const triaseRoutes     = require("./routes/triaseRoutes");
const rekamMedisRoutes = require("./routes/rekamMedisRoutes");
const farmasiRoutes    = require("./routes/farmasiRoutes");
const waliRoutes       = require("./routes/waliRoutes");
const laporanRoutes    = require("./routes/laporanRoutes");

app.use("/", authRoutes);
app.use("/dashboard", dashboardRoutes);
app.use("/santri", santriRoutes);
app.use("/nakes", nakesRoutes);
app.use("/kriteria", kriteriaRoutes);
app.use("/triase", triaseRoutes);
app.use("/rekam-medis", rekamMedisRoutes);
app.use("/farmasi", farmasiRoutes);
app.use("/wali", waliRoutes);
app.use("/laporan", laporanRoutes);

// Root Redirect
app.get("/", (req, res) => {
    if (!req.session.userId) return res.redirect("/login-petugas");
    if (req.session.role === "santri_wali") return res.redirect("/wali");
    if (req.session.role === "petugas_farmasi") return res.redirect("/farmasi");
    return res.redirect("/dashboard");
});

// 404
app.use((req, res) => {
    res.status(404).render("error", { title: "Halaman Tidak Ditemukan", code: 404, message: "Halaman yang Anda cari tidak tersedia." });
});

// Error Handler
app.use((err, req, res, next) => {
    console.error("[SERVER ERROR]", err);
    res.status(500).render("error", { title: "Kesalahan Server", code: 500, message: "Terjadi kesalahan internal. Silakan coba lagi." });
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
    console.log(`\n  SPK Poskestren Al-Bahjah — Server berjalan di http://localhost:${PORT}`);
    console.log(`   Login Petugas : http://localhost:${PORT}/login-petugas`);
    console.log(`   Portal Wali   : http://localhost:${PORT}/login\n`);
});
