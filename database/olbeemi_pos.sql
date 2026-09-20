
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
DROP TABLE IF EXISTS `bahan_baku`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bahan_baku` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_bahan` varchar(100) NOT NULL,
  `stok` decimal(10,2) NOT NULL DEFAULT 0.00,
  `satuan` varchar(20) NOT NULL,
  `minimum_stok` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nama_bahan` (`nama_bahan`)
) ENGINE=InnoDB AUTO_INCREMENT=117 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `bahan_baku` WRITE;
/*!40000 ALTER TABLE `bahan_baku` DISABLE KEYS */;
INSERT INTO `bahan_baku` VALUES (78,'Kopi Arabica',549.00,'g',200.00,'2026-08-13 04:57:04','2026-09-19 19:06:20.416506'),(79,'Kopi Robusta',564.00,'g',200.00,'2026-08-13 04:57:04','2026-09-19 19:06:20.415849'),(80,'Susu UHT',3330.00,'ml',1000.00,'2026-08-13 04:57:04','2026-09-19 19:06:20.417637'),(81,'Gula Aren',1465.00,'ml',500.00,'2026-08-13 04:57:04','2026-09-19 19:06:20.418355'),(82,'Bubuk Cokelat',600.00,'g',200.00,'2026-08-13 04:57:04','2026-09-19 16:18:57.232486'),(83,'Bubuk Matcha',375.00,'g',100.00,'2026-08-13 04:57:04','2026-09-19 16:18:57.232938'),(84,'Krimer',600.00,'g',200.00,'2026-08-13 04:57:04','2026-09-19 16:18:57.221070'),(85,'Sirup Berry',3105.00,'ml',200.00,'2026-08-13 04:57:04','2026-09-19 19:27:23.131397'),(86,'Sirup Peach',2500.00,'ml',200.00,'2026-08-13 04:57:04','2026-09-19 19:27:23.136915'),(87,'Sirup Butterscotch',600.00,'ml',200.00,'2026-08-13 04:57:04','2026-09-19 16:18:57.234608'),(88,'Air Mineral',14720.00,'ml',5000.00,'2026-08-13 04:57:04','2026-09-19 19:04:16.973842'),(89,'Es Batu',5460.00,'g',2000.00,'2026-08-13 04:57:04','2026-09-19 19:06:20.419382'),(90,'Cinnamon',6.00,'g',50.00,'2026-08-13 04:57:04','2026-09-19 16:18:57.230193'),(91,'Cheesy Foam',600.00,'g',200.00,'2026-08-13 04:57:04','2026-09-19 16:18:57.233836'),(92,'Caramel Crumble',300.00,'g',100.00,'2026-08-13 04:57:04','2026-09-19 16:18:57.236623'),(93,'Strawberry Sauce',600.00,'ml',200.00,'2026-08-13 04:57:04','2026-09-19 16:18:57.231868'),(94,'Cup',55.00,'pcs',20.00,'2026-08-13 04:57:04','2026-09-19 19:06:20.420556'),(95,'Sedotan',55.00,'pcs',20.00,'2026-08-13 04:57:04','2026-09-19 19:06:20.421482'),(96,'Plastik Takeaway',55.00,'pcs',20.00,'2026-08-13 04:57:04','2026-09-19 19:06:20.422269'),(114,'Sirup Chese Italy',5000.00,'ml',250.00,'2026-09-09 22:15:17','2026-09-10 05:17:04.917381'),(115,'Susu Lembang',0.00,'ml',400.00,'2026-09-13 04:54:35','2026-09-13 11:54:35.442718'),(116,'Sirup Gula Jawa',2500.00,'ml',250.00,'2026-09-19 12:26:24','2026-09-19 19:27:23.127300');
/*!40000 ALTER TABLE `bahan_baku` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `bahan_kemasan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bahan_kemasan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bahan_id` int(11) NOT NULL,
  `satuan_kemasan` varchar(20) NOT NULL,
  `isi_satuan_dasar` decimal(14,3) NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 1,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_bahan_kemasan` (`bahan_id`,`satuan_kemasan`),
  KEY `idx_bahan_kemasan_default` (`bahan_id`,`is_default`)
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `bahan_kemasan` WRITE;
/*!40000 ALTER TABLE `bahan_kemasan` DISABLE KEYS */;
INSERT INTO `bahan_kemasan` VALUES (16,115,'botol',1000.000,1,1,'2026-09-13 13:48:54','2026-09-13 14:04:18'),(17,81,'botol',1000.000,1,1,'2026-09-13 13:49:28','2026-09-13 14:04:18'),(18,88,'galon',19000.000,1,1,'2026-09-13 14:03:10','2026-09-13 14:04:18'),(19,82,'pack',1000.000,1,1,'2026-09-13 14:03:10','2026-09-13 14:04:18'),(20,83,'pack',500.000,1,1,'2026-09-13 14:03:10','2026-09-13 14:04:18'),(21,92,'pack',500.000,1,1,'2026-09-13 14:03:10','2026-09-13 14:04:18'),(22,91,'pack',500.000,1,1,'2026-09-13 14:03:10','2026-09-13 14:04:18'),(23,90,'pack',100.000,1,1,'2026-09-13 14:03:10','2026-09-13 14:04:18'),(24,94,'pack',50.000,1,1,'2026-09-13 14:03:10','2026-09-13 14:04:18'),(25,89,'kantong',5000.000,1,1,'2026-09-13 14:03:10','2026-09-13 14:04:18'),(27,78,'bag',1000.000,1,1,'2026-09-13 14:03:10','2026-09-13 14:04:18'),(28,79,'bag',1000.000,1,1,'2026-09-13 14:03:10','2026-09-13 14:04:18'),(29,84,'pack',1000.000,1,1,'2026-09-13 14:03:10','2026-09-13 14:04:18'),(30,96,'pack',100.000,1,1,'2026-09-13 14:03:10','2026-09-13 14:04:18'),(31,95,'pack',100.000,1,1,'2026-09-13 14:03:10','2026-09-13 14:04:18'),(32,85,'botol',1000.000,1,1,'2026-09-13 14:03:10','2026-09-13 14:04:18'),(33,87,'botol',1000.000,1,1,'2026-09-13 14:03:10','2026-09-13 14:04:18'),(34,114,'botol',1000.000,1,1,'2026-09-13 14:03:10','2026-09-13 14:04:18'),(35,86,'botol',1000.000,1,1,'2026-09-13 14:03:10','2026-09-13 14:04:18'),(36,93,'botol',1000.000,1,1,'2026-09-13 14:03:10','2026-09-13 14:04:18'),(38,80,'kotak',1000.000,1,1,'2026-09-13 14:03:10','2026-09-13 14:04:18'),(39,116,'botol',500.000,1,0,'2026-09-19 19:54:57','2026-09-19 19:54:57');
/*!40000 ALTER TABLE `bahan_kemasan` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `bahan_kemasan_rantai`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bahan_kemasan_rantai` (
  `bahan_id` int(11) NOT NULL,
  `satuan_luar` varchar(20) NOT NULL,
  `jumlah_satuan_tengah` decimal(14,3) NOT NULL,
  `satuan_tengah` varchar(20) NOT NULL,
  `isi_satuan_dasar` decimal(14,3) NOT NULL,
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`bahan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `bahan_kemasan_rantai` WRITE;
/*!40000 ALTER TABLE `bahan_kemasan_rantai` DISABLE KEYS */;
/*!40000 ALTER TABLE `bahan_kemasan_rantai` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `menu`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `menu` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_menu` varchar(150) NOT NULL,
  `kategori` enum('Coffee','Non Coffee','Snack','Food','Dessert') NOT NULL,
  `harga_jual` decimal(12,2) NOT NULL DEFAULT 0.00,
  `keuntungan` decimal(12,2) NOT NULL DEFAULT 0.00,
  `foto` varchar(255) DEFAULT NULL,
  `status` enum('Aktif','Nonaktif') NOT NULL DEFAULT 'Aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `menu` WRITE;
/*!40000 ALTER TABLE `menu` DISABLE KEYS */;
INSERT INTO `menu` VALUES (26,'Ice Black','Coffee',13000.00,5000.00,'1786026752_6a749b00329e4.png','Aktif','2026-08-06 14:25:07'),(27,'Berry Party','Coffee',15000.00,5000.00,'1786026685_6a749abd9f786.png','Aktif','2026-08-06 14:26:39'),(28,'Sunshine','Coffee',16000.00,6000.00,'1786027884_6a749f6ca9dc6.png','Aktif','2026-08-06 14:28:19'),(29,'Manual Brew','Coffee',23000.00,9000.00,'1786026865_6a749b71bd3a0.png','Aktif','2026-08-06 14:34:25'),(30,'KoSu Sahabat','Coffee',13000.00,4000.00,'1786027125_6a749c75eea86.png','Aktif','2026-08-06 14:37:34'),(31,'Ice White','Coffee',16000.00,5000.00,'1786027262_6a749cfe220d6.png','Aktif','2026-08-06 14:41:02'),(32,'Ice Brown','Coffee',17000.00,6000.00,'1786027503_6a749defe3018.png','Aktif','2026-08-06 14:45:03'),(33,'Racikan','Coffee',20000.00,7000.00,'1786027716_6a749ec439c97.png','Aktif','2026-08-06 14:48:36'),(34,'Berry Shine T','Non Coffee',10000.00,4000.00,'1786027990_6a749fd6a4503.png','Aktif','2026-08-06 14:53:10'),(35,'Korean Berry','Non Coffee',15000.00,5000.00,'1786028184_6a74a0985bf33.png','Aktif','2026-08-06 14:56:24'),(36,'Chocolate','Non Coffee',15000.00,5000.00,'1786028297_6a74a109e1ab4.png','Aktif','2026-08-06 14:58:17'),(37,'Matcha Lattea','Non Coffee',18000.00,6000.00,'1786028433_6a74a191793d5.png','Aktif','2026-08-06 15:00:33'),(38,'Smooth Butter','Coffee',22000.00,7000.00,'1786028678_6a74a286bb6c8.png','Aktif','2026-08-06 15:04:38'),(45,'Kopi Nang Kau','Coffee',5000.00,2000.00,'1788959533_6aa15b2da658b.png','Aktif','2026-09-09 13:13:35');
/*!40000 ALTER TABLE `menu` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `menu_resep`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `menu_resep` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `menu_id` int(11) NOT NULL,
  `bahan_id` int(11) NOT NULL,
  `jumlah` decimal(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `fk_menu_resep_menu` (`menu_id`),
  KEY `fk_menu_resep_bahan` (`bahan_id`),
  CONSTRAINT `fk_menu_resep_bahan` FOREIGN KEY (`bahan_id`) REFERENCES `bahan_baku` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_menu_resep_menu` FOREIGN KEY (`menu_id`) REFERENCES `menu` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=346 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `menu_resep` WRITE;
/*!40000 ALTER TABLE `menu_resep` DISABLE KEYS */;
INSERT INTO `menu_resep` VALUES (241,26,79,10.00),(242,26,78,8.00),(243,26,88,150.00),(244,26,89,120.00),(245,26,94,1.00),(246,26,95,1.00),(247,26,96,1.00),(248,27,78,15.00),(249,27,85,20.00),(250,27,88,130.00),(251,27,89,120.00),(252,27,94,1.00),(253,27,95,1.00),(254,27,96,1.00),(255,28,78,15.00),(256,28,86,20.00),(257,28,88,130.00),(258,28,89,120.00),(259,28,94,1.00),(260,28,95,1.00),(261,28,96,1.00),(262,29,78,18.00),(263,29,88,250.00),(264,29,94,1.00),(265,29,95,1.00),(266,29,96,1.00),(267,31,78,10.00),(268,31,79,8.00),(269,31,80,150.00),(270,31,89,100.00),(271,31,94,1.00),(272,31,95,1.00),(273,31,96,1.00),(274,32,78,10.00),(275,32,79,8.00),(276,32,80,120.00),(277,32,81,20.00),(278,32,89,100.00),(279,32,94,1.00),(280,32,95,1.00),(281,32,96,1.00),(282,33,78,10.00),(283,33,79,8.00),(284,33,80,120.00),(285,33,81,15.00),(286,33,84,10.00),(287,33,90,2.00),(288,33,89,100.00),(289,33,94,1.00),(290,33,95,1.00),(291,33,96,1.00),(292,38,78,10.00),(293,38,79,8.00),(294,38,80,120.00),(295,38,87,20.00),(296,38,91,20.00),(297,38,92,5.00),(298,38,89,100.00),(299,38,94,1.00),(300,38,95,1.00),(301,38,96,1.00),(302,30,79,10.00),(303,30,78,8.00),(304,30,80,150.00),(305,30,81,15.00),(306,30,89,100.00),(307,30,94,1.00),(308,30,95,1.00),(309,30,96,1.00),(310,34,85,25.00),(311,34,88,150.00),(312,34,89,120.00),(313,34,94,1.00),(314,34,95,1.00),(315,34,96,1.00),(316,35,80,150.00),(317,35,85,15.00),(318,35,93,20.00),(319,35,89,100.00),(320,35,94,1.00),(321,35,95,1.00),(322,35,96,1.00),(323,36,82,20.00),(324,36,80,150.00),(325,36,84,10.00),(326,36,89,100.00),(327,36,94,1.00),(328,36,95,1.00),(329,36,96,1.00),(330,37,83,15.00),(331,37,80,150.00),(332,37,84,10.00),(333,37,91,15.00),(334,37,89,100.00),(335,37,94,1.00),(336,37,95,1.00),(337,37,96,1.00),(342,45,94,1.00),(343,45,88,100.00),(344,45,78,5.00);
/*!40000 ALTER TABLE `menu_resep` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `pengadaan_pesanan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pengadaan_pesanan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_id` int(11) NOT NULL,
  `tanggal` datetime NOT NULL,
  `legacy_key` varchar(80) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `legacy_key` (`legacy_key`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `pengadaan_pesanan` WRITE;
/*!40000 ALTER TABLE `pengadaan_pesanan` DISABLE KEYS */;
INSERT INTO `pengadaan_pesanan` VALUES (1,14,'2026-09-06 14:43:13','14|2026-09-06 14:43:13'),(2,10,'2026-09-06 19:52:45','10|2026-09-06 19:52:45'),(3,14,'2026-09-07 09:56:46','14|2026-09-07 09:56:46'),(4,11,'2026-09-08 11:27:10','11|2026-09-08 11:27:10'),(5,9,'2026-09-09 06:58:22','9|2026-09-09 06:58:22'),(6,10,'2026-09-09 06:58:57','10|2026-09-09 06:58:57'),(7,11,'2026-09-09 08:52:29','11|2026-09-09 08:52:29'),(8,10,'2026-09-10 04:49:42','10|2026-09-10 04:49:42'),(9,11,'2026-09-10 05:15:17','11|2026-09-10 05:15:17'),(18,12,'2026-09-10 07:02:33',NULL),(19,11,'2026-09-10 07:19:18',NULL),(22,10,'2026-09-13 11:54:35',NULL),(23,11,'2026-09-19 19:26:24',NULL);
/*!40000 ALTER TABLE `pengadaan_pesanan` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `pengeluaran`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pengeluaran` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `riwayat_masuk_id` int(11) DEFAULT NULL,
  `bahan_id` int(11) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `pesanan_supplier_id` int(11) DEFAULT NULL,
  `jenis` varchar(40) NOT NULL,
  `nama_bahan` varchar(120) DEFAULT NULL,
  `nama_supplier` varchar(120) DEFAULT NULL,
  `nominal` decimal(14,2) NOT NULL DEFAULT 0.00,
  `keterangan` varchar(255) DEFAULT NULL,
  `tanggal` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_riwayat_pengeluaran` (`riwayat_masuk_id`),
  KEY `index_pengeluaran_tanggal` (`tanggal`),
  KEY `index_pengeluaran_jenis` (`jenis`),
  KEY `index_pengeluaran_pesanan` (`pesanan_supplier_id`)
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `pengeluaran` WRITE;
/*!40000 ALTER TABLE `pengeluaran` DISABLE KEYS */;
INSERT INTO `pengeluaran` VALUES (5,82,78,9,NULL,'Stok Awal','Kopi Arabica','Aja Coffee',160000.00,'Pembelian stok awal','2026-08-13 11:57:04','2026-08-13 04:57:04'),(6,83,79,9,NULL,'Stok Awal','Kopi Robusta','Aja Coffee',115000.00,'Pembelian stok awal','2026-08-13 11:57:04','2026-08-13 04:57:04'),(7,84,80,10,NULL,'Stok Awal','Susu UHT','Sukasari Baking Bandung',147000.00,'Pembelian stok awal','2026-08-13 11:57:04','2026-08-13 04:57:04'),(8,85,81,10,NULL,'Stok Awal','Gula Aren','Sukasari Baking Bandung',105000.00,'Pembelian stok awal','2026-08-13 11:57:04','2026-08-13 04:57:04'),(9,86,82,10,NULL,'Stok Awal','Bubuk Cokelat','Sukasari Baking Bandung',90000.00,'Pembelian stok awal','2026-08-13 11:57:04','2026-08-13 04:57:04'),(10,87,83,10,NULL,'Stok Awal','Bubuk Matcha','Sukasari Baking Bandung',100000.00,'Pembelian stok awal','2026-08-13 11:57:04','2026-08-13 04:57:04'),(11,88,84,11,NULL,'Stok Awal','Krimer','Toffin West Java',55000.00,'Pembelian stok awal','2026-08-13 11:57:04','2026-08-13 04:57:04'),(12,89,85,11,NULL,'Stok Awal','Sirup Berry','Toffin West Java',170000.00,'Pembelian stok awal','2026-08-13 11:57:04','2026-08-13 04:57:04'),(13,90,86,11,NULL,'Stok Awal','Sirup Peach','Toffin West Java',170000.00,'Pembelian stok awal','2026-08-13 11:57:04','2026-08-13 04:57:04'),(14,91,87,11,NULL,'Stok Awal','Sirup Butterscotch','Toffin West Java',176000.00,'Pembelian stok awal','2026-08-13 11:57:04','2026-08-13 04:57:04'),(15,92,88,12,NULL,'Stok Awal','Air Mineral','Agen Galon Le Minerale Bandung',26500.00,'Pembelian stok awal','2026-08-13 11:57:04','2026-08-13 04:57:04'),(16,93,89,13,NULL,'Stok Awal','Es Batu','Es Batu Kristal Paman',25000.00,'Pembelian stok awal','2026-08-13 11:57:04','2026-08-13 04:57:04'),(17,94,90,10,NULL,'Stok Awal','Cinnamon','Sukasari Baking Bandung',35000.00,'Pembelian stok awal','2026-08-13 11:57:04','2026-08-13 04:57:04'),(18,95,91,10,NULL,'Stok Awal','Cheesy Foam','Sukasari Baking Bandung',80000.00,'Pembelian stok awal','2026-08-13 11:57:04','2026-08-13 04:57:04'),(19,96,92,10,NULL,'Stok Awal','Caramel Crumble','Sukasari Baking Bandung',45000.00,'Pembelian stok awal','2026-08-13 11:57:04','2026-08-13 04:57:04'),(20,97,93,11,NULL,'Stok Awal','Strawberry Sauce','Toffin West Java',60000.00,'Pembelian stok awal','2026-08-13 11:57:04','2026-08-13 04:57:04'),(21,98,94,14,NULL,'Stok Awal','Cup','Kemasan Jaya Gemilang',45000.00,'Pembelian stok awal','2026-08-13 11:57:04','2026-08-13 04:57:04'),(22,99,95,14,NULL,'Stok Awal','Sedotan','Kemasan Jaya Gemilang',15000.00,'Pembelian stok awal','2026-08-13 11:57:04','2026-08-13 04:57:04'),(23,100,96,14,NULL,'Stok Awal','Plastik Takeaway','Kemasan Jaya Gemilang',25000.00,'Pembelian stok awal','2026-08-13 11:57:04','2026-08-13 04:57:04'),(40,117,94,14,1,'Restock','Cup','Kemasan Jaya Gemilang',50000.00,'Penerimaan Supplier','2026-09-06 14:44:56','2026-09-06 07:44:56'),(41,118,92,10,2,'Restock','Caramel Crumble','Sukasari Baking Bandung',50000.00,'Pembelian bahan baku','2026-09-06 20:51:55','2026-09-06 13:51:55'),(42,119,93,11,5,'Restock','Strawberry Sauce','Toffin West Java',50000.00,'Pembelian bahan baku','2026-09-08 11:28:03','2026-09-08 04:28:03'),(43,120,91,10,8,'Restock','Cheesy Foam','Sukasari Baking Bandung',60000.00,'Pembelian bahan baku','2026-09-09 06:59:57','2026-09-08 23:59:57'),(44,121,85,11,9,'Restock','Sirup Berry','Toffin West Java',75000.00,'Pembelian bahan baku','2026-09-09 08:53:43','2026-09-09 01:53:43'),(45,122,84,11,10,'Restock','Krimer','Toffin West Java',25000.00,'Pembelian bahan baku','2026-09-09 08:54:22','2026-09-09 01:54:22'),(49,126,114,11,16,'Stok Awal','Sirup Chese Italy','Toffin West Java',125000.00,'Pembelian stok awal','2026-09-10 05:17:04','2026-09-09 22:17:04'),(50,127,85,11,15,'Restock','Sirup Berry','Toffin West Java',125000.00,'Pembelian bahan baku','2026-09-10 05:17:04','2026-09-09 22:17:04'),(51,128,86,11,14,'Restock','Sirup Peach','Toffin West Java',125000.00,'Pembelian bahan baku','2026-09-10 05:17:04','2026-09-09 22:17:04'),(52,129,84,11,13,'Restock','Krimer','Toffin West Java',250000.00,'Pembelian bahan baku','2026-09-10 05:17:04','2026-09-09 22:17:04'),(53,130,88,12,17,'Restock','Air Mineral','Agen Galon Terdekat',100000.00,'Pembelian bahan baku','2026-09-10 07:03:13','2026-09-10 00:03:13'),(54,131,85,11,19,'Restock','Sirup Berry','Toffin West Java',450000.00,'Pembelian bahan baku','2026-09-10 07:25:00','2026-09-10 00:25:00'),(55,132,87,11,18,'Restock','Sirup Butterscotch','Toffin West Java',150000.00,'Pembelian bahan baku','2026-09-10 07:25:00','2026-09-10 00:25:00'),(56,141,116,11,31,'Stok Awal','Sirup Gula Jawa','Toffin West Java',175000.00,'Pembelian stok awal','2026-09-19 19:27:23','2026-09-19 12:27:23'),(57,142,85,11,30,'Restock','Sirup Berry','Toffin West Java',250000.00,'Pembelian bahan baku','2026-09-19 19:27:23','2026-09-19 12:27:23'),(58,143,86,11,29,'Restock','Sirup Peach','Toffin West Java',275000.00,'Pembelian bahan baku','2026-09-19 19:27:23','2026-09-19 12:27:23');
/*!40000 ALTER TABLE `pengeluaran` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `permintaan_menu`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permintaan_menu` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `jenis` varchar(30) NOT NULL,
  `menu_id` int(11) DEFAULT NULL,
  `data_json` text NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Menunggu',
  `reviewed_by` int(11) DEFAULT NULL,
  `review_note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_permintaan_menu_status` (`status`),
  KEY `idx_permintaan_menu_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `permintaan_menu` WRITE;
/*!40000 ALTER TABLE `permintaan_menu` DISABLE KEYS */;
INSERT INTO `permintaan_menu` VALUES (1,5,'Ubah Menu',26,'{\"nama_menu\":\"Icee Black\",\"kategori\":\"Coffee\",\"harga_jual\":14000,\"keuntungan\":6000,\"foto\":\"1786026752_6a749b00329e4.png\",\"foto_baru\":false,\"status\":\"Aktif\",\"riwayat_penolakan\":[{\"alasan\":\"UBAH LAGI YA\",\"tanggal\":\"2026-09-06 22:10:37\",\"pemeriksa_id\":\"3\"}]}','Menunggu',NULL,NULL,'2026-09-05 22:43:33',NULL),(3,5,'Tambah Menu',NULL,'{\"nama_menu\": \"Coffee Latte\", \"kategori\": \"Coffee\", \"harga_jual\": 18000, \"keuntungan\": 6000, \"foto\": null, \"status\": \"Aktif\"}','Disetujui',3,NULL,'2026-09-05 22:43:33','2026-09-06 22:27:33'),(4,5,'Ubah Menu',38,'{\"nama_menu\":\"Smooth Butter\",\"kategori\":\"Non Coffee\",\"harga_jual\":22000,\"keuntungan\":7000,\"foto\":\"1786028678_6a74a286bb6c8.png\",\"foto_baru\":false,\"status\":\"Aktif\"}','Disetujui',3,NULL,'2026-09-06 21:24:19','2026-09-06 22:26:50'),(6,5,'Ubah Menu',38,'{\"nama_menu\":\"Smooth Butter\",\"kategori\":\"Coffee\",\"foto\":\"1786028678_6a74a286bb6c8.png\",\"foto_baru\":false,\"status\":\"Aktif\"}','Disetujui',3,NULL,'2026-09-06 22:30:38','2026-09-06 22:31:20'),(24,5,'Ubah Menu',34,'{\"nama_menu\":\"Berry Shine Tea\",\"kategori\":\"Non Coffee\",\"foto\":\"1786027990_6a749fd6a4503.png\",\"foto_baru\":false,\"status\":\"Aktif\",\"riwayat_penolakan\":[{\"alasan\":\"Nama menu perlu disesuaikan dengan standar penulisan Olbeemi.\",\"tanggal\":\"2026-09-07 09:56:46\",\"pemeriksa_id\":\"3\"}]}','Menunggu',NULL,NULL,'2026-09-09 20:15:02',NULL),(27,5,'Tambah Menu',NULL,'{\"nama_menu\":\"Kopi Nang Kau\",\"kategori\":\"Coffee\",\"foto\":\"1788959533_6aa15b2da658b.png\",\"status\":\"Aktif\",\"resep_items\":[{\"bahan_id\":94,\"nama_bahan\":\"Cup\",\"satuan\":\"pcs\",\"jumlah\":1},{\"bahan_id\":88,\"nama_bahan\":\"Air Mineral\",\"satuan\":\"ml\",\"jumlah\":100},{\"bahan_id\":78,\"nama_bahan\":\"Kopi Arabica\",\"satuan\":\"g\",\"jumlah\":5}]}','Disetujui',3,NULL,'2026-09-09 20:12:13','2026-09-09 20:13:35');
/*!40000 ALTER TABLE `permintaan_menu` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `permintaan_perubahan_bahan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permintaan_perubahan_bahan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `bahan_id` int(11) NOT NULL,
  `jenis` varchar(20) NOT NULL,
  `usulan` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`usulan`)),
  `status` varchar(20) NOT NULL DEFAULT 'Menunggu',
  `reviewed_by` int(11) DEFAULT NULL,
  `review_note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_perubahan_bahan_status` (`status`),
  KEY `idx_perubahan_bahan_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `permintaan_perubahan_bahan` WRITE;
/*!40000 ALTER TABLE `permintaan_perubahan_bahan` DISABLE KEYS */;
INSERT INTO `permintaan_perubahan_bahan` VALUES (1,5,81,'Info','{\"nama_bahan\":\"Gula Aren\",\"minimum_stok\":600,\"satuan_baru\":\"ml\"}','Menunggu',NULL,NULL,'2026-09-19 20:46:58',NULL),(2,5,81,'Kemasan','{\"satuan_kemasan\":\"botol\",\"isi_satuan_dasar\":750}','Menunggu',NULL,NULL,'2026-09-19 20:46:58',NULL);
/*!40000 ALTER TABLE `permintaan_perubahan_bahan` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `permintaan_stok`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permintaan_stok` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `bahan_id` int(11) DEFAULT NULL,
  `is_bahan_baru` tinyint(1) NOT NULL DEFAULT 0,
  `nama_bahan_baru` varchar(120) DEFAULT NULL,
  `satuan_baru` varchar(20) DEFAULT NULL,
  `minimum_stok_baru` decimal(12,2) DEFAULT NULL,
  `jenis` varchar(20) NOT NULL DEFAULT 'Keluar',
  `jumlah` decimal(12,2) NOT NULL DEFAULT 0.00,
  `supplier_id` int(11) DEFAULT NULL,
  `pesanan_supplier_id` int(11) DEFAULT NULL,
  `stok_sebelum` decimal(12,2) NOT NULL,
  `stok_usulan` decimal(12,2) NOT NULL,
  `total_pembelian` decimal(14,2) NOT NULL DEFAULT 0.00,
  `harga_supplier` decimal(12,2) NOT NULL DEFAULT 0.00,
  `alasan` varchar(255) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Menunggu',
  `reviewed_by` int(11) DEFAULT NULL,
  `review_note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` datetime DEFAULT NULL,
  `jumlah_input` decimal(12,2) DEFAULT NULL,
  `satuan_input` varchar(20) DEFAULT NULL,
  `isi_per_kemasan` decimal(12,2) DEFAULT NULL,
  `batch_key` varchar(32) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_permintaan_status` (`status`),
  KEY `idx_permintaan_user` (`user_id`),
  KEY `idx_stock_batch` (`batch_key`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `permintaan_stok` WRITE;
/*!40000 ALTER TABLE `permintaan_stok` DISABLE KEYS */;
INSERT INTO `permintaan_stok` VALUES (1,5,88,0,NULL,NULL,NULL,'Keluar',530.00,NULL,NULL,17530.00,17000.00,0.00,0.00,'Stok Rusak','Disetujui',3,NULL,'2026-09-05 20:59:57','2026-09-05 21:01:19',NULL,NULL,NULL,NULL),(2,5,80,0,NULL,NULL,NULL,'Restock',0.00,NULL,NULL,4290.00,4290.00,0.00,0.00,'Permintaan Restock','Disetujui',3,NULL,'2026-09-05 22:43:33','2026-09-05 22:46:09',NULL,NULL,NULL,NULL),(4,5,94,0,NULL,NULL,NULL,'Restock',0.00,NULL,NULL,69.00,69.00,0.00,0.00,'Permintaan Restock','Selesai',3,NULL,'2026-09-06 14:41:10','2026-09-06 14:41:46',NULL,NULL,NULL,NULL),(5,5,94,0,NULL,NULL,NULL,'Masuk',60.00,14,1,69.00,129.00,50000.00,0.00,'Penerimaan Supplier','Disetujui',3,NULL,'2026-09-06 14:44:14','2026-09-06 14:44:56',2.00,'pack',30.00,NULL),(6,5,92,0,NULL,NULL,NULL,'Masuk',500.00,10,2,2.00,502.00,50000.00,0.00,'Penerimaan Supplier','Dibatalkan',NULL,'Dialihkan ke alur konfirmasi barang langsung','2026-09-06 20:01:57','2026-09-06 20:08:18',500.00,'g',0.00,NULL),(9,5,80,0,NULL,NULL,NULL,'Keluar',5.00,NULL,NULL,4140.00,4135.00,0.00,0.00,'Stok Rusak','Menunggu',NULL,NULL,'2026-09-07 01:17:24',NULL,5.00,'ml',0.00,'1818bd184ed03ab43f357f822a35f215'),(10,5,96,0,NULL,NULL,NULL,'Keluar',5.00,NULL,NULL,74.00,69.00,0.00,0.00,'Stok Rusak','Menunggu',NULL,NULL,'2026-09-07 01:17:24',NULL,5.00,'pcs',0.00,'1818bd184ed03ab43f357f822a35f215'),(11,5,93,0,NULL,NULL,NULL,'Keluar',5.00,NULL,NULL,200.00,195.00,0.00,0.00,'Stok Basi','Menunggu',NULL,NULL,'2026-09-07 01:17:24',NULL,5.00,'ml',0.00,'1818bd184ed03ab43f357f822a35f215'),(12,5,94,0,NULL,NULL,NULL,'Keluar',3.00,NULL,NULL,90.00,87.00,0.00,0.00,'Stok Rusak','Disetujui',3,'','2026-09-07 07:56:46','2026-09-09 18:39:42',3.00,'pcs',0.00,'bb8c75d20abc33339102213493f61708'),(13,5,95,0,NULL,NULL,NULL,'Keluar',5.00,NULL,NULL,73.00,68.00,0.00,0.00,'Salah Input','Disetujui',3,'','2026-09-07 07:56:46','2026-09-09 18:39:42',5.00,'pcs',0.00,'bb8c75d20abc33339102213493f61708'),(14,5,94,0,NULL,NULL,NULL,'Keluar',5.00,NULL,NULL,123.00,118.00,0.00,0.00,'Stok Rusak','Disetujui',3,'','2026-09-09 08:57:08','2026-09-09 09:05:12',5.00,'pcs',0.00,'3d100531d914b39c7b0d8e73f6b3f7a4'),(15,5,94,0,NULL,NULL,NULL,'Keluar',18.00,NULL,NULL,118.00,100.00,0.00,0.00,'Stok Rusak','Disetujui',3,'','2026-09-09 09:06:07','2026-09-09 09:06:39',18.00,'pcs',0.00,'2a7028e64e29b67516cc9c98d5db0ee0'),(16,5,88,0,NULL,NULL,NULL,'Keluar',250.00,NULL,NULL,16310.00,16060.00,0.00,0.00,'Stok Basi','Disetujui',3,'Jumlah sesuai hasil pemeriksaan bahan.','2026-09-09 09:21:55','2026-09-09 09:36:55',250.00,'ml',0.00,'1d7a7d34325c861d5b1417e79b4bef0f'),(17,5,89,0,NULL,NULL,NULL,'Keluar',200.00,NULL,NULL,7260.00,7060.00,0.00,0.00,'Salah Input','Disetujui',3,'Jumlah sesuai hasil pemeriksaan bahan.','2026-09-09 09:21:55','2026-09-09 09:36:55',200.00,'g',0.00,'1d7a7d34325c861d5b1417e79b4bef0f'),(18,5,94,0,NULL,NULL,NULL,'Keluar',10.00,NULL,NULL,100.00,90.00,0.00,0.00,'Stok Rusak','Disetujui',3,'','2026-09-09 09:45:49','2026-09-09 09:46:14',10.00,'pcs',0.00,'337cb6346d55c32e05c77b104ed178ef'),(19,5,81,0,NULL,NULL,NULL,'Keluar',1.00,NULL,NULL,1465.00,1464.00,0.00,0.00,'UJI — Penyesuaian Stok','Menunggu',NULL,NULL,'2026-09-19 20:46:58',NULL,1.00,'ml',0.00,NULL);
/*!40000 ALTER TABLE `permintaan_stok` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `pesanan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pesanan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kode_pesanan` varchar(30) NOT NULL,
  `nama_pelanggan` varchar(120) NOT NULL,
  `nomor_wa` varchar(30) DEFAULT NULL,
  `metode_pembayaran` enum('Cash','QRIS') NOT NULL DEFAULT 'Cash',
  `sumber_pesanan` varchar(40) NOT NULL DEFAULT 'QR Menu',
  `catatan` text DEFAULT NULL,
  `status` enum('Menunggu','Diproses','Selesai','Dibatalkan') NOT NULL DEFAULT 'Menunggu',
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bayar` decimal(12,2) NOT NULL DEFAULT 0.00,
  `kembalian` decimal(12,2) NOT NULL DEFAULT 0.00,
  `transaksi_id` int(11) DEFAULT NULL,
  `selesai_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode_pesanan` (`kode_pesanan`)
) ENGINE=InnoDB AUTO_INCREMENT=113 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `pesanan` WRITE;
/*!40000 ALTER TABLE `pesanan` DISABLE KEYS */;
INSERT INTO `pesanan` VALUES (58,'ORD-20260803101500','Dina','081234560001','Cash','QR Menu','Data simulasi skripsi','Selesai',13000.00,20000.00,7000.00,61,'2026-08-03 10:15:00','2026-08-03 03:15:00','2026-08-13 06:31:29'),(59,'ORD-KD-20260810142000-003B','Pelanggan Kedai',NULL,'QRIS','Kedai','Data simulasi skripsi','Selesai',15000.00,15000.00,0.00,62,'2026-08-10 14:20:00','2026-08-10 07:20:00','2026-08-13 06:31:29'),(60,'ORD-20260813113000','Raka','081234560003','Cash','QR Menu','Data simulasi skripsi','Selesai',16000.00,20000.00,4000.00,63,'2026-08-13 11:30:00','2026-08-13 04:30:00','2026-08-13 06:31:29'),(61,'ORD-KD-20260813125359-E916','Pelanggan Kedai','','Cash','Kedai','','Selesai',17000.00,20000.00,3000.00,64,'2026-08-13 12:54:02','2026-08-13 05:53:59','2026-08-13 05:54:02'),(62,'ORD-20260718092000','Alya','081200000101','Cash','QR Menu','Data simulasi skripsi','Selesai',16000.00,20000.00,4000.00,65,'2026-07-18 09:20:00','2026-07-18 02:20:00','2026-08-13 06:31:29'),(63,'ORD-KD-20260720131000-003F','Pelanggan Kedai',NULL,'QRIS','Kedai','Data simulasi skripsi','Selesai',23000.00,23000.00,0.00,66,'2026-07-20 13:10:00','2026-07-20 06:10:00','2026-08-13 06:31:29'),(64,'ORD-20260722153500','Bima','081200000103','QRIS','QR Menu','Data simulasi skripsi','Selesai',15000.00,15000.00,0.00,67,'2026-07-22 15:35:00','2026-07-22 08:35:00','2026-08-13 06:31:29'),(65,'ORD-KD-20260724110500-0041','Pelanggan Kedai',NULL,'Cash','Kedai','Data simulasi skripsi','Selesai',15000.00,20000.00,5000.00,68,'2026-07-24 11:05:00','2026-07-24 04:05:00','2026-08-13 06:31:29'),(66,'ORD-20260726164000','Citra','081200000105','Cash','QR Menu','Data simulasi skripsi','Selesai',18000.00,20000.00,2000.00,69,'2026-07-26 16:40:00','2026-07-26 09:40:00','2026-08-13 06:31:29'),(67,'ORD-KD-20260728105000-0043','Pelanggan Kedai',NULL,'QRIS','Kedai','Data simulasi skripsi','Selesai',13000.00,13000.00,0.00,70,'2026-07-28 10:50:00','2026-07-28 03:50:00','2026-08-13 06:31:29'),(68,'ORD-20260730142500','Damar','081200000107','QRIS','QR Menu','Data simulasi skripsi','Selesai',10000.00,10000.00,0.00,71,'2026-07-30 14:25:00','2026-07-30 07:25:00','2026-08-13 06:31:29'),(69,'ORD-KD-20260731171500-0045','Pelanggan Kedai',NULL,'Cash','Kedai','Data simulasi skripsi','Selesai',13000.00,15000.00,2000.00,72,'2026-07-31 17:15:00','2026-07-31 10:15:00','2026-08-13 06:31:29'),(70,'ORD-20260801094500','Elisa','081200000109','Cash','QR Menu','Data simulasi skripsi','Selesai',15000.00,20000.00,5000.00,73,'2026-08-01 09:45:00','2026-08-01 02:45:00','2026-08-13 06:31:29'),(71,'ORD-KD-20260802123000-0047','Pelanggan Kedai',NULL,'QRIS','Kedai','Data simulasi skripsi','Selesai',17000.00,17000.00,0.00,74,'2026-08-02 12:30:00','2026-08-02 05:30:00','2026-08-13 06:31:29'),(72,'ORD-20260804151000','Fajar','081200000111','QRIS','QR Menu','Data simulasi skripsi','Selesai',16000.00,16000.00,0.00,75,'2026-08-04 15:10:00','2026-08-04 08:10:00','2026-08-13 06:31:29'),(73,'ORD-KD-20260805102000-0049','Pelanggan Kedai',NULL,'Cash','Kedai','Data simulasi skripsi','Selesai',16000.00,20000.00,4000.00,76,'2026-08-05 10:20:00','2026-08-05 03:20:00','2026-08-13 06:31:29'),(74,'ORD-20260806135000','Gita','081200000113','Cash','QR Menu','Data simulasi skripsi','Selesai',15000.00,20000.00,5000.00,77,'2026-08-06 13:50:00','2026-08-06 06:50:00','2026-08-13 06:31:29'),(75,'ORD-KD-20260807160500-004B','Pelanggan Kedai',NULL,'QRIS','Kedai','Data simulasi skripsi','Selesai',23000.00,23000.00,0.00,78,'2026-08-07 16:05:00','2026-08-07 09:05:00','2026-08-13 06:31:29'),(76,'ORD-20260808114000','Hana','081200000115','QRIS','QR Menu','Data simulasi skripsi','Selesai',18000.00,18000.00,0.00,79,'2026-08-08 11:40:00','2026-08-08 04:40:00','2026-08-13 06:31:29'),(77,'ORD-KD-20260812145500-004D','Pelanggan Kedai',NULL,'Cash','Kedai','Data simulasi skripsi','Selesai',13000.00,15000.00,2000.00,80,'2026-08-12 14:55:00','2026-08-12 07:55:00','2026-08-13 06:31:29'),(93,'ORD-20260813091000','Nadia','081200000201','Cash','QR Menu','Pelanggan membatalkan pesanan','Dibatalkan',13000.00,0.00,0.00,NULL,NULL,'2026-08-13 02:10:00','2026-08-13 06:30:46'),(94,'ORD-KD-20260813103500-A102','Pelanggan Kedai',NULL,'QRIS','Kedai','Pesanan dibatalkan pelanggan','Dibatalkan',15000.00,0.00,0.00,NULL,NULL,'2026-08-13 03:35:00','2026-08-13 06:30:46'),(95,'ORD-KD-20260811134500-B104','Pelanggan Kedai',NULL,'Cash','Kedai','Pesanan tidak dilanjutkan','Dibatalkan',23000.00,0.00,0.00,NULL,NULL,'2026-08-11 06:45:00','2026-08-13 06:30:46'),(96,'ORD-20260812152000','Putri','081200000203','QRIS','QR Menu','Pelanggan salah memilih menu','Dibatalkan',15000.00,0.00,0.00,NULL,NULL,'2026-08-12 08:20:00','2026-08-13 06:30:46'),(97,'ORD-20260809160500','Rian','081200000205','Cash','QR Menu','Pelanggan membatalkan pesanan','Dibatalkan',18000.00,0.00,0.00,NULL,NULL,'2026-08-09 09:05:00','2026-08-13 06:30:46'),(100,'ORD-KD-20260813165024-CE1F','Bagas','','QRIS','Kedai','-','Selesai',17000.00,17000.00,0.00,96,'2026-08-13 16:50:33','2026-08-13 09:50:24','2026-08-13 09:50:33'),(101,'ORD-20260813215156','Raka','082764536278','QRIS','QR Menu','gula banyakin','Diproses',44000.00,0.00,0.00,NULL,NULL,'2026-08-13 14:51:56','2026-08-13 14:51:56'),(102,'ORD-KD-20260813215648-4A4D','Sukta','082145672237','Cash','Kedai','less sugar','Diproses',97000.00,100000.00,3000.00,NULL,NULL,'2026-08-13 14:56:48','2026-08-13 14:56:48'),(103,'ORD-KD-20260906222904-B115','Pelanggan Kedai','','Cash','Kedai','','Diproses',23000.00,30000.00,7000.00,NULL,NULL,'2026-09-06 15:29:04','2026-09-06 15:29:04'),(104,'ORD-20260907095646-0104','Dina','628000985237','QRIS','QR Menu',NULL,'Selesai',16000.00,16000.00,0.00,100,'2026-09-07 10:05:19','2026-09-07 02:56:46','2026-09-20 11:41:45'),(105,'ORD-KD-20260907095646-0105','Pelanggan Kedai','','Cash','Kedai',NULL,'Diproses',23000.00,0.00,0.00,NULL,NULL,'2026-09-07 02:56:46','2026-09-07 03:02:30'),(106,'ORD-20260907095647-0106','Alya','628000202645','QRIS','QR Menu',NULL,'Selesai',20000.00,20000.00,0.00,97,'2026-09-07 09:56:46','2026-09-07 02:56:46','2026-09-20 11:41:45'),(107,'ORD-KD-20260904095646-0107','Pelanggan Kedai','','Cash','Kedai',NULL,'Selesai',15000.00,20000.00,5000.00,98,'2026-09-04 09:56:46','2026-09-04 02:56:46','2026-09-07 03:02:30'),(108,'ORD-20260828095646-0108','Rafi','628000795591','Cash','QR Menu',NULL,'Selesai',15000.00,20000.00,5000.00,99,'2026-08-28 09:56:46','2026-08-28 02:56:46','2026-09-20 11:41:45'),(109,'ORD-20260908034445','Erga','628000573332','Cash','QR Menu','','Diproses',28000.00,0.00,0.00,NULL,NULL,'2026-09-07 20:44:45','2026-09-20 11:41:45'),(110,'ORD-KD-20260909065412-4379','Pelanggan Kedai','','Cash','Kedai','','Selesai',15000.00,20000.00,5000.00,101,'2026-09-09 06:54:15','2026-09-08 23:54:12','2026-09-08 23:54:15'),(111,'ORD-KD-20260919190405-40B1','Pelanggan Kedai','','Cash','Kedai','','Selesai',45000.00,50000.00,5000.00,102,'2026-09-19 19:04:16','2026-09-19 12:04:05','2026-09-19 12:04:16'),(112,'ORD-KD-20260919190516-6CDD','Pelanggan Kedai','','QRIS','Kedai','','Selesai',29000.00,29000.00,0.00,103,'2026-09-19 19:06:20','2026-09-19 12:05:16','2026-09-19 12:06:20');
/*!40000 ALTER TABLE `pesanan` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `pesanan_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pesanan_detail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pesanan_id` int(11) NOT NULL,
  `menu_id` int(11) NOT NULL,
  `nama_menu` varchar(160) NOT NULL,
  `harga_satuan` decimal(12,2) NOT NULL DEFAULT 0.00,
  `qty` int(11) NOT NULL DEFAULT 1,
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `pesanan_id` (`pesanan_id`),
  CONSTRAINT `fk_pesanan_detail_pesanan` FOREIGN KEY (`pesanan_id`) REFERENCES `pesanan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=182 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `pesanan_detail` WRITE;
/*!40000 ALTER TABLE `pesanan_detail` DISABLE KEYS */;
INSERT INTO `pesanan_detail` VALUES (116,58,26,'Ice Black',13000.00,1,13000.00,'2026-08-03 03:15:00'),(117,59,27,'Berry Party',15000.00,1,15000.00,'2026-08-10 07:20:00'),(118,60,31,'Ice White',16000.00,1,16000.00,'2026-08-13 04:30:00'),(119,61,32,'Ice Brown',17000.00,1,17000.00,'2026-08-13 05:53:59'),(120,62,28,'Sunshine',16000.00,1,16000.00,'2026-07-18 02:20:00'),(121,63,29,'Manual Brew',23000.00,1,23000.00,'2026-07-20 06:10:00'),(122,64,35,'Korean Berry',15000.00,1,15000.00,'2026-07-22 08:35:00'),(123,65,36,'Chocolate',15000.00,1,15000.00,'2026-07-24 04:05:00'),(124,66,37,'Matcha Lattea',18000.00,1,18000.00,'2026-07-26 09:40:00'),(125,67,30,'KoSu Sahabat',13000.00,1,13000.00,'2026-07-28 03:50:00'),(126,68,34,'Berry Shine T',10000.00,1,10000.00,'2026-07-30 07:25:00'),(127,69,26,'Ice Black',13000.00,1,13000.00,'2026-07-31 10:15:00'),(128,70,27,'Berry Party',15000.00,1,15000.00,'2026-08-01 02:45:00'),(129,71,32,'Ice Brown',17000.00,1,17000.00,'2026-08-02 05:30:00'),(130,72,31,'Ice White',16000.00,1,16000.00,'2026-08-04 08:10:00'),(131,73,28,'Sunshine',16000.00,1,16000.00,'2026-08-05 03:20:00'),(132,74,36,'Chocolate',15000.00,1,15000.00,'2026-08-06 06:50:00'),(133,75,29,'Manual Brew',23000.00,1,23000.00,'2026-08-07 09:05:00'),(134,76,37,'Matcha Lattea',18000.00,1,18000.00,'2026-08-08 04:40:00'),(135,77,30,'KoSu Sahabat',13000.00,1,13000.00,'2026-08-12 07:55:00'),(151,93,26,'Ice Black',13000.00,1,13000.00,'2026-08-13 02:10:00'),(152,94,27,'Berry Party',15000.00,1,15000.00,'2026-08-13 03:35:00'),(153,95,29,'Manual Brew',23000.00,1,23000.00,'2026-08-11 06:45:00'),(154,96,36,'Chocolate',15000.00,1,15000.00,'2026-08-12 08:20:00'),(155,97,37,'Matcha Lattea',18000.00,1,18000.00,'2026-08-09 09:05:00'),(158,100,32,'Ice Brown',17000.00,1,17000.00,'2026-08-13 09:50:24'),(159,101,26,'Ice Black',13000.00,1,13000.00,'2026-08-13 14:51:56'),(160,101,27,'Berry Party',15000.00,1,15000.00,'2026-08-13 14:51:56'),(161,101,31,'Ice White',16000.00,1,16000.00,'2026-08-13 14:51:56'),(162,102,29,'Manual Brew',23000.00,1,23000.00,'2026-08-13 14:56:48'),(163,102,30,'KoSu Sahabat',13000.00,1,13000.00,'2026-08-13 14:56:48'),(164,102,31,'Ice White',16000.00,1,16000.00,'2026-08-13 14:56:48'),(165,102,32,'Ice Brown',17000.00,1,17000.00,'2026-08-13 14:56:48'),(166,102,26,'Ice Black',13000.00,1,13000.00,'2026-08-13 14:56:48'),(167,102,27,'Berry Party',15000.00,1,15000.00,'2026-08-13 14:56:48'),(168,103,29,'Manual Brew',23000.00,1,23000.00,'2026-09-06 15:29:04'),(169,104,28,'Sunshine',16000.00,1,16000.00,'2026-09-07 02:56:46'),(170,105,29,'Manual Brew',23000.00,1,23000.00,'2026-09-07 02:56:46'),(171,106,34,'Berry Shine T',10000.00,2,20000.00,'2026-09-07 02:56:46'),(172,107,35,'Korean Berry',15000.00,1,15000.00,'2026-09-07 02:56:46'),(173,108,27,'Berry Party',15000.00,1,15000.00,'2026-09-07 02:56:46'),(174,109,26,'Ice Black',13000.00,1,13000.00,'2026-09-07 20:44:45'),(175,109,27,'Berry Party',15000.00,1,15000.00,'2026-09-07 20:44:45'),(176,110,27,'Berry Party',15000.00,1,15000.00,'2026-09-08 23:54:12'),(177,111,27,'Berry Party',15000.00,1,15000.00,'2026-09-19 12:04:05'),(178,111,26,'Ice Black',13000.00,1,13000.00,'2026-09-19 12:04:05'),(179,111,32,'Ice Brown',17000.00,1,17000.00,'2026-09-19 12:04:05'),(180,112,31,'Ice White',16000.00,1,16000.00,'2026-09-19 12:05:16'),(181,112,30,'KoSu Sahabat',13000.00,1,13000.00,'2026-09-19 12:05:16');
/*!40000 ALTER TABLE `pesanan_detail` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `pesanan_supplier`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pesanan_supplier` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `permintaan_stok_id` int(11) DEFAULT NULL,
  `bahan_id` int(11) NOT NULL,
  `is_bahan_baru` tinyint(1) NOT NULL DEFAULT 0,
  `nama_bahan_baru` varchar(120) DEFAULT NULL,
  `satuan_baru` varchar(20) DEFAULT NULL,
  `minimum_stok_baru` decimal(12,2) DEFAULT NULL,
  `supplier_id` int(11) NOT NULL,
  `jumlah` decimal(12,2) NOT NULL,
  `jumlah_input` decimal(12,2) DEFAULT NULL,
  `satuan_input` varchar(20) DEFAULT NULL,
  `isi_per_kemasan` decimal(12,2) DEFAULT NULL,
  `satuan` varchar(20) NOT NULL,
  `total_pembelian` decimal(14,2) NOT NULL DEFAULT 0.00,
  `foto_nota` varchar(255) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'Sudah Dipesan',
  `tanggal_pesan` datetime NOT NULL DEFAULT current_timestamp(),
  `tanggal_diterima` datetime DEFAULT NULL,
  `kode_pesanan` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pesanan_supplier_bahan_status` (`bahan_id`,`status`),
  KEY `idx_kode_pesanan` (`kode_pesanan`)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `pesanan_supplier` WRITE;
/*!40000 ALTER TABLE `pesanan_supplier` DISABLE KEYS */;
INSERT INTO `pesanan_supplier` VALUES (1,4,94,0,NULL,NULL,NULL,14,60.00,2.00,'pack',30.00,'pcs',50000.00,'nota-1-6294027833166fde.png','Diterima','2026-09-06 14:43:13','2026-09-06 14:44:56','PB-20260906-0001'),(2,NULL,92,0,NULL,NULL,NULL,10,500.00,500.00,'g',0.00,'g',50000.00,'nota-92-dcaf19f230416d10.png','Diterima','2026-09-06 19:52:45','2026-09-06 20:51:55','PB-20260906-0002'),(3,NULL,94,0,NULL,NULL,NULL,14,100.00,1.00,'pack',100.00,'pcs',45000.00,NULL,'Sudah Dipesan','2026-09-07 09:56:46',NULL,'PB-20260907-0003'),(4,NULL,95,0,NULL,NULL,NULL,14,100.00,1.00,'pack',100.00,'pcs',15000.00,NULL,'Sudah Dipesan','2026-09-07 09:56:46',NULL,'PB-20260907-0003'),(5,NULL,93,0,NULL,NULL,NULL,11,25.00,1.00,'botol',25.00,'ml',50000.00,'nota-5-1ecdfa4d1c8f344b.jpg','Diterima','2026-09-08 11:27:10','2026-09-08 11:28:03','PB-20260908-0004'),(6,NULL,78,0,NULL,NULL,NULL,9,1000.00,1.00,'kg',0.00,'g',50000.00,NULL,'Sudah Dipesan','2026-09-09 06:58:22',NULL,'PB-20260909-0005'),(7,NULL,79,0,NULL,NULL,NULL,9,1000.00,1.00,'kg',0.00,'g',50000.00,NULL,'Sudah Dipesan','2026-09-09 06:58:22',NULL,'PB-20260909-0005'),(8,NULL,91,0,NULL,NULL,NULL,10,500.00,500.00,'g',0.00,'g',60000.00,NULL,'Diterima','2026-09-09 06:58:57','2026-09-09 06:59:57','PB-20260909-0006'),(9,NULL,85,0,NULL,NULL,NULL,11,1750.00,5.00,'botol',350.00,'ml',75000.00,NULL,'Diterima','2026-09-09 08:52:29','2026-09-09 08:53:43','PB-20260909-0007'),(10,NULL,84,0,NULL,NULL,NULL,11,500.00,1.00,'pack',500.00,'g',25000.00,NULL,'Diterima','2026-09-09 08:52:29','2026-09-09 08:54:22','PB-20260909-0007'),(11,NULL,90,0,NULL,NULL,NULL,10,6000.00,6.00,'kg',0.00,'g',36000.00,NULL,'Sudah Dipesan','2026-09-10 04:49:42',NULL,'PB-20260910-0008'),(12,NULL,83,0,NULL,NULL,NULL,10,14000.00,14.00,'kg',0.00,'g',311108.00,NULL,'Sudah Dipesan','2026-09-10 04:49:42',NULL,'PB-20260910-0008'),(13,NULL,84,0,NULL,NULL,NULL,11,2600.00,5.00,'pack',520.00,'g',250000.00,'nota-13-3fc940c9ad2d82fe.png','Diterima','2026-09-10 05:15:17','2026-09-10 05:17:04','PB-20260910-0009'),(14,NULL,86,0,NULL,NULL,NULL,11,5000.00,5.00,'botol',1000.00,'ml',125000.00,NULL,'Diterima','2026-09-10 05:15:17','2026-09-10 05:17:04','PB-20260910-0009'),(15,NULL,85,0,NULL,NULL,NULL,11,5000.00,5.00,'botol',1000.00,'ml',125000.00,NULL,'Diterima','2026-09-10 05:15:17','2026-09-10 05:17:04','PB-20260910-0009'),(16,NULL,114,1,NULL,NULL,NULL,11,5000.00,5.00,'botol',1000.00,'ml',125000.00,NULL,'Diterima','2026-09-10 05:15:17','2026-09-10 05:17:04','PB-20260910-0009'),(17,NULL,88,0,NULL,NULL,NULL,12,75000.00,5.00,'galon',15000.00,'ml',100000.00,NULL,'Diterima','2026-09-10 07:02:33','2026-09-10 07:03:13','PB-20260910-0018'),(18,NULL,87,0,NULL,NULL,NULL,11,3000.00,5.00,'botol',600.00,'ml',150000.00,'nota-18-19b2d4aae0e95acf.png','Diterima','2026-09-10 07:19:18','2026-09-10 07:25:00','PB-20260910-0019'),(19,NULL,85,0,NULL,NULL,NULL,11,12000.00,1.00,'dus',12000.00,'ml',450000.00,NULL,'Diterima','2026-09-10 07:19:18','2026-09-10 07:25:00','PB-20260910-0019'),(24,NULL,82,0,NULL,NULL,NULL,10,5000.00,5.00,'kg',0.00,'g',250000.00,NULL,'Sudah Dipesan','2026-09-13 11:54:35',NULL,'PB-20260913-0022'),(25,NULL,81,0,NULL,NULL,NULL,10,5000.00,5.00,'liter',0.00,'ml',10000.00,NULL,'Sudah Dipesan','2026-09-13 11:54:35',NULL,'PB-20260913-0022'),(26,NULL,80,0,NULL,NULL,NULL,10,6000.00,5.00,'botol',1200.00,'ml',100000.00,NULL,'Sudah Dipesan','2026-09-13 11:54:35',NULL,'PB-20260913-0022'),(27,NULL,92,0,NULL,NULL,NULL,10,5000.00,5.00,'kg',0.00,'g',102500.00,NULL,'Sudah Dipesan','2026-09-13 11:54:35',NULL,'PB-20260913-0022'),(28,NULL,115,1,NULL,NULL,NULL,10,6500.00,5.00,'botol',1300.00,'ml',115000.00,NULL,'Sudah Dipesan','2026-09-13 11:54:35',NULL,'PB-20260913-0022'),(29,NULL,86,0,NULL,NULL,NULL,11,2500.00,5.00,'botol',500.00,'ml',275000.00,NULL,'Diterima','2026-09-19 19:26:24','2026-09-19 19:27:23','PB-20260919-0023'),(30,NULL,85,0,NULL,NULL,NULL,11,2500.00,5.00,'botol',500.00,'ml',250000.00,NULL,'Diterima','2026-09-19 19:26:24','2026-09-19 19:27:23','PB-20260919-0023'),(31,NULL,116,1,NULL,NULL,NULL,11,2500.00,5.00,'botol',500.00,'ml',175000.00,NULL,'Diterima','2026-09-19 19:26:24','2026-09-19 19:27:23','PB-20260919-0023');
/*!40000 ALTER TABLE `pesanan_supplier` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `riwayat_keluar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `riwayat_keluar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bahan_id` int(11) NOT NULL,
  `nama_bahan` varchar(150) NOT NULL,
  `jumlah_keluar` decimal(12,2) NOT NULL,
  `satuan` varchar(50) NOT NULL,
  `total_setelah` decimal(12,2) NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `tanggal` timestamp NOT NULL DEFAULT current_timestamp(),
  `jumlah_input` decimal(12,2) DEFAULT NULL,
  `satuan_input` varchar(20) DEFAULT NULL,
  `isi_per_kemasan` decimal(12,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_riwayat_keluar_bahan` (`bahan_id`),
  CONSTRAINT `fk_riwayat_keluar_bahan` FOREIGN KEY (`bahan_id`) REFERENCES `bahan_baku` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=528 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `riwayat_keluar` WRITE;
/*!40000 ALTER TABLE `riwayat_keluar` DISABLE KEYS */;
INSERT INTO `riwayat_keluar` VALUES (263,78,'Kopi Arabica',8.00,'g',918.00,'Ice Black','2026-08-03 03:15:00',NULL,NULL,NULL),(264,79,'Kopi Robusta',10.00,'g',962.00,'Ice Black','2026-08-03 03:15:00',NULL,NULL,NULL),(265,88,'Air Mineral',150.00,'ml',18040.00,'Ice Black','2026-08-03 03:15:00',NULL,NULL,NULL),(266,89,'Es Batu',120.00,'g',8900.00,'Ice Black','2026-08-03 03:15:00',NULL,NULL,NULL),(267,94,'Cup',1.00,'pcs',89.00,'Ice Black','2026-08-03 03:15:00',NULL,NULL,NULL),(268,95,'Sedotan',1.00,'pcs',89.00,'Ice Black','2026-08-03 03:15:00',NULL,NULL,NULL),(269,96,'Plastik Takeaway',1.00,'pcs',89.00,'Ice Black','2026-08-03 03:15:00',NULL,NULL,NULL),(270,78,'Kopi Arabica',15.00,'g',860.00,'Berry Party','2026-08-10 07:20:00',NULL,NULL,NULL),(271,85,'Sirup Berry',20.00,'ml',1320.00,'Berry Party','2026-08-10 07:20:00',NULL,NULL,NULL),(272,88,'Air Mineral',130.00,'ml',17530.00,'Berry Party','2026-08-10 07:20:00',NULL,NULL,NULL),(273,89,'Es Batu',120.00,'g',8360.00,'Berry Party','2026-08-10 07:20:00',NULL,NULL,NULL),(274,94,'Cup',1.00,'pcs',83.00,'Berry Party','2026-08-10 07:20:00',NULL,NULL,NULL),(275,95,'Sedotan',1.00,'pcs',83.00,'Berry Party','2026-08-10 07:20:00',NULL,NULL,NULL),(276,96,'Plastik Takeaway',1.00,'pcs',83.00,'Berry Party','2026-08-10 07:20:00',NULL,NULL,NULL),(277,78,'Kopi Arabica',10.00,'g',842.00,'Ice White','2026-08-13 04:30:00',NULL,NULL,NULL),(278,79,'Kopi Robusta',8.00,'g',936.00,'Ice White','2026-08-13 04:30:00',NULL,NULL,NULL),(279,80,'Susu UHT',150.00,'ml',4530.00,'Ice White','2026-08-13 04:30:00',NULL,NULL,NULL),(280,89,'Es Batu',100.00,'g',8160.00,'Ice White','2026-08-13 04:30:00',NULL,NULL,NULL),(281,94,'Cup',1.00,'pcs',81.00,'Ice White','2026-08-13 04:30:00',NULL,NULL,NULL),(282,95,'Sedotan',1.00,'pcs',81.00,'Ice White','2026-08-13 04:30:00',NULL,NULL,NULL),(283,96,'Plastik Takeaway',1.00,'pcs',81.00,'Ice White','2026-08-13 04:30:00',NULL,NULL,NULL),(294,78,'Kopi Arabica',10.00,'g',832.00,'Ice Brown','2026-08-13 05:54:02',NULL,NULL,NULL),(295,79,'Kopi Robusta',8.00,'g',928.00,'Ice Brown','2026-08-13 05:54:02',NULL,NULL,NULL),(296,80,'Susu UHT',120.00,'ml',4410.00,'Ice Brown','2026-08-13 05:54:02',NULL,NULL,NULL),(297,81,'Gula Aren',20.00,'ml',2930.00,'Ice Brown','2026-08-13 05:54:02',NULL,NULL,NULL),(298,89,'Es Batu',100.00,'g',8060.00,'Ice Brown','2026-08-13 05:54:02',NULL,NULL,NULL),(299,94,'Cup',1.00,'pcs',80.00,'Ice Brown','2026-08-13 05:54:02',NULL,NULL,NULL),(300,95,'Sedotan',1.00,'pcs',80.00,'Ice Brown','2026-08-13 05:54:02',NULL,NULL,NULL),(301,96,'Plastik Takeaway',1.00,'pcs',80.00,'Ice Brown','2026-08-13 05:54:02',NULL,NULL,NULL),(302,78,'Kopi Arabica',15.00,'g',985.00,'Sunshine','2026-07-18 02:20:00',NULL,NULL,NULL),(303,86,'Sirup Peach',20.00,'ml',1380.00,'Sunshine','2026-07-18 02:20:00',NULL,NULL,NULL),(304,88,'Air Mineral',130.00,'ml',18870.00,'Sunshine','2026-07-18 02:20:00',NULL,NULL,NULL),(305,89,'Es Batu',120.00,'g',9880.00,'Sunshine','2026-07-18 02:20:00',NULL,NULL,NULL),(306,94,'Cup',1.00,'pcs',99.00,'Sunshine','2026-07-18 02:20:00',NULL,NULL,NULL),(307,95,'Sedotan',1.00,'pcs',99.00,'Sunshine','2026-07-18 02:20:00',NULL,NULL,NULL),(308,96,'Plastik Takeaway',1.00,'pcs',99.00,'Sunshine','2026-07-18 02:20:00',NULL,NULL,NULL),(309,78,'Kopi Arabica',18.00,'g',967.00,'Manual Brew','2026-07-20 06:10:00',NULL,NULL,NULL),(310,88,'Air Mineral',250.00,'ml',18620.00,'Manual Brew','2026-07-20 06:10:00',NULL,NULL,NULL),(311,94,'Cup',1.00,'pcs',98.00,'Manual Brew','2026-07-20 06:10:00',NULL,NULL,NULL),(312,95,'Sedotan',1.00,'pcs',98.00,'Manual Brew','2026-07-20 06:10:00',NULL,NULL,NULL),(313,96,'Plastik Takeaway',1.00,'pcs',98.00,'Manual Brew','2026-07-20 06:10:00',NULL,NULL,NULL),(314,80,'Susu UHT',150.00,'ml',5850.00,'Korean Berry','2026-07-22 08:35:00',NULL,NULL,NULL),(315,85,'Sirup Berry',15.00,'ml',1385.00,'Korean Berry','2026-07-22 08:35:00',NULL,NULL,NULL),(316,93,'Strawberry Sauce',20.00,'ml',980.00,'Korean Berry','2026-07-22 08:35:00',NULL,NULL,NULL),(317,89,'Es Batu',100.00,'g',9780.00,'Korean Berry','2026-07-22 08:35:00',NULL,NULL,NULL),(318,94,'Cup',1.00,'pcs',97.00,'Korean Berry','2026-07-22 08:35:00',NULL,NULL,NULL),(319,95,'Sedotan',1.00,'pcs',97.00,'Korean Berry','2026-07-22 08:35:00',NULL,NULL,NULL),(320,96,'Plastik Takeaway',1.00,'pcs',97.00,'Korean Berry','2026-07-22 08:35:00',NULL,NULL,NULL),(321,82,'Bubuk Cokelat',20.00,'g',980.00,'Chocolate','2026-07-24 04:05:00',NULL,NULL,NULL),(322,80,'Susu UHT',150.00,'ml',5700.00,'Chocolate','2026-07-24 04:05:00',NULL,NULL,NULL),(323,84,'Krimer',10.00,'g',990.00,'Chocolate','2026-07-24 04:05:00',NULL,NULL,NULL),(324,89,'Es Batu',100.00,'g',9680.00,'Chocolate','2026-07-24 04:05:00',NULL,NULL,NULL),(325,94,'Cup',1.00,'pcs',96.00,'Chocolate','2026-07-24 04:05:00',NULL,NULL,NULL),(326,95,'Sedotan',1.00,'pcs',96.00,'Chocolate','2026-07-24 04:05:00',NULL,NULL,NULL),(327,96,'Plastik Takeaway',1.00,'pcs',96.00,'Chocolate','2026-07-24 04:05:00',NULL,NULL,NULL),(328,83,'Bubuk Matcha',15.00,'g',485.00,'Matcha Lattea','2026-07-26 09:40:00',NULL,NULL,NULL),(329,80,'Susu UHT',150.00,'ml',5550.00,'Matcha Lattea','2026-07-26 09:40:00',NULL,NULL,NULL),(330,84,'Krimer',10.00,'g',980.00,'Matcha Lattea','2026-07-26 09:40:00',NULL,NULL,NULL),(331,91,'Cheesy Foam',15.00,'g',985.00,'Matcha Lattea','2026-07-26 09:40:00',NULL,NULL,NULL),(332,89,'Es Batu',100.00,'g',9580.00,'Matcha Lattea','2026-07-26 09:40:00',NULL,NULL,NULL),(333,94,'Cup',1.00,'pcs',95.00,'Matcha Lattea','2026-07-26 09:40:00',NULL,NULL,NULL),(334,95,'Sedotan',1.00,'pcs',95.00,'Matcha Lattea','2026-07-26 09:40:00',NULL,NULL,NULL),(335,96,'Plastik Takeaway',1.00,'pcs',95.00,'Matcha Lattea','2026-07-26 09:40:00',NULL,NULL,NULL),(336,79,'Kopi Robusta',10.00,'g',990.00,'KoSu Sahabat','2026-07-28 03:50:00',NULL,NULL,NULL),(337,78,'Kopi Arabica',8.00,'g',959.00,'KoSu Sahabat','2026-07-28 03:50:00',NULL,NULL,NULL),(338,80,'Susu UHT',150.00,'ml',5400.00,'KoSu Sahabat','2026-07-28 03:50:00',NULL,NULL,NULL),(339,81,'Gula Aren',15.00,'ml',2985.00,'KoSu Sahabat','2026-07-28 03:50:00',NULL,NULL,NULL),(340,89,'Es Batu',100.00,'g',9480.00,'KoSu Sahabat','2026-07-28 03:50:00',NULL,NULL,NULL),(341,94,'Cup',1.00,'pcs',94.00,'KoSu Sahabat','2026-07-28 03:50:00',NULL,NULL,NULL),(342,95,'Sedotan',1.00,'pcs',94.00,'KoSu Sahabat','2026-07-28 03:50:00',NULL,NULL,NULL),(343,96,'Plastik Takeaway',1.00,'pcs',94.00,'KoSu Sahabat','2026-07-28 03:50:00',NULL,NULL,NULL),(344,85,'Sirup Berry',25.00,'ml',1360.00,'Berry Shine T','2026-07-30 07:25:00',NULL,NULL,NULL),(345,88,'Air Mineral',150.00,'ml',18470.00,'Berry Shine T','2026-07-30 07:25:00',NULL,NULL,NULL),(346,89,'Es Batu',120.00,'g',9360.00,'Berry Shine T','2026-07-30 07:25:00',NULL,NULL,NULL),(347,94,'Cup',1.00,'pcs',93.00,'Berry Shine T','2026-07-30 07:25:00',NULL,NULL,NULL),(348,95,'Sedotan',1.00,'pcs',93.00,'Berry Shine T','2026-07-30 07:25:00',NULL,NULL,NULL),(349,96,'Plastik Takeaway',1.00,'pcs',93.00,'Berry Shine T','2026-07-30 07:25:00',NULL,NULL,NULL),(350,79,'Kopi Robusta',10.00,'g',980.00,'Ice Black','2026-07-31 10:15:00',NULL,NULL,NULL),(351,78,'Kopi Arabica',8.00,'g',951.00,'Ice Black','2026-07-31 10:15:00',NULL,NULL,NULL),(352,88,'Air Mineral',150.00,'ml',18320.00,'Ice Black','2026-07-31 10:15:00',NULL,NULL,NULL),(353,89,'Es Batu',120.00,'g',9240.00,'Ice Black','2026-07-31 10:15:00',NULL,NULL,NULL),(354,94,'Cup',1.00,'pcs',92.00,'Ice Black','2026-07-31 10:15:00',NULL,NULL,NULL),(355,95,'Sedotan',1.00,'pcs',92.00,'Ice Black','2026-07-31 10:15:00',NULL,NULL,NULL),(356,96,'Plastik Takeaway',1.00,'pcs',92.00,'Ice Black','2026-07-31 10:15:00',NULL,NULL,NULL),(357,78,'Kopi Arabica',15.00,'g',936.00,'Berry Party','2026-08-01 02:45:00',NULL,NULL,NULL),(358,85,'Sirup Berry',20.00,'ml',1340.00,'Berry Party','2026-08-01 02:45:00',NULL,NULL,NULL),(359,88,'Air Mineral',130.00,'ml',18190.00,'Berry Party','2026-08-01 02:45:00',NULL,NULL,NULL),(360,89,'Es Batu',120.00,'g',9120.00,'Berry Party','2026-08-01 02:45:00',NULL,NULL,NULL),(361,94,'Cup',1.00,'pcs',91.00,'Berry Party','2026-08-01 02:45:00',NULL,NULL,NULL),(362,95,'Sedotan',1.00,'pcs',91.00,'Berry Party','2026-08-01 02:45:00',NULL,NULL,NULL),(363,96,'Plastik Takeaway',1.00,'pcs',91.00,'Berry Party','2026-08-01 02:45:00',NULL,NULL,NULL),(364,78,'Kopi Arabica',10.00,'g',926.00,'Ice Brown','2026-08-02 05:30:00',NULL,NULL,NULL),(365,79,'Kopi Robusta',8.00,'g',972.00,'Ice Brown','2026-08-02 05:30:00',NULL,NULL,NULL),(366,80,'Susu UHT',120.00,'ml',5280.00,'Ice Brown','2026-08-02 05:30:00',NULL,NULL,NULL),(367,81,'Gula Aren',20.00,'ml',2965.00,'Ice Brown','2026-08-02 05:30:00',NULL,NULL,NULL),(368,89,'Es Batu',100.00,'g',9020.00,'Ice Brown','2026-08-02 05:30:00',NULL,NULL,NULL),(369,94,'Cup',1.00,'pcs',90.00,'Ice Brown','2026-08-02 05:30:00',NULL,NULL,NULL),(370,95,'Sedotan',1.00,'pcs',90.00,'Ice Brown','2026-08-02 05:30:00',NULL,NULL,NULL),(371,96,'Plastik Takeaway',1.00,'pcs',90.00,'Ice Brown','2026-08-02 05:30:00',NULL,NULL,NULL),(372,78,'Kopi Arabica',10.00,'g',908.00,'Ice White','2026-08-04 08:10:00',NULL,NULL,NULL),(373,79,'Kopi Robusta',8.00,'g',954.00,'Ice White','2026-08-04 08:10:00',NULL,NULL,NULL),(374,80,'Susu UHT',150.00,'ml',5130.00,'Ice White','2026-08-04 08:10:00',NULL,NULL,NULL),(375,89,'Es Batu',100.00,'g',8800.00,'Ice White','2026-08-04 08:10:00',NULL,NULL,NULL),(376,94,'Cup',1.00,'pcs',88.00,'Ice White','2026-08-04 08:10:00',NULL,NULL,NULL),(377,95,'Sedotan',1.00,'pcs',88.00,'Ice White','2026-08-04 08:10:00',NULL,NULL,NULL),(378,96,'Plastik Takeaway',1.00,'pcs',88.00,'Ice White','2026-08-04 08:10:00',NULL,NULL,NULL),(379,78,'Kopi Arabica',15.00,'g',893.00,'Sunshine','2026-08-05 03:20:00',NULL,NULL,NULL),(380,86,'Sirup Peach',20.00,'ml',1360.00,'Sunshine','2026-08-05 03:20:00',NULL,NULL,NULL),(381,88,'Air Mineral',130.00,'ml',17910.00,'Sunshine','2026-08-05 03:20:00',NULL,NULL,NULL),(382,89,'Es Batu',120.00,'g',8680.00,'Sunshine','2026-08-05 03:20:00',NULL,NULL,NULL),(383,94,'Cup',1.00,'pcs',87.00,'Sunshine','2026-08-05 03:20:00',NULL,NULL,NULL),(384,95,'Sedotan',1.00,'pcs',87.00,'Sunshine','2026-08-05 03:20:00',NULL,NULL,NULL),(385,96,'Plastik Takeaway',1.00,'pcs',87.00,'Sunshine','2026-08-05 03:20:00',NULL,NULL,NULL),(386,82,'Bubuk Cokelat',20.00,'g',960.00,'Chocolate','2026-08-06 06:50:00',NULL,NULL,NULL),(387,80,'Susu UHT',150.00,'ml',4980.00,'Chocolate','2026-08-06 06:50:00',NULL,NULL,NULL),(388,84,'Krimer',10.00,'g',970.00,'Chocolate','2026-08-06 06:50:00',NULL,NULL,NULL),(389,89,'Es Batu',100.00,'g',8580.00,'Chocolate','2026-08-06 06:50:00',NULL,NULL,NULL),(390,94,'Cup',1.00,'pcs',86.00,'Chocolate','2026-08-06 06:50:00',NULL,NULL,NULL),(391,95,'Sedotan',1.00,'pcs',86.00,'Chocolate','2026-08-06 06:50:00',NULL,NULL,NULL),(392,96,'Plastik Takeaway',1.00,'pcs',86.00,'Chocolate','2026-08-06 06:50:00',NULL,NULL,NULL),(393,78,'Kopi Arabica',18.00,'g',875.00,'Manual Brew','2026-08-07 09:05:00',NULL,NULL,NULL),(394,88,'Air Mineral',250.00,'ml',17660.00,'Manual Brew','2026-08-07 09:05:00',NULL,NULL,NULL),(395,94,'Cup',1.00,'pcs',85.00,'Manual Brew','2026-08-07 09:05:00',NULL,NULL,NULL),(396,95,'Sedotan',1.00,'pcs',85.00,'Manual Brew','2026-08-07 09:05:00',NULL,NULL,NULL),(397,96,'Plastik Takeaway',1.00,'pcs',85.00,'Manual Brew','2026-08-07 09:05:00',NULL,NULL,NULL),(398,83,'Bubuk Matcha',15.00,'g',470.00,'Matcha Lattea','2026-08-08 04:40:00',NULL,NULL,NULL),(399,80,'Susu UHT',150.00,'ml',4830.00,'Matcha Lattea','2026-08-08 04:40:00',NULL,NULL,NULL),(400,84,'Krimer',10.00,'g',960.00,'Matcha Lattea','2026-08-08 04:40:00',NULL,NULL,NULL),(401,91,'Cheesy Foam',15.00,'g',970.00,'Matcha Lattea','2026-08-08 04:40:00',NULL,NULL,NULL),(402,89,'Es Batu',100.00,'g',8480.00,'Matcha Lattea','2026-08-08 04:40:00',NULL,NULL,NULL),(403,94,'Cup',1.00,'pcs',84.00,'Matcha Lattea','2026-08-08 04:40:00',NULL,NULL,NULL),(404,95,'Sedotan',1.00,'pcs',84.00,'Matcha Lattea','2026-08-08 04:40:00',NULL,NULL,NULL),(405,96,'Plastik Takeaway',1.00,'pcs',84.00,'Matcha Lattea','2026-08-08 04:40:00',NULL,NULL,NULL),(406,79,'Kopi Robusta',10.00,'g',944.00,'KoSu Sahabat','2026-08-12 07:55:00',NULL,NULL,NULL),(407,78,'Kopi Arabica',8.00,'g',852.00,'KoSu Sahabat','2026-08-12 07:55:00',NULL,NULL,NULL),(408,80,'Susu UHT',150.00,'ml',4680.00,'KoSu Sahabat','2026-08-12 07:55:00',NULL,NULL,NULL),(409,81,'Gula Aren',15.00,'ml',2950.00,'KoSu Sahabat','2026-08-12 07:55:00',NULL,NULL,NULL),(410,89,'Es Batu',100.00,'g',8260.00,'KoSu Sahabat','2026-08-12 07:55:00',NULL,NULL,NULL),(411,94,'Cup',1.00,'pcs',82.00,'KoSu Sahabat','2026-08-12 07:55:00',NULL,NULL,NULL),(412,95,'Sedotan',1.00,'pcs',82.00,'KoSu Sahabat','2026-08-12 07:55:00',NULL,NULL,NULL),(413,96,'Plastik Takeaway',1.00,'pcs',82.00,'KoSu Sahabat','2026-08-12 07:55:00',NULL,NULL,NULL),(429,94,'Cup',10.00,'pcs',70.00,'Stok Rusak','2026-08-13 09:49:06',NULL,NULL,NULL),(430,78,'Kopi Arabica',10.00,'g',822.00,'Ice Brown','2026-08-13 09:50:33',NULL,NULL,NULL),(431,79,'Kopi Robusta',8.00,'g',920.00,'Ice Brown','2026-08-13 09:50:33',NULL,NULL,NULL),(432,80,'Susu UHT',120.00,'ml',4290.00,'Ice Brown','2026-08-13 09:50:33',NULL,NULL,NULL),(433,81,'Gula Aren',20.00,'ml',2910.00,'Ice Brown','2026-08-13 09:50:33',NULL,NULL,NULL),(434,89,'Es Batu',100.00,'g',7960.00,'Ice Brown','2026-08-13 09:50:33',NULL,NULL,NULL),(435,94,'Cup',1.00,'pcs',69.00,'Ice Brown','2026-08-13 09:50:33',NULL,NULL,NULL),(436,95,'Sedotan',1.00,'pcs',79.00,'Ice Brown','2026-08-13 09:50:33',NULL,NULL,NULL),(437,96,'Plastik Takeaway',1.00,'pcs',79.00,'Ice Brown','2026-08-13 09:50:33',NULL,NULL,NULL),(438,88,'Air Mineral',530.00,'ml',17000.00,'Stok Rusak','2026-09-05 14:01:19',NULL,NULL,NULL),(439,85,'Sirup Berry',50.00,'ml',1270.00,'Berry Shine T','2026-09-07 02:56:46',NULL,NULL,NULL),(440,88,'Air Mineral',300.00,'ml',16700.00,'Berry Shine T','2026-09-07 02:56:46',NULL,NULL,NULL),(441,89,'Es Batu',240.00,'g',7720.00,'Berry Shine T','2026-09-07 02:56:46',NULL,NULL,NULL),(442,94,'Cup',2.00,'pcs',127.00,'Berry Shine T','2026-09-07 02:56:46',NULL,NULL,NULL),(443,95,'Sedotan',2.00,'pcs',77.00,'Berry Shine T','2026-09-07 02:56:46',NULL,NULL,NULL),(444,96,'Plastik Takeaway',2.00,'pcs',77.00,'Berry Shine T','2026-09-07 02:56:46',NULL,NULL,NULL),(445,80,'Susu UHT',150.00,'ml',4140.00,'Korean Berry','2026-09-04 02:56:46',NULL,NULL,NULL),(446,85,'Sirup Berry',15.00,'ml',1255.00,'Korean Berry','2026-09-04 02:56:46',NULL,NULL,NULL),(447,89,'Es Batu',100.00,'g',7620.00,'Korean Berry','2026-09-04 02:56:46',NULL,NULL,NULL),(448,93,'Strawberry Sauce',20.00,'ml',960.00,'Korean Berry','2026-09-04 02:56:46',NULL,NULL,NULL),(449,94,'Cup',1.00,'pcs',126.00,'Korean Berry','2026-09-04 02:56:46',NULL,NULL,NULL),(450,95,'Sedotan',1.00,'pcs',76.00,'Korean Berry','2026-09-04 02:56:46',NULL,NULL,NULL),(451,96,'Plastik Takeaway',1.00,'pcs',76.00,'Korean Berry','2026-09-04 02:56:46',NULL,NULL,NULL),(452,78,'Kopi Arabica',15.00,'g',185.00,'Berry Party','2026-08-28 02:56:46',NULL,NULL,NULL),(453,85,'Sirup Berry',20.00,'ml',1235.00,'Berry Party','2026-08-28 02:56:46',NULL,NULL,NULL),(454,88,'Air Mineral',130.00,'ml',16570.00,'Berry Party','2026-08-28 02:56:46',NULL,NULL,NULL),(455,89,'Es Batu',120.00,'g',7500.00,'Berry Party','2026-08-28 02:56:46',NULL,NULL,NULL),(456,94,'Cup',1.00,'pcs',125.00,'Berry Party','2026-08-28 02:56:46',NULL,NULL,NULL),(457,95,'Sedotan',1.00,'pcs',75.00,'Berry Party','2026-08-28 02:56:46',NULL,NULL,NULL),(458,96,'Plastik Takeaway',1.00,'pcs',75.00,'Berry Party','2026-08-28 02:56:46',NULL,NULL,NULL),(459,78,'Kopi Arabica',15.00,'g',170.00,'Sunshine','2026-09-07 03:05:19',NULL,NULL,NULL),(460,86,'Sirup Peach',20.00,'ml',1340.00,'Sunshine','2026-09-07 03:05:19',NULL,NULL,NULL),(461,88,'Air Mineral',130.00,'ml',16440.00,'Sunshine','2026-09-07 03:05:19',NULL,NULL,NULL),(462,89,'Es Batu',120.00,'g',7380.00,'Sunshine','2026-09-07 03:05:19',NULL,NULL,NULL),(463,94,'Cup',1.00,'pcs',124.00,'Sunshine','2026-09-07 03:05:19',NULL,NULL,NULL),(464,95,'Sedotan',1.00,'pcs',74.00,'Sunshine','2026-09-07 03:05:19',NULL,NULL,NULL),(465,96,'Plastik Takeaway',1.00,'pcs',74.00,'Sunshine','2026-09-07 03:05:19',NULL,NULL,NULL),(466,78,'Kopi Arabica',15.00,'g',155.00,'Berry Party','2026-09-08 23:54:15',NULL,NULL,NULL),(467,85,'Sirup Berry',20.00,'ml',180.00,'Berry Party','2026-09-08 23:54:15',NULL,NULL,NULL),(468,88,'Air Mineral',130.00,'ml',16310.00,'Berry Party','2026-09-08 23:54:15',NULL,NULL,NULL),(469,89,'Es Batu',120.00,'g',7260.00,'Berry Party','2026-09-08 23:54:15',NULL,NULL,NULL),(470,94,'Cup',1.00,'pcs',123.00,'Berry Party','2026-09-08 23:54:15',NULL,NULL,NULL),(471,95,'Sedotan',1.00,'pcs',73.00,'Berry Party','2026-09-08 23:54:15',NULL,NULL,NULL),(472,96,'Plastik Takeaway',1.00,'pcs',73.00,'Berry Party','2026-09-08 23:54:15',NULL,NULL,NULL),(473,94,'Cup',5.00,'pcs',118.00,'Stok Rusak','2026-09-09 02:05:12',5.00,'pcs',0.00),(474,94,'Cup',18.00,'pcs',100.00,'Stok Rusak','2026-09-09 02:06:39',18.00,'pcs',0.00),(475,88,'Air Mineral',250.00,'ml',16060.00,'Stok Basi','2026-09-09 02:36:55',250.00,'ml',0.00),(476,89,'Es Batu',200.00,'g',7060.00,'Salah Input','2026-09-09 02:36:55',200.00,'g',0.00),(477,94,'Cup',10.00,'pcs',90.00,'Stok Rusak','2026-09-09 02:46:14',10.00,'pcs',0.00),(478,94,'Cup',3.00,'pcs',87.00,'Stok Rusak','2026-09-09 11:39:42',3.00,'pcs',0.00),(479,95,'Sedotan',5.00,'pcs',68.00,'Salah Input','2026-09-09 11:39:42',5.00,'pcs',0.00),(480,88,'Air Mineral',63200.00,'ml',15000.00,'Salah Input','2026-09-19 09:18:57',NULL,NULL,NULL),(481,89,'Es Batu',1060.00,'g',6000.00,'Salah Input','2026-09-19 09:18:57',NULL,NULL,NULL),(482,94,'Cup',27.00,'pcs',60.00,'Salah Input','2026-09-19 09:18:57',NULL,NULL,NULL),(483,95,'Sedotan',8.00,'pcs',60.00,'Salah Input','2026-09-19 09:18:57',NULL,NULL,NULL),(484,85,'Sirup Berry',16435.00,'ml',625.00,'Salah Input','2026-09-19 09:18:57',NULL,NULL,NULL),(485,86,'Sirup Peach',5180.00,'ml',0.00,'Salah Input','2026-09-19 09:18:57',NULL,NULL,NULL),(486,84,'Krimer',2090.00,'g',600.00,'Salah Input','2026-09-19 09:18:57',NULL,NULL,NULL),(487,90,'Cinnamon',4.00,'g',6.00,'Salah Input','2026-09-19 09:18:57',NULL,NULL,NULL),(488,91,'Cheesy Foam',100.00,'g',600.00,'Salah Input','2026-09-19 09:18:57',NULL,NULL,NULL),(489,87,'Sirup Butterscotch',2600.00,'ml',600.00,'Salah Input','2026-09-19 09:18:57',NULL,NULL,NULL),(490,92,'Caramel Crumble',202.00,'g',300.00,'Salah Input','2026-09-19 09:18:57',NULL,NULL,NULL),(491,78,'Kopi Arabica',15.00,'g',585.00,'Berry Party','2026-09-19 12:04:16',NULL,NULL,NULL),(492,85,'Sirup Berry',20.00,'ml',605.00,'Berry Party','2026-09-19 12:04:16',NULL,NULL,NULL),(493,88,'Air Mineral',130.00,'ml',14870.00,'Berry Party','2026-09-19 12:04:16',NULL,NULL,NULL),(494,89,'Es Batu',120.00,'g',5880.00,'Berry Party','2026-09-19 12:04:16',NULL,NULL,NULL),(495,94,'Cup',1.00,'pcs',59.00,'Berry Party','2026-09-19 12:04:16',NULL,NULL,NULL),(496,95,'Sedotan',1.00,'pcs',59.00,'Berry Party','2026-09-19 12:04:16',NULL,NULL,NULL),(497,96,'Plastik Takeaway',1.00,'pcs',59.00,'Berry Party','2026-09-19 12:04:16',NULL,NULL,NULL),(498,79,'Kopi Robusta',10.00,'g',590.00,'Ice Black','2026-09-19 12:04:16',NULL,NULL,NULL),(499,78,'Kopi Arabica',8.00,'g',577.00,'Ice Black','2026-09-19 12:04:16',NULL,NULL,NULL),(500,88,'Air Mineral',150.00,'ml',14720.00,'Ice Black','2026-09-19 12:04:16',NULL,NULL,NULL),(501,89,'Es Batu',120.00,'g',5760.00,'Ice Black','2026-09-19 12:04:16',NULL,NULL,NULL),(502,94,'Cup',1.00,'pcs',58.00,'Ice Black','2026-09-19 12:04:16',NULL,NULL,NULL),(503,95,'Sedotan',1.00,'pcs',58.00,'Ice Black','2026-09-19 12:04:16',NULL,NULL,NULL),(504,96,'Plastik Takeaway',1.00,'pcs',58.00,'Ice Black','2026-09-19 12:04:16',NULL,NULL,NULL),(505,78,'Kopi Arabica',10.00,'g',567.00,'Ice Brown','2026-09-19 12:04:16',NULL,NULL,NULL),(506,79,'Kopi Robusta',8.00,'g',582.00,'Ice Brown','2026-09-19 12:04:16',NULL,NULL,NULL),(507,80,'Susu UHT',120.00,'ml',3630.00,'Ice Brown','2026-09-19 12:04:16',NULL,NULL,NULL),(508,81,'Gula Aren',20.00,'ml',1480.00,'Ice Brown','2026-09-19 12:04:16',NULL,NULL,NULL),(509,89,'Es Batu',100.00,'g',5660.00,'Ice Brown','2026-09-19 12:04:16',NULL,NULL,NULL),(510,94,'Cup',1.00,'pcs',57.00,'Ice Brown','2026-09-19 12:04:16',NULL,NULL,NULL),(511,95,'Sedotan',1.00,'pcs',57.00,'Ice Brown','2026-09-19 12:04:16',NULL,NULL,NULL),(512,96,'Plastik Takeaway',1.00,'pcs',57.00,'Ice Brown','2026-09-19 12:04:16',NULL,NULL,NULL),(513,78,'Kopi Arabica',10.00,'g',557.00,'Ice White','2026-09-19 12:06:20',NULL,NULL,NULL),(514,79,'Kopi Robusta',8.00,'g',574.00,'Ice White','2026-09-19 12:06:20',NULL,NULL,NULL),(515,80,'Susu UHT',150.00,'ml',3480.00,'Ice White','2026-09-19 12:06:20',NULL,NULL,NULL),(516,89,'Es Batu',100.00,'g',5560.00,'Ice White','2026-09-19 12:06:20',NULL,NULL,NULL),(517,94,'Cup',1.00,'pcs',56.00,'Ice White','2026-09-19 12:06:20',NULL,NULL,NULL),(518,95,'Sedotan',1.00,'pcs',56.00,'Ice White','2026-09-19 12:06:20',NULL,NULL,NULL),(519,96,'Plastik Takeaway',1.00,'pcs',56.00,'Ice White','2026-09-19 12:06:20',NULL,NULL,NULL),(520,79,'Kopi Robusta',10.00,'g',564.00,'KoSu Sahabat','2026-09-19 12:06:20',NULL,NULL,NULL),(521,78,'Kopi Arabica',8.00,'g',549.00,'KoSu Sahabat','2026-09-19 12:06:20',NULL,NULL,NULL),(522,80,'Susu UHT',150.00,'ml',3330.00,'KoSu Sahabat','2026-09-19 12:06:20',NULL,NULL,NULL),(523,81,'Gula Aren',15.00,'ml',1465.00,'KoSu Sahabat','2026-09-19 12:06:20',NULL,NULL,NULL),(524,89,'Es Batu',100.00,'g',5460.00,'KoSu Sahabat','2026-09-19 12:06:20',NULL,NULL,NULL),(525,94,'Cup',1.00,'pcs',55.00,'KoSu Sahabat','2026-09-19 12:06:20',NULL,NULL,NULL),(526,95,'Sedotan',1.00,'pcs',55.00,'KoSu Sahabat','2026-09-19 12:06:20',NULL,NULL,NULL),(527,96,'Plastik Takeaway',1.00,'pcs',55.00,'KoSu Sahabat','2026-09-19 12:06:20',NULL,NULL,NULL);
/*!40000 ALTER TABLE `riwayat_keluar` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `riwayat_masuk`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `riwayat_masuk` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bahan_id` int(11) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `supplier_nama` varchar(120) DEFAULT NULL,
  `nama_bahan` varchar(100) NOT NULL,
  `jumlah_masuk` decimal(10,2) NOT NULL,
  `satuan` varchar(20) NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `tanggal` datetime DEFAULT current_timestamp(),
  `total_setelah` decimal(10,2) NOT NULL DEFAULT 0.00,
  `jumlah_input` decimal(12,2) DEFAULT NULL,
  `satuan_input` varchar(20) DEFAULT NULL,
  `isi_per_kemasan` decimal(12,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `bahan_id` (`bahan_id`),
  CONSTRAINT `riwayat_masuk_ibfk_1` FOREIGN KEY (`bahan_id`) REFERENCES `bahan_baku` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=144 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `riwayat_masuk` WRITE;
/*!40000 ALTER TABLE `riwayat_masuk` DISABLE KEYS */;
INSERT INTO `riwayat_masuk` VALUES (82,78,9,'Aja Coffee','Kopi Arabica',1000.00,'g','Stok Awal','2026-08-13 11:57:04',1000.00,NULL,NULL,NULL),(83,79,9,'Aja Coffee','Kopi Robusta',1000.00,'g','Stok Awal','2026-08-13 11:57:04',1000.00,NULL,NULL,NULL),(84,80,10,'Sukasari Baking Bandung','Susu UHT',6000.00,'ml','Stok Awal','2026-08-13 11:57:04',6000.00,NULL,NULL,NULL),(85,81,10,'Sukasari Baking Bandung','Gula Aren',3000.00,'ml','Stok Awal','2026-08-13 11:57:04',3000.00,NULL,NULL,NULL),(86,82,10,'Sukasari Baking Bandung','Bubuk Cokelat',1000.00,'g','Stok Awal','2026-08-13 11:57:04',1000.00,NULL,NULL,NULL),(87,83,10,'Sukasari Baking Bandung','Bubuk Matcha',500.00,'g','Stok Awal','2026-08-13 11:57:04',500.00,NULL,NULL,NULL),(88,84,11,'Toffin West Java','Krimer',1000.00,'g','Stok Awal','2026-08-13 11:57:04',1000.00,NULL,NULL,NULL),(89,85,11,'Toffin West Java','Sirup Berry',1400.00,'ml','Stok Awal','2026-08-13 11:57:04',1400.00,NULL,NULL,NULL),(90,86,11,'Toffin West Java','Sirup Peach',1400.00,'ml','Stok Awal','2026-08-13 11:57:04',1400.00,NULL,NULL,NULL),(91,87,11,'Toffin West Java','Sirup Butterscotch',1400.00,'ml','Stok Awal','2026-08-13 11:57:04',1400.00,NULL,NULL,NULL),(92,88,12,'Agen Galon Le Minerale Bandung','Air Mineral',19000.00,'ml','Stok Awal','2026-07-01 08:00:00',19000.00,NULL,NULL,NULL),(93,89,13,'Es Batu Kristal Paman','Es Batu',10000.00,'g','Stok Awal','2026-08-13 11:57:04',10000.00,NULL,NULL,NULL),(94,90,10,'Sukasari Baking Bandung','Cinnamon',10.00,'g','Stok Awal','2026-08-13 11:57:04',10.00,NULL,NULL,NULL),(95,91,10,'Sukasari Baking Bandung','Cheesy Foam',1000.00,'g','Stok Awal','2026-08-13 11:57:04',1000.00,NULL,NULL,NULL),(96,92,10,'Sukasari Baking Bandung','Caramel Crumble',2.00,'g','Stok Awal','2026-08-13 11:57:04',2.00,NULL,NULL,NULL),(97,93,11,'Toffin West Java','Strawberry Sauce',1000.00,'ml','Stok Awal','2026-08-13 11:57:04',1000.00,NULL,NULL,NULL),(98,94,14,'Kemasan Jaya Gemilang','Cup',100.00,'pcs','Stok Awal','2026-08-13 11:57:04',100.00,NULL,NULL,NULL),(99,95,14,'Kemasan Jaya Gemilang','Sedotan',100.00,'pcs','Stok Awal','2026-08-13 11:57:04',100.00,NULL,NULL,NULL),(100,96,14,'Kemasan Jaya Gemilang','Plastik Takeaway',100.00,'pcs','Stok Awal','2026-08-13 11:57:04',100.00,NULL,NULL,NULL),(117,94,14,'Kemasan Jaya Gemilang','Cup',60.00,'pcs','Penerimaan Supplier','2026-09-06 14:44:56',129.00,2.00,'pack',30.00),(118,92,10,'Sukasari Baking Bandung','Caramel Crumble',500.00,'g','Restock','2026-09-06 20:51:55',502.00,500.00,'g',0.00),(119,93,11,'Toffin West Java','Strawberry Sauce',25.00,'ml','Restock','2026-09-08 11:28:03',225.00,1.00,'botol',25.00),(120,91,10,'Sukasari Baking Bandung','Cheesy Foam',500.00,'g','Restock','2026-09-09 06:59:57',700.00,500.00,'g',0.00),(121,85,11,'Toffin West Java','Sirup Berry',1750.00,'ml','Restock','2026-09-09 08:53:43',1930.00,5.00,'botol',350.00),(122,84,11,'Toffin West Java','Krimer',500.00,'g','Restock','2026-09-09 08:54:22',700.00,1.00,'pack',500.00),(126,114,11,'Toffin West Java','Sirup Chese Italy',5000.00,'ml','Stok Awal','2026-09-10 05:17:04',5000.00,5.00,'botol',1000.00),(127,85,11,'Toffin West Java','Sirup Berry',5000.00,'ml','Restock','2026-09-10 05:17:04',5060.00,5.00,'botol',1000.00),(128,86,11,'Toffin West Java','Sirup Peach',5000.00,'ml','Restock','2026-09-10 05:17:04',5180.00,5.00,'botol',1000.00),(129,84,11,'Toffin West Java','Krimer',2600.00,'g','Restock','2026-09-10 05:17:04',2690.00,5.00,'pack',520.00),(130,88,12,'Agen Galon Terdekat','Air Mineral',75000.00,'ml','Restock','2026-09-10 07:03:13',78200.00,5.00,'galon',15000.00),(131,85,11,'Toffin West Java','Sirup Berry',12000.00,'ml','Restock','2026-09-10 07:25:00',17060.00,1.00,'dus',12000.00),(132,87,11,'Toffin West Java','Sirup Butterscotch',3000.00,'ml','Restock','2026-09-10 07:25:00',3200.00,5.00,'botol',600.00),(133,79,NULL,NULL,'Kopi Robusta',400.00,'g','Stok awal simulasi menu','2026-09-19 16:18:57',600.00,NULL,NULL,NULL),(134,78,NULL,NULL,'Kopi Arabica',445.00,'g','Stok awal simulasi menu','2026-09-19 16:18:57',600.00,NULL,NULL,NULL),(135,96,NULL,NULL,'Plastik Takeaway',48.00,'pcs','Stok awal simulasi menu','2026-09-19 16:18:57',60.00,NULL,NULL,NULL),(136,80,NULL,NULL,'Susu UHT',3100.00,'ml','Stok awal simulasi menu','2026-09-19 16:18:57',3750.00,NULL,NULL,NULL),(137,81,NULL,NULL,'Gula Aren',1180.00,'ml','Stok awal simulasi menu','2026-09-19 16:18:57',1500.00,NULL,NULL,NULL),(138,93,NULL,NULL,'Strawberry Sauce',375.00,'ml','Stok awal simulasi menu','2026-09-19 16:18:57',600.00,NULL,NULL,NULL),(139,82,NULL,NULL,'Bubuk Cokelat',450.00,'g','Stok awal simulasi menu','2026-09-19 16:18:57',600.00,NULL,NULL,NULL),(140,83,NULL,NULL,'Bubuk Matcha',275.00,'g','Stok awal simulasi menu','2026-09-19 16:18:57',375.00,NULL,NULL,NULL),(141,116,11,'Toffin West Java','Sirup Gula Jawa',2500.00,'ml','Stok Awal','2026-09-19 19:27:23',2500.00,5.00,'botol',500.00),(142,85,11,'Toffin West Java','Sirup Berry',2500.00,'ml','Restock','2026-09-19 19:27:23',3105.00,5.00,'botol',500.00),(143,86,11,'Toffin West Java','Sirup Peach',2500.00,'ml','Restock','2026-09-19 19:27:23',2500.00,5.00,'botol',500.00);
/*!40000 ALTER TABLE `riwayat_masuk` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `supplier`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `supplier` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_supplier` varchar(120) NOT NULL,
  `no_telepon` varchar(30) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nama_supplier` (`nama_supplier`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `supplier` WRITE;
/*!40000 ALTER TABLE `supplier` DISABLE KEYS */;
INSERT INTO `supplier` VALUES (9,'Aja Coffee','0815-5000-0709','Padalestari 11, Bandung, Jawa Barat 40154','2026-08-13 04:57:04'),(10,'Sukasari Baking Bandung','0813-8722-8280','Jl. Kalipah Apo No.26A RT03/RW04, Karanganyar, Kec. Astanaanyar, Kota Bandung, Jawa Barat 40241','2026-08-13 04:57:04'),(11,'Toffin West Java','0811-2260-056','Jl. Pajajaran No.68C, Pamoyanan, Kec. Cicendo, Kota Bandung, Jawa Barat 40173','2026-08-13 04:57:04'),(12,'Agen Galon Terdekat',NULL,NULL,'2026-08-13 04:57:04'),(13,'Es Batu Kristal Paman','0822-2106-6616','Jl. Sekeloa Selatan I No.99A, Lebakgede, Kec. Coblong, Kota Bandung, Jawa Barat 40132','2026-08-13 04:57:04'),(14,'Kemasan Jaya Gemilang','(022) 87304128','Jl. Batununggal Indah Raya No.377, Batununggal, Kec. Bandung Kidul, Kota Bandung, Jawa Barat 40266','2026-08-13 04:57:04'),(16,'AKAPACK KEMASAN','0857-9756-6046','Jl. Ibrahim Adjie No.147, Babakan Sari, Kec. Kiaracondong, Kota Bandung, Jawa Barat 40283','2026-08-13 05:00:33');
/*!40000 ALTER TABLE `supplier` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `supplier_bahan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `supplier_bahan` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_id` int(11) NOT NULL,
  `bahan_id` int(11) NOT NULL,
  `harga` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_supplier_bahan` (`supplier_id`,`bahan_id`)
) ENGINE=InnoDB AUTO_INCREMENT=100 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `supplier_bahan` WRITE;
/*!40000 ALTER TABLE `supplier_bahan` DISABLE KEYS */;
INSERT INTO `supplier_bahan` VALUES (52,9,78,160000.00,'2026-08-13 04:57:04'),(53,9,79,115000.00,'2026-08-13 04:57:04'),(54,10,80,24500.00,'2026-08-13 04:57:04'),(55,10,81,35000.00,'2026-08-13 04:57:04'),(56,10,82,90000.00,'2026-08-13 04:57:04'),(57,10,83,100000.00,'2026-08-13 04:57:04'),(58,11,84,55000.00,'2026-08-13 04:57:04'),(59,11,85,85000.00,'2026-08-13 04:57:04'),(60,11,86,85000.00,'2026-08-13 04:57:04'),(61,11,87,88000.00,'2026-08-13 04:57:04'),(63,13,89,25000.00,'2026-08-13 04:57:04'),(64,10,90,35000.00,'2026-08-13 04:57:04'),(65,10,91,80000.00,'2026-08-13 04:57:04'),(66,10,92,45000.00,'2026-08-13 04:57:04'),(67,11,93,60000.00,'2026-08-13 04:57:04'),(68,14,94,45000.00,'2026-08-13 04:57:04'),(69,14,95,15000.00,'2026-08-13 04:57:04'),(70,14,96,25000.00,'2026-08-13 04:57:04'),(84,16,94,42000.00,'2026-08-13 05:07:47'),(85,16,95,13000.00,'2026-08-13 05:07:47'),(86,16,96,23000.00,'2026-08-13 05:07:47'),(91,12,88,22000.00,'2026-08-13 07:55:16'),(92,12,109,10000.00,'2026-08-13 07:55:16'),(97,11,114,0.00,'2026-09-09 22:15:17'),(98,10,115,0.00,'2026-09-13 04:54:35'),(99,11,116,0.00,'2026-09-19 12:26:24');
/*!40000 ALTER TABLE `supplier_bahan` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `transaksi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transaksi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kode_transaksi` varchar(50) NOT NULL,
  `metode_pembayaran` enum('Cash','QRIS') NOT NULL,
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `bayar` decimal(12,2) NOT NULL DEFAULT 0.00,
  `kembalian` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_profit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode_transaksi` (`kode_transaksi`)
) ENGINE=InnoDB AUTO_INCREMENT=104 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `transaksi` WRITE;
/*!40000 ALTER TABLE `transaksi` DISABLE KEYS */;
INSERT INTO `transaksi` VALUES (61,'INV-QR-20260803101500-58','Cash',13000.00,20000.00,7000.00,5000.00,'2026-08-03 03:15:00'),(62,'INV-KD-20260810142000-59','QRIS',15000.00,15000.00,0.00,5000.00,'2026-08-10 07:20:00'),(63,'INV-QR-20260813113000-60','Cash',16000.00,20000.00,4000.00,5000.00,'2026-08-13 04:30:00'),(64,'INV-KD-20260813125402-61','Cash',17000.00,20000.00,3000.00,6000.00,'2026-08-13 05:54:02'),(65,'INV-QR-20260718092000-62','Cash',16000.00,20000.00,4000.00,6000.00,'2026-07-18 02:20:00'),(66,'INV-KD-20260720131000-63','QRIS',23000.00,23000.00,0.00,9000.00,'2026-07-20 06:10:00'),(67,'INV-QR-20260722153500-64','QRIS',15000.00,15000.00,0.00,5000.00,'2026-07-22 08:35:00'),(68,'INV-KD-20260724110500-65','Cash',15000.00,20000.00,5000.00,5000.00,'2026-07-24 04:05:00'),(69,'INV-QR-20260726164000-66','Cash',18000.00,20000.00,2000.00,6000.00,'2026-07-26 09:40:00'),(70,'INV-KD-20260728105000-67','QRIS',13000.00,13000.00,0.00,4000.00,'2026-07-28 03:50:00'),(71,'INV-QR-20260730142500-68','QRIS',10000.00,10000.00,0.00,4000.00,'2026-07-30 07:25:00'),(72,'INV-KD-20260731171500-69','Cash',13000.00,15000.00,2000.00,5000.00,'2026-07-31 10:15:00'),(73,'INV-QR-20260801094500-70','Cash',15000.00,20000.00,5000.00,5000.00,'2026-08-01 02:45:00'),(74,'INV-KD-20260802123000-71','QRIS',17000.00,17000.00,0.00,6000.00,'2026-08-02 05:30:00'),(75,'INV-QR-20260804151000-72','QRIS',16000.00,16000.00,0.00,5000.00,'2026-08-04 08:10:00'),(76,'INV-KD-20260805102000-73','Cash',16000.00,20000.00,4000.00,6000.00,'2026-08-05 03:20:00'),(77,'INV-QR-20260806135000-74','Cash',15000.00,20000.00,5000.00,5000.00,'2026-08-06 06:50:00'),(78,'INV-KD-20260807160500-75','QRIS',23000.00,23000.00,0.00,9000.00,'2026-08-07 09:05:00'),(79,'INV-QR-20260808114000-76','QRIS',18000.00,18000.00,0.00,6000.00,'2026-08-08 04:40:00'),(80,'INV-KD-20260812145500-77','Cash',13000.00,15000.00,2000.00,4000.00,'2026-08-12 07:55:00'),(96,'INV-KD-20260813165033-100','QRIS',17000.00,17000.00,0.00,6000.00,'2026-08-13 09:50:33'),(97,'INV-QR-20260907095646-106','QRIS',20000.00,20000.00,0.00,8000.00,'2026-09-07 02:56:46'),(98,'INV-KD-20260904095646-107','Cash',15000.00,20000.00,5000.00,5000.00,'2026-09-04 02:56:46'),(99,'INV-QR-20260828095646-108','Cash',15000.00,20000.00,5000.00,5000.00,'2026-08-28 02:56:46'),(100,'INV-QR-20260907100519-104','QRIS',16000.00,16000.00,0.00,6000.00,'2026-09-07 03:05:19'),(101,'INV-KD-20260909065415-110','Cash',15000.00,20000.00,5000.00,5000.00,'2026-09-08 23:54:15'),(102,'INV-KD-20260919190416-111','Cash',45000.00,50000.00,5000.00,16000.00,'2026-09-19 12:04:16'),(103,'INV-KD-20260919190620-112','QRIS',29000.00,29000.00,0.00,9000.00,'2026-09-19 12:06:20');
/*!40000 ALTER TABLE `transaksi` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `transaksi_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `transaksi_detail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaksi_id` int(11) NOT NULL,
  `menu_id` int(11) NOT NULL,
  `nama_menu` varchar(150) NOT NULL,
  `harga_satuan` decimal(12,2) NOT NULL,
  `keuntungan_satuan` decimal(12,2) NOT NULL,
  `qty` int(11) NOT NULL DEFAULT 1,
  `subtotal` decimal(12,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_transaksi_detail_transaksi` (`transaksi_id`),
  KEY `fk_transaksi_detail_menu` (`menu_id`),
  CONSTRAINT `fk_transaksi_detail_menu` FOREIGN KEY (`menu_id`) REFERENCES `menu` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_transaksi_detail_transaksi` FOREIGN KEY (`transaksi_id`) REFERENCES `transaksi` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=167 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `transaksi_detail` WRITE;
/*!40000 ALTER TABLE `transaksi_detail` DISABLE KEYS */;
INSERT INTO `transaksi_detail` VALUES (121,61,26,'Ice Black',13000.00,5000.00,1,13000.00),(122,62,27,'Berry Party',15000.00,5000.00,1,15000.00),(123,63,31,'Ice White',16000.00,5000.00,1,16000.00),(124,64,32,'Ice Brown',17000.00,6000.00,1,17000.00),(125,65,28,'Sunshine',16000.00,6000.00,1,16000.00),(126,66,29,'Manual Brew',23000.00,9000.00,1,23000.00),(127,67,35,'Korean Berry',15000.00,5000.00,1,15000.00),(128,68,36,'Chocolate',15000.00,5000.00,1,15000.00),(129,69,37,'Matcha Lattea',18000.00,6000.00,1,18000.00),(130,70,30,'KoSu Sahabat',13000.00,4000.00,1,13000.00),(131,71,34,'Berry Shine T',10000.00,4000.00,1,10000.00),(132,72,26,'Ice Black',13000.00,5000.00,1,13000.00),(133,73,27,'Berry Party',15000.00,5000.00,1,15000.00),(134,74,32,'Ice Brown',17000.00,6000.00,1,17000.00),(135,75,31,'Ice White',16000.00,5000.00,1,16000.00),(136,76,28,'Sunshine',16000.00,6000.00,1,16000.00),(137,77,36,'Chocolate',15000.00,5000.00,1,15000.00),(138,78,29,'Manual Brew',23000.00,9000.00,1,23000.00),(139,79,37,'Matcha Lattea',18000.00,6000.00,1,18000.00),(140,80,30,'KoSu Sahabat',13000.00,4000.00,1,13000.00),(156,96,32,'Ice Brown',17000.00,6000.00,1,17000.00),(157,97,34,'Berry Shine T',10000.00,4000.00,2,20000.00),(158,98,35,'Korean Berry',15000.00,5000.00,1,15000.00),(159,99,27,'Berry Party',15000.00,5000.00,1,15000.00),(160,100,28,'Sunshine',16000.00,6000.00,1,16000.00),(161,101,27,'Berry Party',15000.00,5000.00,1,15000.00),(162,102,27,'Berry Party',15000.00,5000.00,1,15000.00),(163,102,26,'Ice Black',13000.00,5000.00,1,13000.00),(164,102,32,'Ice Brown',17000.00,6000.00,1,17000.00),(165,103,31,'Ice White',16000.00,5000.00,1,16000.00),(166,103,30,'KoSu Sahabat',13000.00,4000.00,1,13000.00);
/*!40000 ALTER TABLE `transaksi_detail` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'owner',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (3,'owner','$2y$10$F3YCuxeqYcsfL2My863qI./rsd0rlLI/XDaXLDybRql64F5ncnXkm','owner','2026-08-03 06:45:06'),(4,'kasir','$2y$10$jiaYSas49.kIqWiPFLyiyuh7E8UUH5yGJEokCoVTBeBf3M570TPVW','kasir','2026-09-05 10:44:42'),(5,'barista','$2y$10$yluLpE48cZwZboRncucRXOZwpQJDsEfYqc87FXp.GPICc1L0mQxW2','barista','2026-09-05 10:44:42'),(6,'admin','$2y$10$7A.pGbSN9mWXt2aelkHt0OrZ1p4b73lj4Ezmf/MgVTnJYiIOU8l0y','owner','2026-09-06 15:47:26');
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

