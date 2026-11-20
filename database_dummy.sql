-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: poskestren_albahjah
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `aktivitas` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cs_messages`
--

DROP TABLE IF EXISTS `cs_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cs_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_key` varchar(64) NOT NULL,
  `nama_pengirim` varchar(100) DEFAULT 'Pengunjung',
  `pesan` text NOT NULL,
  `balasan` text DEFAULT NULL,
  `dari_cs` tinyint(1) DEFAULT 0,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_session` (`session_key`),
  KEY `idx_read` (`is_read`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cs_messages`
--

LOCK TABLES `cs_messages` WRITE;
/*!40000 ALTER TABLE `cs_messages` DISABLE KEYS */;
INSERT INTO `cs_messages` VALUES (1,'guest_1793728642333_e49jtzm9','Pengunjung','Bagaimana alur izin rawat inap santri?',NULL,0,1,'2026-11-04 00:57:27'),(2,'guest_1793728642333_e49jtzm9','dr. H. Ahmad Fauzan, Sp.A','untuk rawat inap',NULL,1,1,'2026-11-04 01:06:25'),(3,'wali_14_ahmadsubene','ahmad subene (Wali)','Bagaimana kondisi ananda saya saat ini?',NULL,0,1,'2026-11-04 01:16:01'),(4,'wali_14_ahmadsubene','ahmad subene','alhamdulillah ananda sehat abi',NULL,1,1,'2026-11-04 01:16:27'),(5,'wali_14_ahmadsubene','ahmad subene (Wali)','kok status nya darurat',NULL,0,1,'2026-11-05 19:24:50'),(6,'guest_1794198047349_52abbjuq','Pengunjung','Berapa nomor telepon darurat poskestren?',NULL,0,0,'2026-11-09 11:21:32'),(7,'wali_14','ahmad subene','Bagaimana kondisi ananda saya saat ini?',NULL,0,0,'2026-11-10 07:47:52');
/*!40000 ALTER TABLE `cs_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `kunjungan_sakit`
--

DROP TABLE IF EXISTS `kunjungan_sakit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `kunjungan_sakit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `no_antrean` varchar(20) NOT NULL,
  `santri_id` int(11) NOT NULL,
  `tanggal_berobat` timestamp NOT NULL DEFAULT current_timestamp(),
  `keluhan_utama` text NOT NULL,
  `status_antrean` enum('menunggu_triase','pemeriksaan','rawat_jalan','rawat_inap','rujuk_faskes','selesai') DEFAULT 'menunggu_triase',
  `skor_saw_akhir` decimal(6,4) DEFAULT 0.0000,
  `tingkat_prioritas` enum('P1_Gawat_Darurat','P2_Mendesak','P3_Biasa') DEFAULT 'P3_Biasa',
  `is_emergency_override` tinyint(1) NOT NULL DEFAULT 0,
  `tanggal_selesai` datetime DEFAULT NULL,
  `catatan_dokter` text DEFAULT NULL,
  `dokter_pemeriksa` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `no_antrean` (`no_antrean`),
  KEY `santri_id` (`santri_id`),
  CONSTRAINT `kunjungan_sakit_ibfk_1` FOREIGN KEY (`santri_id`) REFERENCES `santri` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kunjungan_sakit`
--

LOCK TABLES `kunjungan_sakit` WRITE;
/*!40000 ALTER TABLE `kunjungan_sakit` DISABLE KEYS */;
INSERT INTO `kunjungan_sakit` VALUES (12,'ANT-001',6,'2026-11-05 16:11:12','demam','rawat_jalan',0.6600,'P2_Mendesak',0,NULL,NULL,NULL),(13,'ANT-002',1,'2026-11-05 16:12:07','jatuh di ranjang','selesai',0.3900,'P3_Biasa',0,'2026-11-17 20:17:29','Kondisi fisik telah pulih dan stabil, suhu normal, keluhan telah mereda. Santri dinyatakan sembuh dan diperbolehkan kembali beraktivitas di asrama serta mengikuti KBM.','dr. Siti Nurhaliza, M.Biomed');
/*!40000 ALTER TABLE `kunjungan_sakit` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifikasi_wali`
--

DROP TABLE IF EXISTS `notifikasi_wali`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifikasi_wali` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `santri_id` int(11) NOT NULL,
  `kunjungan_id` int(11) NOT NULL,
  `judul` varchar(100) NOT NULL,
  `pesan` text NOT NULL,
  `status_baca` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `santri_id` (`santri_id`),
  KEY `kunjungan_id` (`kunjungan_id`),
  CONSTRAINT `notifikasi_wali_ibfk_1` FOREIGN KEY (`santri_id`) REFERENCES `santri` (`id`),
  CONSTRAINT `notifikasi_wali_ibfk_2` FOREIGN KEY (`kunjungan_id`) REFERENCES `kunjungan_sakit` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifikasi_wali`
--

LOCK TABLES `notifikasi_wali` WRITE;
/*!40000 ALTER TABLE `notifikasi_wali` DISABLE KEYS */;
INSERT INTO `notifikasi_wali` VALUES (1,1,1,'Pemberitahuan Kesehatan Ananda M. Farhan','Ananda saat ini sedang dalam penanganan di Poskestren Al-Bahjah karena febris dan batuk. Mohon doanya.',0,'2026-10-27 02:26:27');
/*!40000 ALTER TABLE `notifikasi_wali` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `obat`
--

DROP TABLE IF EXISTS `obat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `obat` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kategori_id` int(11) NOT NULL,
  `kode_obat` varchar(30) NOT NULL,
  `nama_obat` varchar(100) NOT NULL,
  `satuan` varchar(20) NOT NULL,
  `stok_minimal` int(11) DEFAULT 10,
  `stok_sekarang` int(11) DEFAULT 0,
  `exp_date` date NOT NULL,
  `keterangan` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode_obat` (`kode_obat`),
  KEY `kategori_id` (`kategori_id`),
  CONSTRAINT `obat_ibfk_1` FOREIGN KEY (`kategori_id`) REFERENCES `obat_kategori` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `obat`
--

LOCK TABLES `obat` WRITE;
/*!40000 ALTER TABLE `obat` DISABLE KEYS */;
INSERT INTO `obat` VALUES (1,1,'OBT-001','Paracetamol 500mg Tablet','Tablet',20,140,'2027-08-30','Penurun demam dan pereda nyeri'),(2,1,'OBT-002','Ibuprofen 400mg Kaplet','Kaplet',15,65,'2027-04-15','Anti-inflamasi dan pereda demam'),(3,2,'OBT-003','Amoxicillin 500mg Kapsul','Kapsul',25,30,'2026-12-31','Antibiotik spektrum luas (resep dokter)'),(4,3,'OBT-004','Cetirizine 10mg Tablet','Tablet',10,5,'2027-01-20','Antialergi dan gatal (Stok Menipis)'),(5,4,'OBT-005','Antasida Doen Tablet Kunyah','Tablet',30,120,'2027-11-10','Pereda nyeri lambung dan asam lambung'),(6,4,'OBT-006','Oralit Serbuk Sachet','Sachet',40,200,'2028-02-14','Rehidrasi santri diare / muntah'),(7,5,'OBT-007','Vitamin C 500mg IPI','Tablet',20,5,'2026-11-05','Mendekati expired dan stok kritis!'),(8,6,'OBT-008','Povidone Iodine 60ml','Botol',10,25,'2027-09-01','Antiseptik luka luar'),(9,6,'OBT-009','Salep Hidrokortison 1%','Tube',10,18,'2027-06-25','Salep alergi gatal dan radang kulit'),(10,1,'OBT-010','Paracetamol Sirup 120mg/5ml','Botol',10,14,'2027-03-12','Sirup penurun panas santri junior');
/*!40000 ALTER TABLE `obat` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `obat_kategori`
--

DROP TABLE IF EXISTS `obat_kategori`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `obat_kategori` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_kategori` varchar(50) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `obat_kategori`
--

LOCK TABLES `obat_kategori` WRITE;
/*!40000 ALTER TABLE `obat_kategori` DISABLE KEYS */;
INSERT INTO `obat_kategori` VALUES (1,'Analgesik & Antipiretik'),(2,'Antibiotik & Antijamur'),(3,'Antihistamin & Alergi'),(4,'Saluran Pencernaan'),(5,'Vitamin & Suplemen'),(6,'Obat Luar & Antiseptik');
/*!40000 ALTER TABLE `obat_kategori` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `obat_mutasi_stok`
--

DROP TABLE IF EXISTS `obat_mutasi_stok`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `obat_mutasi_stok` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `obat_id` int(11) NOT NULL,
  `jenis_mutasi` enum('masuk','keluar','penyesuaian','kadaluarsa') NOT NULL,
  `jumlah` int(11) NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `obat_id` (`obat_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `obat_mutasi_stok_ibfk_1` FOREIGN KEY (`obat_id`) REFERENCES `obat` (`id`),
  CONSTRAINT `obat_mutasi_stok_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `obat_mutasi_stok`
--

LOCK TABLES `obat_mutasi_stok` WRITE;
/*!40000 ALTER TABLE `obat_mutasi_stok` DISABLE KEYS */;
INSERT INTO `obat_mutasi_stok` VALUES (1,1,'masuk',100,'Penerimaan logistik dinas kesehatan',1,'2026-10-27 02:26:27'),(2,5,'masuk',50,'Pengadaan persediaan rutin poskestren',1,'2026-10-27 02:26:27'),(3,1,'keluar',10,'Penyerahan resep #1 ke pasien',1,'2026-10-27 03:18:33'),(4,3,'keluar',15,'Penyerahan resep #1 ke pasien',1,'2026-10-27 03:18:33'),(5,4,'keluar',3,'Penyerahan resep #2 oleh Apoteker',4,'2026-11-06 06:30:56');
/*!40000 ALTER TABLE `obat_mutasi_stok` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_token` (`token`),
  KEY `idx_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pemeriksaan_klinis`
--

DROP TABLE IF EXISTS `pemeriksaan_klinis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pemeriksaan_klinis` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kunjungan_id` int(11) NOT NULL,
  `petugas_id` int(11) NOT NULL,
  `tekanan_darah` varchar(15) DEFAULT NULL,
  `suhu_tubuh` decimal(4,1) DEFAULT NULL,
  `nadi` int(11) DEFAULT NULL,
  `laju_respirasi` int(11) DEFAULT NULL,
  `saturasi_oksigen` int(11) DEFAULT NULL,
  `tingkat_kesadaran` varchar(50) DEFAULT NULL,
  `diagnosa_dokter` text DEFAULT NULL,
  `tindakan_pertama` text DEFAULT NULL,
  `rekomendasi_rawat` enum('Rawat Jalan','Rawat Inap Poskestren','Rujuk RS') NOT NULL,
  PRIMARY KEY (`id`),
  KEY `kunjungan_id` (`kunjungan_id`),
  KEY `petugas_id` (`petugas_id`),
  CONSTRAINT `pemeriksaan_klinis_ibfk_1` FOREIGN KEY (`kunjungan_id`) REFERENCES `kunjungan_sakit` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pemeriksaan_klinis_ibfk_2` FOREIGN KEY (`petugas_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pemeriksaan_klinis`
--

LOCK TABLES `pemeriksaan_klinis` WRITE;
/*!40000 ALTER TABLE `pemeriksaan_klinis` DISABLE KEYS */;
INSERT INTO `pemeriksaan_klinis` VALUES (1,1,2,'110/70',39.4,104,28,94,'Apatis / Lemas','Bronkopneumonia Ringan & Febris Tinggi','Pemberian oksigen nasal 2 lpm, kompres hangat, antipiretik oral','Rawat Inap Poskestren'),(2,12,2,'110/70',38.5,88,20,98,'Kompos Mentis (Sadar Penuh)','-','-','Rawat Jalan');
/*!40000 ALTER TABLE `pemeriksaan_klinis` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rawat_inap`
--

DROP TABLE IF EXISTS `rawat_inap`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rawat_inap` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kunjungan_id` int(11) NOT NULL,
  `nomor_bed` varchar(20) NOT NULL,
  `tanggal_masuk` timestamp NOT NULL DEFAULT current_timestamp(),
  `tanggal_keluar` timestamp NULL DEFAULT NULL,
  `status_rawat` enum('Dirawat','Sembuh/KBM','Izin Pulang Ke Rumah','Rujuk Lanjutan') DEFAULT 'Dirawat',
  `catatan_perkembangan` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `kunjungan_id` (`kunjungan_id`),
  CONSTRAINT `rawat_inap_ibfk_1` FOREIGN KEY (`kunjungan_id`) REFERENCES `kunjungan_sakit` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rawat_inap`
--

LOCK TABLES `rawat_inap` WRITE;
/*!40000 ALTER TABLE `rawat_inap` DISABLE KEYS */;
INSERT INTO `rawat_inap` VALUES (1,1,'Bed 02 (Ruang Al-Kin','2026-10-27 02:05:48',NULL,'Dirawat','Observasi suhu tiap 2 jam, pastikan asupan cairan cukup');
/*!40000 ALTER TABLE `rawat_inap` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `resep_obat`
--

DROP TABLE IF EXISTS `resep_obat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `resep_obat` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kunjungan_id` int(11) NOT NULL,
  `petugas_farmasi_id` int(11) DEFAULT NULL,
  `status_resep` enum('menunggu_proses','diproses','selesai','dibatalkan') DEFAULT 'menunggu_proses',
  `catatan_resep` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `kunjungan_id` (`kunjungan_id`),
  KEY `petugas_farmasi_id` (`petugas_farmasi_id`),
  CONSTRAINT `resep_obat_ibfk_1` FOREIGN KEY (`kunjungan_id`) REFERENCES `kunjungan_sakit` (`id`) ON DELETE CASCADE,
  CONSTRAINT `resep_obat_ibfk_2` FOREIGN KEY (`petugas_farmasi_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `resep_obat`
--

LOCK TABLES `resep_obat` WRITE;
/*!40000 ALTER TABLE `resep_obat` DISABLE KEYS */;
INSERT INTO `resep_obat` VALUES (1,1,1,'selesai','Diminum setelah makan, perbanyak air putih','2026-10-27 02:05:48'),(2,12,4,'selesai','perbanyak jelan tapi pelan-pelan','2026-11-06 06:19:23');
/*!40000 ALTER TABLE `resep_obat` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `resep_obat_detail`
--

DROP TABLE IF EXISTS `resep_obat_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `resep_obat_detail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `resep_id` int(11) NOT NULL,
  `obat_id` int(11) NOT NULL,
  `jumlah` int(11) NOT NULL,
  `dosis_instruksi` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `resep_id` (`resep_id`),
  KEY `obat_id` (`obat_id`),
  CONSTRAINT `resep_obat_detail_ibfk_1` FOREIGN KEY (`resep_id`) REFERENCES `resep_obat` (`id`) ON DELETE CASCADE,
  CONSTRAINT `resep_obat_detail_ibfk_2` FOREIGN KEY (`obat_id`) REFERENCES `obat` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `resep_obat_detail`
--

LOCK TABLES `resep_obat_detail` WRITE;
/*!40000 ALTER TABLE `resep_obat_detail` DISABLE KEYS */;
INSERT INTO `resep_obat_detail` VALUES (1,1,1,10,'3 x 1 tablet sesudah makan (bila demam)'),(2,1,3,15,'3 x 1 kapsul dihabiskan'),(3,2,4,3,'setelah makan 3x1 hari');
/*!40000 ALTER TABLE `resep_obat_detail` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `santri`
--

DROP TABLE IF EXISTS `santri`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `santri` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nis` varchar(20) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `jenis_kelamin` enum('L','P') NOT NULL,
  `kamar_asrama` varchar(50) NOT NULL,
  `kompleks_asrama` enum('Putra','Putri') NOT NULL,
  `tanggal_lahir` date NOT NULL,
  `nama_wali` varchar(100) NOT NULL,
  `no_hp_wali` varchar(20) NOT NULL,
  `user_id_wali` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nis` (`nis`),
  KEY `user_id_wali` (`user_id_wali`),
  CONSTRAINT `santri_ibfk_1` FOREIGN KEY (`user_id_wali`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `santri`
--

LOCK TABLES `santri` WRITE;
/*!40000 ALTER TABLE `santri` DISABLE KEYS */;
INSERT INTO `santri` VALUES (1,'2024-AB-0192','M. Farhan','L','Kamar 204 (Al-Fatih)','Putra','2008-05-12','ahmad subene','085770909120',14),(4,'2024-AB-0305','Muhammad Zaky Pratama','L','Kamar 102 (Khalid)','Putra','2008-03-15','siti','0895798678',17),(6,'2024-AB-0244','Khadijah Azzahra','P','Kamar 204 (Aisyah)','Putri','2008-12-10','Hj. Maryam','085299887766',NULL);
/*!40000 ALTER TABLE `santri` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `saw_kriteria`
--

DROP TABLE IF EXISTS `saw_kriteria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `saw_kriteria` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kode_kriteria` varchar(10) NOT NULL,
  `nama_kriteria` varchar(100) NOT NULL,
  `bobot_persen` decimal(5,2) NOT NULL,
  `tipe_kriteria` enum('benefit','cost') NOT NULL,
  `keterangan` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode_kriteria` (`kode_kriteria`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `saw_kriteria`
--

LOCK TABLES `saw_kriteria` WRITE;
/*!40000 ALTER TABLE `saw_kriteria` DISABLE KEYS */;
INSERT INTO `saw_kriteria` VALUES (1,'C1','Suhu Tubuh',30.00,'benefit','Pengukuran suhu aksila (°C)'),(2,'C2','Skala Nyeri / Keparahan',25.00,'benefit','Skala NRS 0–10'),(3,'C3','Riwayat Komorbid',20.00,'benefit','Riwayat asma, alergi berat, kejang'),(4,'C4','Durasi Sakit',15.00,'benefit','Lama waktu keluhan berlangsung'),(5,'C5','Tanda Vital & Kesadaran',10.00,'benefit','Status kesadaran AVPU/GCS');
/*!40000 ALTER TABLE `saw_kriteria` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `saw_nilai_pemeriksaan`
--

DROP TABLE IF EXISTS `saw_nilai_pemeriksaan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `saw_nilai_pemeriksaan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kunjungan_id` int(11) NOT NULL,
  `kriteria_id` int(11) NOT NULL,
  `sub_kriteria_id` int(11) NOT NULL,
  `nilai_raw` decimal(5,2) NOT NULL,
  `nilai_normalisasi` decimal(6,4) NOT NULL,
  `nilai_terbobot` decimal(6,4) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `kunjungan_id` (`kunjungan_id`),
  KEY `kriteria_id` (`kriteria_id`),
  KEY `sub_kriteria_id` (`sub_kriteria_id`),
  CONSTRAINT `saw_nilai_pemeriksaan_ibfk_1` FOREIGN KEY (`kunjungan_id`) REFERENCES `kunjungan_sakit` (`id`) ON DELETE CASCADE,
  CONSTRAINT `saw_nilai_pemeriksaan_ibfk_2` FOREIGN KEY (`kriteria_id`) REFERENCES `saw_kriteria` (`id`),
  CONSTRAINT `saw_nilai_pemeriksaan_ibfk_3` FOREIGN KEY (`sub_kriteria_id`) REFERENCES `saw_sub_kriteria` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=66 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `saw_nilai_pemeriksaan`
--

LOCK TABLES `saw_nilai_pemeriksaan` WRITE;
/*!40000 ALTER TABLE `saw_nilai_pemeriksaan` DISABLE KEYS */;
INSERT INTO `saw_nilai_pemeriksaan` VALUES (1,1,1,1,5.00,1.0000,0.3000),(2,1,2,6,5.00,1.0000,0.2500),(3,1,3,11,5.00,1.0000,0.2000),(4,1,4,17,4.00,0.8000,0.1200),(5,1,5,1,4.00,1.0000,0.1000),(16,4,1,3,3.00,0.6000,0.1800),(17,4,2,7,4.00,0.8000,0.2000),(18,4,3,12,4.00,0.8000,0.1600),(19,4,4,16,5.00,1.0000,0.1500),(20,4,5,23,3.00,0.7500,0.0750),(26,6,1,4,2.00,0.4000,0.1200),(27,6,2,8,3.00,0.6000,0.1500),(28,6,3,15,1.00,0.2000,0.0400),(29,6,4,20,1.00,0.2000,0.0300),(30,6,5,25,1.00,0.2000,0.0200),(31,7,1,1,5.00,1.0000,0.3000),(32,7,2,6,5.00,1.0000,0.2500),(33,7,3,11,5.00,1.0000,0.2000),(34,7,4,20,1.00,0.2000,0.0300),(35,7,5,23,3.00,0.6000,0.0600),(36,8,1,4,2.00,0.4000,0.1200),(37,8,2,9,2.00,0.4000,0.1000),(38,8,3,15,1.00,0.2000,0.0400),(39,8,4,20,1.00,0.2000,0.0300),(40,8,5,24,2.00,0.4000,0.0400),(41,9,1,4,2.00,0.4000,0.1200),(42,9,2,8,3.00,0.6000,0.1500),(43,9,3,15,1.00,0.2000,0.0400),(44,9,4,20,1.00,0.2000,0.0300),(45,9,5,25,1.00,0.2000,0.0200),(46,10,1,4,2.00,0.4000,0.1200),(47,10,2,8,3.00,0.6000,0.1500),(48,10,3,15,1.00,0.2000,0.0400),(49,10,4,20,1.00,0.2000,0.0300),(50,10,5,25,1.00,0.2000,0.0200),(51,11,1,1,5.00,1.0000,0.3000),(52,11,2,8,3.00,0.6000,0.1500),(53,11,3,11,5.00,1.0000,0.2000),(54,11,4,20,1.00,0.2000,0.0300),(55,11,5,22,5.00,1.0000,0.1000),(56,12,1,1,5.00,1.0000,0.3000),(57,12,2,8,3.00,0.6000,0.1500),(58,12,3,13,3.00,0.6000,0.1200),(59,12,4,20,1.00,0.2000,0.0300),(60,12,5,23,3.00,0.6000,0.0600),(61,13,1,4,2.00,0.4000,0.1200),(62,13,2,9,2.00,0.4000,0.1000),(63,13,3,15,1.00,0.2000,0.0400),(64,13,4,20,1.00,0.2000,0.0300),(65,13,5,21,5.00,1.0000,0.1000);
/*!40000 ALTER TABLE `saw_nilai_pemeriksaan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `saw_sub_kriteria`
--

DROP TABLE IF EXISTS `saw_sub_kriteria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `saw_sub_kriteria` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kriteria_id` int(11) NOT NULL,
  `deskripsi` varchar(100) NOT NULL,
  `nilai_crisp` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `kriteria_id` (`kriteria_id`),
  CONSTRAINT `saw_sub_kriteria_ibfk_1` FOREIGN KEY (`kriteria_id`) REFERENCES `saw_kriteria` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `saw_sub_kriteria`
--

LOCK TABLES `saw_sub_kriteria` WRITE;
/*!40000 ALTER TABLE `saw_sub_kriteria` DISABLE KEYS */;
INSERT INTO `saw_sub_kriteria` VALUES (1,1,'Demam Tinggi (>= 39.0 °C)',5),(2,1,'Demam Sedang (38.1 - 38.9 °C)',4),(3,1,'Subfebris (37.5 - 38.0 °C)',3),(4,1,'Normal Rendah (36.5 - 37.4 °C)',2),(5,1,'Hipotermia (< 36.5 °C)',3),(6,2,'Nyeri Sangat Berat / Tak Tertahankan (Skala 9-10)',5),(7,2,'Nyeri Berat (Skala 7-8)',4),(8,2,'Nyeri Sedang (Skala 4-6)',3),(9,2,'Nyeri Ringan (Skala 1-3)',2),(10,2,'Tidak Ada Nyeri (Skala 0)',1),(11,3,'Komorbid Berat Aktif (Asma Akut / Jantung / Kejang)',5),(12,3,'Komorbid Sedang (Gastritis Akut / Alergi Berat)',4),(13,3,'Komorbid Ringan Terkontrol (Alergi Ringan)',3),(14,3,'Ada Riwayat Penyakit Menular',3),(15,3,'Tidak Ada Riwayat Komorbid',1),(16,4,'Mendadak Akut (< 3 Jam dengan perburukan)',5),(17,4,'Akut (3 - 12 Jam)',4),(18,4,'Subakut (1 - 3 Hari)',3),(19,4,'Kronis (> 3 Hari tanpa perburukan)',2),(20,4,'Keluhan Berkala / Kontrol Rutin',1),(21,5,'GCS < 13 / Penurunan Kesadaran (Unresponsive/Pain)',5),(22,5,'Sesak Napas Berat (RR > 30x/mnt / SpO2 < 92%)',5),(23,5,'Gelisah / Tampak Sangat Lemah (Verbal response)',3),(24,5,'Tanda Vital Sedikit Berubah (SpO2 93-95%)',2),(25,5,'Kompos Mentis Penuh / TTV Normal',1);
/*!40000 ALTER TABLE `saw_sub_kriteria` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `shift_jaga`
--

DROP TABLE IF EXISTS `shift_jaga`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `shift_jaga` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `posisi` varchar(50) NOT NULL,
  `nama` varchar(150) NOT NULL,
  `catatan` varchar(255) DEFAULT '',
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `posisi` (`posisi`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `shift_jaga`
--

LOCK TABLES `shift_jaga` WRITE;
/*!40000 ALTER TABLE `shift_jaga` DISABLE KEYS */;
INSERT INTO `shift_jaga` VALUES (1,'dokter','dr. H. Ahmad Fauzan, Sp.A','Dokter Penanggung Jawab',NULL,'2026-11-05 21:39:04'),(2,'perawat','Ns. Hendra Pratama, S.Kep','Perawat Triase',NULL,'2026-11-05 21:39:04'),(3,'farmasi','Apt. Rahmat Hidayat, S.Farm','Apoteker Jaga',NULL,'2026-11-05 21:39:04');
/*!40000 ALTER TABLE `shift_jaga` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `role` enum('admin','petugas_kesehatan','petugas_farmasi','santri_wali') NOT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `password` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'admin','$2y$10$NO/dksb.BCfxfaWM/MXemOWG9eNogoG/.nrSBPKpg5y8wslH/CAJu','dr. H. Ahmad Fauzan, Sp.A','admin','081122334455',1,'2026-10-27 02:05:48','2026-10-27 02:05:48',''),(2,'nakes1','$2y$10$NO/dksb.BCfxfaWM/MXemOWG9eNogoG/.nrSBPKpg5y8wslH/CAJu','dr. Siti Nurhaliza, M.Biomed','petugas_kesehatan','081234567801',1,'2026-10-27 02:05:48','2026-10-27 02:05:48',''),(3,'nakes2','$2y$10$NO/dksb.BCfxfaWM/MXemOWG9eNogoG/.nrSBPKpg5y8wslH/CAJu','Ns. Hendra Pratama, S.Kep','petugas_kesehatan','081234567802',1,'2026-10-27 02:05:48','2026-10-27 02:05:48',''),(4,'farmasi1','$2y$10$NO/dksb.BCfxfaWM/MXemOWG9eNogoG/.nrSBPKpg5y8wslH/CAJu','Apt. Rahmat Hidayat, S.Farm','petugas_farmasi','081234567803',1,'2026-10-27 02:05:48','2026-10-27 02:05:48',''),(14,'085770909120','$2y$10$eAzx0LWNfAiuRpgJXH7Rc.lNEYSR7rRsMZ3V9LuWt4dL1meG8jxfq','ahmad subene','santri_wali','085770909120',1,'2026-11-03 16:37:14','2026-11-03 16:37:14',''),(15,'2411070087','','Wali Santri','',NULL,1,'2026-11-03 17:08:19','2026-11-03 17:08:19','123456'),(16,'0873926322','$2y$10$YqadragOePPMRfte4z9tH.QfPQ7fnnDhcS5lYewryc3laYqm4.z/u','siti','santri_wali','0873926322',1,'2026-11-05 15:45:39','2026-11-05 15:45:39',''),(17,'0895798678','$2y$10$weWBbgdWfrxYWP9ILIElf.SPE29gf3OORFxT/HMOkbwH/.Ggxl3pi','siti','santri_wali','0895798678',1,'2026-11-05 15:46:57','2026-11-05 15:46:57','');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-19 22:21:02
