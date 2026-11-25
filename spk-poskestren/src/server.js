require('dotenv').config();
const app = require('./app');

const PORT = process.env.PORT || 3000;

app.listen(PORT, () => {
  console.log('');
  console.log('╔══════════════════════════════════════════════════════╗');
  console.log('║   SPK Poskestren Al-Bahjah Bogor — Server Running   ║');
  console.log('╠══════════════════════════════════════════════════════╣');
  console.log(`║   🌐 URL  : http://localhost:${PORT}                    ║`);
  console.log(`║   ⚙️  Mode : ${process.env.NODE_ENV}                      ║`);
  console.log('║   📋 Fase : 1 — Fondasi & Modul 1-3                 ║');
  console.log('╚══════════════════════════════════════════════════════╝');
  console.log('');
});
