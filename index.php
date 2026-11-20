<?php
// index.php - Beranda Publik Poskestren Al-Bahjah Bogor
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'koneksi.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Poskestren Al-Bahjah Bogor - Sistem Triase & Kesehatan Santri</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary-green': '#16845B',
                        'deep-green': '#0B3D2E',
                        'warm-yellow': '#F4C542',
                        'emergency-red': '#D64545',
                        'app-bg': '#F5F8F6',
                        'text-main': '#182923',
                        'text-muted': '#64756D',
                        'border-color': '#DCE7E0',
                    },
                    fontFamily: { sans: ['Inter', 'sans-serif'] }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #F5F8F6; color: #182923; }
        .chat-drawer { transition: transform 0.3s ease-in-out, opacity 0.3s ease-in-out; }
    </style>
</head>
<body class="bg-app-bg text-text-main flex flex-col min-h-screen scroll-smooth">

    <!-- NAVBAR -->
    <header class="sticky top-0 z-40 bg-white/90 backdrop-blur-md border-b border-border-color">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <a href="#" class="flex items-center space-x-3 group">
                    <div class="w-11 h-11 bg-primary-green text-white rounded-xl flex items-center justify-center font-bold text-xl shadow-md group-hover:bg-deep-green transition-colors">
                        <i data-lucide="cross"></i>
                    </div>
                    <div>
                        <span class="text-lg font-bold text-deep-green block leading-tight">Poskestren</span>
                        <span class="text-xs text-text-muted font-medium block">Al-Bahjah Bogor</span>
                    </div>
                </a>

                <nav class="hidden md:flex items-center space-x-8">
                    <a href="#tentang" class="text-sm font-semibold text-text-main hover:text-primary-green transition-colors flex items-center gap-1.5">
                        <i data-lucide="info" class="w-4 h-4 text-primary-green"></i> Tentang Sistem
                    </a>
                    <a href="#latar-belakang" class="text-sm font-semibold text-text-main hover:text-primary-green transition-colors flex items-center gap-1.5">
                        <i data-lucide="building-2" class="w-4 h-4 text-primary-green"></i> Profil Pesantren
                    </a>
                    <a href="#alur-pelayanan" class="text-sm font-semibold text-text-main hover:text-primary-green transition-colors flex items-center gap-1.5">
                        <i data-lucide="git-merge" class="w-4 h-4 text-primary-green"></i> Alur Pelayanan
                    </a>
                </nav>

                <div class="hidden md:flex items-center space-x-3">
                    <a href="login_petugas.php" class="inline-flex items-center px-4 py-2 text-sm font-semibold text-deep-green border border-border-color hover:bg-gray-50 rounded-xl transition-all gap-2">
                        <i data-lucide="stethoscope" class="w-4 h-4"></i> Petugas Medis
                    </a>
                    <a href="login.php" class="inline-flex items-center px-5 py-2.5 text-sm font-semibold text-white bg-primary-green hover:bg-deep-green rounded-xl transition-all shadow-sm gap-2">
                        <i data-lucide="user-check" class="w-4 h-4"></i> Portal Wali Santri
                    </a>
                </div>

                <div class="flex md:hidden">
                    <button id="mobile-menu-btn" class="p-2 rounded-lg text-text-muted hover:text-text-main hover:bg-gray-100">
                        <i data-lucide="menu" class="w-6 h-6"></i>
                    </button>
                </div>
            </div>
        </div>

        <div id="mobile-menu" class="hidden md:hidden bg-white border-b border-border-color px-4 pt-2 pb-4 space-y-2">
            <a href="#tentang" class="block px-3 py-2 rounded-lg text-base font-medium text-text-main hover:bg-app-bg">Tentang Sistem</a>
            <a href="#latar-belakang" class="block px-3 py-2 rounded-lg text-base font-medium text-text-main hover:bg-app-bg">Profil Pesantren</a>
            <a href="#alur-pelayanan" class="block px-3 py-2 rounded-lg text-base font-medium text-text-main hover:bg-app-bg">Alur Pelayanan</a>
            <a href="login.php" class="block w-full text-center px-4 py-2.5 mt-2 text-base font-semibold text-white bg-primary-green rounded-xl">Portal Wali Santri</a>
        </div>
    </header>

    <!-- HERO SECTION -->
    <section class="relative bg-gradient-to-b from-white to-app-bg py-16 md:py-24 border-b border-border-color">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                <div class="lg:col-span-7 space-y-6">
                    <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-primary-green/10 border border-primary-green/20 text-primary-green text-xs font-semibold">
                        <i data-lucide="shield-check" class="w-4 h-4"></i> Pos Kesehatan Pesantren Terpadu
                    </div>
                    <h1 class="text-3xl sm:text-4xl lg:text-5xl font-bold text-deep-green leading-tight">
                        Layanan Kesehatan & Triase Digital Santri Al-Bahjah Bogor
                    </h1>
                    <p class="text-text-muted text-base sm:text-lg leading-relaxed">
                        Sistem Informasi SPK Poskestren berbasis metode triase kualitatif modern untuk memastikan penanganan medis yang cepat, tepat, dan transparan bagi seluruh santri dan keluarga.
                    </p>
                    <div class="flex flex-wrap gap-4 pt-2">
                        <a href="login.php" class="inline-flex items-center px-6 py-3.5 text-base font-semibold text-white bg-primary-green hover:bg-deep-green rounded-xl transition-all shadow-md gap-2">
                            <i data-lucide="user-check" class="w-5 h-5"></i> Akses Portal Wali Santri
                        </a>
                        <button onclick="toggleChat()" class="inline-flex items-center px-6 py-3.5 text-base font-semibold text-deep-green bg-white border border-border-color hover:bg-gray-50 rounded-xl transition-all shadow-sm gap-2">
                            <i data-lucide="message-square" class="w-5 h-5 text-primary-green"></i> Hubungi Customer Service
                        </button>
                    </div>
                </div>
                <div class="lg:col-span-5 flex justify-center">
                    <div class="bg-white p-6 sm:p-8 rounded-2xl border border-border-color shadow-lg space-y-6 w-full max-w-md">
                        <div class="flex items-center space-x-4 border-b border-border-color pb-4">
                            <div class="w-12 h-12 bg-primary-green/10 text-primary-green rounded-xl flex items-center justify-center">
                                <i data-lucide="activity" class="w-6 h-6"></i>
                            </div>
                            <div>
                                <h2 class="text-sm font-semibold text-text-muted">Status Operasional</h2>
                                <p class="text-lg font-bold text-deep-green">24 Jam (Triase Darurat)</p>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <div class="flex justify-between text-sm">
                                <span class="text-text-muted">Pemeriksaan Rutin</span>
                                <span class="font-semibold text-text-main">08.00 – 16.00 WIB</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-text-muted">Layanan Dokter Spesialis</span>
                                <span class="font-semibold text-text-main">Sesuai Jadwal</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-text-muted">Rawat Inap & Observasi</span>
                                <span class="font-semibold text-primary-green">Aktif 24/7</span>
                            </div>
                        </div>
                        <!-- Statistik Live -->
                        <div class="grid grid-cols-3 gap-2 pt-2 border-t border-border-color text-center text-xs">
                            <div>
                                <div class="text-base font-bold text-deep-green">24 Jam</div>
                                <div class="text-text-muted text-[10px]">Triase Darurat</div>
                            </div>
                            <div>
                                <div class="text-base font-bold text-primary-green">08–16</div>
                                <div class="text-text-muted text-[10px]">Jam Dokter</div>
                            </div>
                            <div>
                                <div class="text-base font-bold text-deep-green">2 Ruang</div>
                                <div class="text-text-muted text-[10px]">Putra & Putri</div>
                            </div>
                        </div>
                        <div class="p-3 bg-app-bg rounded-xl border border-border-color flex items-center gap-3">
                            <span class="w-3 h-3 rounded-full bg-emerald-500 animate-pulse flex-shrink-0"></span>
                            <span class="text-xs font-semibold text-text-main">Petugas Medis Poskestren Siaga Saat Ini</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- TENTANG SISTEM -->
    <section id="tentang" class="py-16 md:py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto space-y-4 mb-16">
                <span class="text-xs font-bold tracking-wider text-primary-green uppercase bg-primary-green/10 px-3 py-1 rounded-full">Inovasi Digital</span>
                <h2 class="text-2xl sm:text-3xl font-bold text-deep-green">Tentang Sistem SPK Poskestren</h2>
                <p class="text-text-muted text-sm sm:text-base">
                    Sistem Penunjang Keputusan (SPK) Poskestren Al-Bahjah Bogor dikembangkan untuk mendukung efisiensi pelayanan kesehatan berbasis ilmiah bagi komunitas pesantren.
                </p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-app-bg p-6 rounded-2xl border border-border-color space-y-4 hover:border-primary-green transition-all">
                    <div class="w-12 h-12 bg-primary-green/10 text-primary-green rounded-xl flex items-center justify-center">
                        <i data-lucide="sliders" class="w-6 h-6"></i>
                    </div>
                    <h3 class="text-lg font-bold text-deep-green">Metode Triase Prioritas SAW</h3>
                    <p class="text-sm text-text-muted leading-relaxed">Pengalokasian urutan penanganan santri dilakukan secara objektif berdasarkan skor parameter klinis (suhu, nyeri, komorbid, durasi, dan vitalitas).</p>
                </div>
                <div class="bg-app-bg p-6 rounded-2xl border border-border-color space-y-4 hover:border-primary-green transition-all">
                    <div class="w-12 h-12 bg-primary-green/10 text-primary-green rounded-xl flex items-center justify-center">
                        <i data-lucide="file-spreadsheet" class="w-6 h-6"></i>
                    </div>
                    <h3 class="text-lg font-bold text-deep-green">Rekam Medis & Farmasi Terpadu</h3>
                    <p class="text-sm text-text-muted leading-relaxed">Data riwayat alergi, diagnosis medis, resep obat, hingga persediaan farmasi terkoneksi langsung guna meminimalkan kesalahan pemberian terapi obat.</p>
                </div>
                <div class="bg-app-bg p-6 rounded-2xl border border-border-color space-y-4 hover:border-primary-green transition-all">
                    <div class="w-12 h-12 bg-primary-green/10 text-primary-green rounded-xl flex items-center justify-center">
                        <i data-lucide="users" class="w-6 h-6"></i>
                    </div>
                    <h3 class="text-lg font-bold text-deep-green">Portal Transparan Wali & Santri</h3>
                    <p class="text-sm text-text-muted leading-relaxed">Wali santri dapat memantau kondisi kesehatan, status perizinan istirahat, serta penanganan medis anak tanpa mengabaikan privasi data medis santri lain.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- LATAR BELAKANG -->
    <section id="latar-belakang" class="py-16 md:py-20 bg-app-bg border-y border-border-color">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                <div class="lg:col-span-6 space-y-6">
                    <span class="text-xs font-bold tracking-wider text-primary-green uppercase bg-white border border-border-color px-3 py-1 rounded-full">Profil Lembaga</span>
                    <h2 class="text-2xl sm:text-3xl font-bold text-deep-green leading-tight">
                        Latar Belakang Pondok Pesantren Al-Bahjah Bogor
                    </h2>
                    <p class="text-text-muted text-sm sm:text-base leading-relaxed">
                        Pondok Pesantren Al-Bahjah Bogor merupakan bagian dari Lembaga Pengembangan Dakwah (LPD) Al-Bahjah yang diasuh oleh <strong class="text-text-main">Buya Yahya</strong>. Pesantren ini berfokus pada pembinaan akhlak, pendidikan Al-Qur'an, dan tafaqquh fiddin bagi para santri generasi penerus.
                    </p>
                    <p class="text-text-muted text-sm sm:text-base leading-relaxed">
                        Seiring bertambahnya jumlah santri, <strong class="text-text-main">Poskestren Al-Bahjah Bogor</strong> hadir sebagai garda terdepan dalam menjaga kesehatan fisik serta memberikan pertolongan pertama bagi seluruh santri dan pengabdi.
                    </p>
                    <div class="grid grid-cols-2 gap-4 pt-2">
                        <div class="p-4 bg-white rounded-xl border border-border-color">
                            <span class="block text-2xl font-bold text-primary-green">100%</span>
                            <span class="text-xs text-text-muted font-medium">Dedikasi Kesehatan Santri</span>
                        </div>
                        <div class="p-4 bg-white rounded-xl border border-border-color">
                            <span class="block text-2xl font-bold text-primary-green">24/7</span>
                            <span class="text-xs text-text-muted font-medium">Pendampingan Tenaga Kesehatan</span>
                        </div>
                    </div>
                </div>
                <div class="lg:col-span-6">
                    <div class="bg-white p-8 rounded-2xl border border-border-color shadow-sm space-y-6">
                        <h3 class="text-xl font-bold text-deep-green border-b border-border-color pb-3">Misi Pelayanan Poskestren Al-Bahjah</h3>
                        <ul class="space-y-4">
                            <?php foreach ([
                                "Memberikan pertolongan pertama kesehatan yang ramah, cepat, dan sesuai kaidah medis.",
                                "Mewujudkan lingkungan santri yang bersih, higienis, dan sadar akan pentingnya kesehatan.",
                                "Menyediakan akses informasi riwayat kesehatan yang transparan dan dapat dipertanggungjawabkan kepada wali santri."
                            ] as $misi): ?>
                            <li class="flex items-start gap-3">
                                <div class="p-1 bg-primary-green/10 text-primary-green rounded-md mt-0.5 flex-shrink-0">
                                    <i data-lucide="check" class="w-4 h-4"></i>
                                </div>
                                <span class="text-sm text-text-main"><?= $misi; ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ALUR PELAYANAN -->
    <section id="alur-pelayanan" class="py-16 md:py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto space-y-4 mb-16">
                <span class="text-xs font-bold tracking-wider text-primary-green uppercase bg-primary-green/10 px-3 py-1 rounded-full">Prosedur Medis</span>
                <h2 class="text-2xl sm:text-3xl font-bold text-deep-green">Alur Pelayanan Kesehatan Poskestren</h2>
                <p class="text-text-muted text-sm sm:text-base">Tahapan sistematis dari pendaftaran, triase penentuan prioritas, hingga penyerahan obat dan izin tindakan.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <?php
                $steps = [
                    ['num'=>'01','color'=>'bg-deep-green','title'=>'Kedatangan & Registrasi','desc'=>'Santri datang ke Poskestren dan didata oleh petugas menggunakan identitas NIS/Nama Lengkap.'],
                    ['num'=>'02','color'=>'bg-primary-green','title'=>'Pemeriksaan & Triase SAW','desc'=>'Petugas mengukur tanda vital. Sistem menghitung skor SAW untuk menentukan antrean (Darurat/Sedang/Ringan).'],
                    ['num'=>'03','color'=>'bg-deep-green','title'=>'Tindakan Medis & Rekam','desc'=>'Dokter/Nakes memberikan diagnosis, menentukan tindakan (Rawat Jalan/Rawat Inap/Rujukan), serta memberikan resep obat.'],
                    ['num'=>'04','color'=>'bg-primary-green','title'=>'Penyerahan Obat / Izin','desc'=>'Farmasi menyerahkan obat sesuai resep, dan sistem menerbitkan status izin istirahat/pemulihan santri.'],
                ];
                foreach ($steps as $step):
                ?>
                <div class="bg-app-bg p-6 rounded-2xl border border-border-color space-y-4">
                    <div class="w-10 h-10 <?= $step['color']; ?> text-white rounded-xl flex items-center justify-center font-bold text-sm"><?= $step['num']; ?></div>
                    <h3 class="text-base font-bold text-deep-green"><?= $step['title']; ?></h3>
                    <p class="text-xs text-text-muted leading-relaxed"><?= $step['desc']; ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="bg-deep-green text-white mt-auto border-t border-primary-green/20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div class="space-y-4 md:col-span-2">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-primary-green text-white rounded-xl flex items-center justify-center font-bold">
                            <i data-lucide="cross"></i>
                        </div>
                        <span class="text-lg font-bold">Poskestren Al-Bahjah Bogor</span>
                    </div>
                    <p class="text-sm text-gray-300 max-w-sm leading-relaxed">Sistem Penunjang Keputusan Triase Medis & Manajemen Kesehatan Santri Terpadu.</p>
                </div>
                <div>
                    <h4 class="text-sm font-semibold text-warm-yellow uppercase tracking-wider mb-4">Navigasi Cepat</h4>
                    <ul class="space-y-2 text-sm text-gray-300">
                        <li><a href="#tentang" class="hover:text-white transition-colors">Tentang Sistem</a></li>
                        <li><a href="#latar-belakang" class="hover:text-white transition-colors">Profil Pesantren</a></li>
                        <li><a href="#alur-pelayanan" class="hover:text-white transition-colors">Alur Pelayanan</a></li>
                        <li><a href="login.php" class="hover:text-white transition-colors">Portal Wali Santri</a></li>
                        <li><a href="login_petugas.php" class="hover:text-white transition-colors">Login Petugas Medis</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-sm font-semibold text-warm-yellow uppercase tracking-wider mb-4">Kontak Darurat</h4>
                    <p class="text-sm text-gray-300 leading-relaxed mb-3">Kampus Pondok Pesantren Al-Bahjah Bogor, Jawa Barat.</p>
                    <a href="tel:081122334455" class="text-sm text-gray-300 hover:text-white block mb-2">📞 0811-2233-4455</a>
                    <button onclick="toggleChat()" class="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold bg-primary-green hover:bg-emerald-600 rounded-lg transition-colors">
                        <i data-lucide="phone-call" class="w-4 h-4"></i> Layanan CS Poskestren
                    </button>
                </div>
            </div>
            <div class="border-t border-gray-700/60 mt-8 pt-6 text-center text-xs text-gray-400">
                &copy; <?= date('Y'); ?> Poskestren Al-Bahjah Bogor. All rights reserved.
            </div>
        </div>
    </footer>

    <!-- CS CHAT WIDGET -->
    <div class="fixed bottom-6 right-6 z-50">
        <button id="chat-toggle-btn" onclick="toggleChat()" class="w-14 h-14 bg-primary-green hover:bg-deep-green text-white rounded-full flex items-center justify-center shadow-2xl transition-all hover:scale-105 relative">
            <i data-lucide="message-circle" class="w-7 h-7"></i>
            <span class="absolute -top-1 -right-1 w-4 h-4 bg-warm-yellow rounded-full border-2 border-white"></span>
        </button>

        <div id="chat-box" class="hidden absolute bottom-16 right-0 w-80 sm:w-96 bg-white rounded-2xl shadow-2xl border border-border-color overflow-hidden chat-drawer">
            <div class="bg-deep-green p-4 text-white flex justify-between items-center">
                <div class="flex items-center space-x-3">
                    <div class="w-9 h-9 bg-primary-green rounded-full flex items-center justify-center">
                        <i data-lucide="headset" class="w-5 h-5"></i>
                    </div>
                    <div>
                        <h4 class="text-sm font-bold">CS Poskestren Al-Bahjah</h4>
                        <p class="text-xs text-emerald-300 flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-emerald-400"></span> Online / Siaga</p>
                    </div>
                </div>
                <button onclick="toggleChat()" class="text-gray-300 hover:text-white"><i data-lucide="x" class="w-5 h-5"></i></button>
            </div>

            <div id="chat-messages" class="p-4 h-64 overflow-y-auto space-y-3 bg-app-bg text-xs">
                <div class="flex items-start space-x-2">
                    <div class="w-7 h-7 bg-primary-green text-white rounded-full flex items-center justify-center flex-shrink-0 text-xs font-bold">CS</div>
                    <div class="bg-white p-3 rounded-2xl rounded-tl-none border border-border-color text-text-main max-w-[80%]">
                        <p class="font-semibold text-primary-green mb-1">Petugas Poskestren</p>
                        Assalamu'alaikum Wr. Wb. Ada yang bisa kami bantu terkait informasi pelayanan kesehatan atau kondisi santri di Poskestren Al-Bahjah Bogor?
                    </div>
                </div>
            </div>

            <div class="px-3 py-2 bg-white border-t border-border-color flex gap-2 overflow-x-auto text-[11px]">
                <button onclick="sendQuickMsg('Bagaimana alur izin rawat inap santri?')" class="px-2.5 py-1 bg-app-bg border border-border-color hover:bg-primary-green/10 text-text-main rounded-lg whitespace-nowrap">Izin Rawat Inap</button>
                <button onclick="sendQuickMsg('Berapa nomor telepon darurat poskestren?')" class="px-2.5 py-1 bg-app-bg border border-border-color hover:bg-primary-green/10 text-text-main rounded-lg whitespace-nowrap">Nomor Darurat</button>
            </div>

            <form id="chat-form" class="p-3 bg-white border-t border-border-color flex gap-2">
                <input type="text" id="chat-input" placeholder="Tulis pesan Anda..." class="flex-1 text-xs border border-border-color rounded-xl px-3 py-2 focus:outline-none focus:border-primary-green bg-app-bg" required>
                <button type="submit" class="p-2 bg-primary-green text-white rounded-xl hover:bg-deep-green transition-colors">
                    <i data-lucide="send" class="w-4 h-4"></i>
                </button>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();

        document.getElementById('mobile-menu-btn').addEventListener('click', () => {
            document.getElementById('mobile-menu').classList.toggle('hidden');
        });

        // ── Session key unik per browser tab ─────────────────────────
        let SESSION_KEY = sessionStorage.getItem('cs_session_key');
        if (!SESSION_KEY) {
            SESSION_KEY = 'guest_' + Date.now() + '_' + Math.random().toString(36).substr(2,8);
            sessionStorage.setItem('cs_session_key', SESSION_KEY);
        }
        let lastMsgId = 0;
        let pollTimer = null;

        function toggleChat() {
            const box = document.getElementById('chat-box');
            box.classList.toggle('hidden');
            if (!box.classList.contains('hidden')) startPolling();
        }

        // ── Kirim pesan ke server ─────────────────────────────────────
        function sendQuickMsg(text) {
            document.getElementById('chat-input').value = text;
            document.getElementById('chat-form').dispatchEvent(new Event('submit', { cancelable: true }));
        }

        document.getElementById('chat-form').addEventListener('submit', function(e) {
            e.preventDefault();
            const input     = document.getElementById('chat-input');
            const container = document.getElementById('chat-messages');
            const text      = input.value.trim();
            if (!text) return;

            // Tampilkan pesan pengirim langsung
            appendMsg(container, text, false);
            input.value = '';

            // Kirim ke server
            fetch('api_chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'send', pesan: text, session_key: SESSION_KEY, nama: 'Pengunjung' })
            })
            .then(r => r.json())
            .then(data => {
                if (data.ok) {
                    lastMsgId = Math.max(lastMsgId, data.id);
                    // Pesan konfirmasi otomatis
                    setTimeout(() => {
                        appendCSMsg(container, 'Terima kasih! Pesan Anda telah diterima dan akan segera dibalas oleh petugas siaga Poskestren Al-Bahjah.');
                    }, 800);
                }
            });
        });

        // ── Polling balasan dari CS ───────────────────────────────────
        function startPolling() {
            if (pollTimer) return;
            pollTimer = setInterval(pollReplies, 4000);
        }

        function pollReplies() {
            fetch(`api_chat.php?action=poll&session_key=${SESSION_KEY}&last_id=${lastMsgId}`)
            .then(r => r.json())
            .then(data => {
                if (data.ok && data.messages.length > 0) {
                    const container = document.getElementById('chat-messages');
                    data.messages.forEach(m => {
                        appendCSMsg(container, m.pesan, m.time);
                        lastMsgId = Math.max(lastMsgId, m.id);
                    });
                }
            });
        }

        // ── Helper: tambah bubble pesan ───────────────────────────────
        function appendMsg(container, text, isCS, time) {
            container.insertAdjacentHTML('beforeend', `
                <div class="flex items-start justify-end space-x-2">
                    <div class="bg-primary-green text-white p-3 rounded-2xl rounded-tr-none text-xs max-w-[80%]">${escapeHtml(text)}</div>
                </div>`);
            container.scrollTop = container.scrollHeight;
        }

        function appendCSMsg(container, text, time) {
            const t = time || new Date().toLocaleTimeString('id-ID',{hour:'2-digit',minute:'2-digit'});
            container.insertAdjacentHTML('beforeend', `
                <div class="flex items-start space-x-2">
                    <div class="w-7 h-7 bg-primary-green text-white rounded-full flex items-center justify-center flex-shrink-0 text-xs font-bold">CS</div>
                    <div class="bg-white p-3 rounded-2xl rounded-tl-none border border-border-color text-xs max-w-[80%]">
                        <p class="font-semibold text-primary-green mb-1">Petugas Poskestren</p>
                        ${escapeHtml(text)}
                        <span class="block text-[10px] text-gray-400 mt-1">${t}</span>
                    </div>
                </div>`);
            container.scrollTop = container.scrollHeight;
        }

        function escapeHtml(text) {
            const d = document.createElement('div');
            d.textContent = text;
            return d.innerHTML;
        }
    </script>
</body>
</html>
