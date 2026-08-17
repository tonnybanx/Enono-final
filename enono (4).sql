-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 17, 2026 at 10:03 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `enono`
--

-- --------------------------------------------------------

--
-- Table structure for table `addresses`
--

CREATE TABLE `addresses` (
  `id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `label` varchar(50) DEFAULT NULL,
  `full_name` varchar(150) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `line1` varchar(255) NOT NULL,
  `line2` varchar(255) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `county` varchar(100) DEFAULT NULL,
  `village` varchar(100) DEFAULT NULL,
  `country` char(2) NOT NULL DEFAULT 'UG',
  `is_default` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attribute_types`
--

CREATE TABLE `attribute_types` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `display_name` varchar(80) NOT NULL,
  `sort_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attribute_types`
--

INSERT INTO `attribute_types` (`id`, `name`, `display_name`, `sort_order`) VALUES
(1, 'color', 'Color / Material', 1),
(2, 'size', 'Size', 2);

-- --------------------------------------------------------

--
-- Table structure for table `attribute_values`
--

CREATE TABLE `attribute_values` (
  `id` int(11) NOT NULL,
  `attribute_type_id` int(11) NOT NULL,
  `value` varchar(100) NOT NULL,
  `display_value` varchar(150) NOT NULL,
  `extra_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`extra_data`)),
  `sort_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attribute_values`
--

INSERT INTO `attribute_values` (`id`, `attribute_type_id`, `value`, `display_value`, `extra_data`, `sort_order`) VALUES
(1, 1, 'natural', 'Natural Wood / Fibre', NULL, 1),
(2, 1, 'dark', 'Dark Ebony / Deep Brown', NULL, 2),
(3, 1, 'amber', 'Amber / Terracotta', NULL, 3),
(4, 1, 'black', 'Matte Black / Charcoal', NULL, 4),
(5, 1, 'red', 'Deep Red / Ochre', NULL, 5),
(6, 1, 'cream', 'Cream / Off-white', NULL, 6),
(7, 2, 'small', 'Small', '{\"length_cm\": 12}', 1),
(8, 2, 'medium', 'Medium', '{\"length_cm\": 16}', 2),
(9, 2, 'large', 'Large', '{\"length_cm\": 20}', 3),
(10, 2, 'normal', 'Normal', NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `blog_posts`
--

CREATE TABLE `blog_posts` (
  `id` bigint(20) NOT NULL,
  `title` varchar(250) NOT NULL,
  `excerpt` text NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `read_time_minutes` int(11) DEFAULT NULL,
  `image_url` varchar(500) NOT NULL,
  `author_name` varchar(150) DEFAULT NULL,
  `author_country` varchar(100) DEFAULT NULL,
  `author_image_url` varchar(500) DEFAULT NULL,
  `published_at` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `blog_posts`
--

INSERT INTO `blog_posts` (`id`, `title`, `excerpt`, `category`, `read_time_minutes`, `image_url`, `author_name`, `author_country`, `author_image_url`, `published_at`, `is_active`, `sort_order`, `created_at`) VALUES
(1, 'The Royal Threads: How Our Kente Fabrics Tell Ancient Stories', 'Every pattern in our premium Kente collection carries centuries of meaning.', 'Kente Collection', 6, 'images/africanacity1.jpg', 'Mama Amina', 'Ghana', 'images/jeweleries.jpg', '2026-07-08', 1, 1, '2026-08-11 06:21:39'),
(2, 'From Sacred Forests: The Making of Our Hand-Carved Masks', 'Follow the journey of our master wood carvers.', 'Wood Carving', 7, 'images/sculpture1.jpg', 'Kweku Mensah', 'Ghana', 'images/africanacity1.jpg', '2026-07-05', 1, 2, '2026-08-11 06:21:39'),
(3, 'Beads That Speak: The Cultural Power Behind Our Jewelry', 'Discover how our beaded jewelry carries deep spiritual significance.', 'Jewelry & Beads', 5, 'images/jeweleries.jpg', 'Fatou Ndiaye', 'Senegal', 'https://picsum.photos/id/64/48/48', '2026-06-30', 1, 3, '2026-08-11 06:21:39');

-- --------------------------------------------------------

--
-- Table structure for table `carts`
--

CREATE TABLE `carts` (
  `id` bigint(20) NOT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `session_id` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `carts`
--

INSERT INTO `carts` (`id`, `user_id`, `session_id`, `created_at`, `updated_at`) VALUES
(1, 2, NULL, '2026-08-16 15:40:53', '2026-08-17 07:56:58');

-- --------------------------------------------------------

--
-- Table structure for table `cart_items`
--

CREATE TABLE `cart_items` (
  `id` bigint(20) NOT NULL,
  `cart_id` bigint(20) NOT NULL,
  `variant_id` bigint(20) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `price` decimal(12,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `parent_id`, `description`, `image_url`, `sort_order`, `is_active`, `deleted_at`, `created_at`) VALUES
(1, 'Pottery', NULL, 'Burnished clay vessels', NULL, 1, 1, NULL, '2026-08-11 06:21:37'),
(2, 'Weaving', NULL, 'Baskets, mats and barkcloth', NULL, 2, 1, NULL, '2026-08-11 06:21:37'),
(3, 'Beadwork', NULL, 'Necklaces, bracelets and more', NULL, 3, 1, NULL, '2026-08-11 06:21:37'),
(4, 'Woodcarving', NULL, 'Sculptures, masks and utensils', NULL, 4, 1, NULL, '2026-08-11 06:21:37');

-- --------------------------------------------------------

--
-- Table structure for table `company_profile`
--

CREATE TABLE `company_profile` (
  `id` int(11) NOT NULL DEFAULT 1,
  `company_name` varchar(150) NOT NULL,
  `tagline` varchar(255) DEFAULT NULL,
  `logo_url` varchar(500) DEFAULT NULL,
  `favicon_url` varchar(500) DEFAULT NULL,
  `short_description` text DEFAULT NULL,
  `full_history` text DEFAULT NULL,
  `mission` text DEFAULT NULL,
  `vision` text DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `whatsapp` varchar(50) DEFAULT NULL,
  `address_line1` varchar(255) DEFAULT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'Uganda',
  `facebook_url` varchar(500) DEFAULT NULL,
  `instagram_url` varchar(500) DEFAULT NULL,
  `twitter_url` varchar(500) DEFAULT NULL,
  `youtube_url` varchar(500) DEFAULT NULL,
  `tiktok_url` varchar(500) DEFAULT NULL,
  `working_hours` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `company_profile`
--

INSERT INTO `company_profile` (`id`, `company_name`, `tagline`, `logo_url`, `favicon_url`, `short_description`, `full_history`, `mission`, `vision`, `email`, `phone`, `whatsapp`, `address_line1`, `address_line2`, `city`, `country`, `facebook_url`, `instagram_url`, `twitter_url`, `youtube_url`, `tiktok_url`, `working_hours`, `created_at`, `updated_at`) VALUES
(1, 'Enono', 'Market', 'images/drum_logo.jpg', NULL, 'Connecting you to Africa\'s richest cultural heritage. Handmade in Uganda — every piece tells a story shaped by the potters, carvers and weavers keeping Uganda’s living traditions alive.', 'Enono Market was founded with a simple yet powerful vision: to connect the world with the rich craftsmanship and cultural heritage of Africa.', 'To empower African artisans, celebrate cultural heritage, and deliver authentic products that tell meaningful stories.', 'Authenticity – Every item is handcrafted and carefully verified from real artisans.\nFair Trade – We ensure artisans receive fair and transparent compensation.\nSustainability – Supporting ethical practices and preserving traditional crafts.', NULL, NULL, NULL, 'First Floor, Plot 25, Kampala Road', NULL, 'Kampala', 'Uganda', NULL, NULL, NULL, NULL, NULL, 'Mon – Sat: 9:00 AM – 6:00 PM', '2026-08-11 06:21:38', '2026-08-11 06:21:38');

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` bigint(20) NOT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `status` enum('new','read','replied','closed') NOT NULL DEFAULT 'new',
  `admin_reply` text DEFAULT NULL,
  `replied_by` bigint(20) DEFAULT NULL,
  `replied_at` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contact_messages`
--

INSERT INTO `contact_messages` (`id`, `user_id`, `name`, `email`, `phone`, `subject`, `message`, `status`, `admin_reply`, `replied_by`, `replied_at`, `ip_address`, `user_agent`, `created_at`, `updated_at`) VALUES
(3, 1, 'AGWENG TONNY BRIAN', 'agwengtonnybrian@gmail.com', NULL, '', 'Do you have all sorts of traditional products', 'replied', 'yeah, we have all sorts of traditional products', 1, '2026-08-16 10:27:42', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36', '2026-08-16 10:24:49', '2026-08-16 10:27:42');

-- --------------------------------------------------------

--
-- Table structure for table `faqs`
--

CREATE TABLE `faqs` (
  `id` bigint(20) NOT NULL,
  `category_id` int(11) NOT NULL,
  `question` varchar(500) NOT NULL,
  `answer` text NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `faqs`
--

INSERT INTO `faqs` (`id`, `category_id`, `question`, `answer`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'How can I track my order?', 'Go to the orders page, here you will see a summary of the things you have ordered, here they can be in pending, packed or delivered ready for pickup at the pickup point', 1, 1, '2026-08-11 06:21:39', '2026-08-16 10:50:31'),
(2, 1, 'My order is delayed, what should I do?', 'Please allow 2-5 business days. Contact support if longer.', 2, 1, '2026-08-11 06:21:39', '2026-08-11 06:21:39'),
(3, 1, 'Can I change my order after placing it?', 'Yes, within 2 hours of placing the order.', 3, 1, '2026-08-11 06:21:39', '2026-08-11 06:21:39'),
(4, 2, 'What is the delivery time?', 'Standard delivery usually takes 5-14 business days.', 1, 1, '2026-08-11 06:21:39', '2026-08-11 06:21:39'),
(5, 2, 'What are your shipping options?', 'Standard (free over $50) and express shipping available.', 2, 1, '2026-08-11 06:21:39', '2026-08-11 06:21:39'),
(6, 3, 'What is your return policy?', 'Unused items within 30 days of delivery in original condition.', 1, 1, '2026-08-11 06:21:39', '2026-08-11 06:21:39'),
(7, 3, 'How do I initiate a return?', 'Go to My Orders → select item → Return Item.', 2, 1, '2026-08-11 06:21:39', '2026-08-11 06:21:39'),
(8, 4, 'How do I reset my password?', 'Click Forgot Password on the sign-in page.', 1, 1, '2026-08-11 06:21:39', '2026-08-11 06:21:39'),
(9, 4, 'How do I update my shipping address?', 'Account Settings → Addresses.', 2, 1, '2026-08-11 06:21:39', '2026-08-11 06:21:39');

-- --------------------------------------------------------

--
-- Table structure for table `faq_categories`
--

CREATE TABLE `faq_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `faq_categories`
--

INSERT INTO `faq_categories` (`id`, `name`, `display_name`, `sort_order`, `is_active`, `created_at`) VALUES
(1, 'order', 'Order Issues', 1, 1, '2026-08-11 06:21:39'),
(2, 'shipping', 'Shipping & Delivery', 2, 1, '2026-08-11 06:21:39'),
(3, 'return', 'Return & Refund', 3, 1, '2026-08-11 06:21:39'),
(4, 'account', 'Managing my Account', 4, 1, '2026-08-11 06:21:39');

-- --------------------------------------------------------

--
-- Table structure for table `general_images`
--

CREATE TABLE `general_images` (
  `id` bigint(20) NOT NULL,
  `title` varchar(200) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `url` varchar(500) NOT NULL,
  `alt_text` varchar(255) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `general_images`
--

INSERT INTO `general_images` (`id`, `title`, `description`, `url`, `alt_text`, `category`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'Company Logo', NULL, 'images/drum_logo.jpg', 'Enono Logo', 'logo', 1, 1, '2026-08-11 06:21:38', '2026-08-11 06:21:38'),
(2, 'Artisan Basket Weaver', NULL, 'images/basketweaver.jpg', 'Artisan weaving a basket', 'about', 1, 1, '2026-08-11 06:21:38', '2026-08-11 06:21:38'),
(3, 'Homepage Hero', NULL, 'https://media.istockphoto.com/id/695152194/photo/african-drums.jpg', 'African drums', 'banner', 1, 1, '2026-08-11 06:21:38', '2026-08-11 06:21:38'),
(4, 'Africanacity 1', NULL, 'images/africanacity1.jpg', 'Africanacity scene 1', 'africanacity', 1, 1, '2026-08-11 06:21:38', '2026-08-11 06:21:38'),
(5, 'Africanacity 2', NULL, 'images/africanacity2.jpg', 'Africanacity scene 2', 'africanacity', 1, 2, '2026-08-11 06:21:38', '2026-08-11 06:21:38'),
(6, 'Location', NULL, 'images/location.PNG', 'Kampala, Uganda', 'location', 1, 1, '2026-08-11 06:21:38', '2026-08-11 06:21:38'),
(7, 'Collection 1', NULL, 'images/collections1.jpg', 'Collection 1', 'collections', 1, 1, '2026-08-11 06:21:38', '2026-08-11 06:21:38'),
(8, 'Collection 2', NULL, 'images/collections2.jpg', 'Collection 2', 'collections', 1, 2, '2026-08-11 06:21:38', '2026-08-11 06:21:38'),
(9, 'Collection 3', NULL, 'images/collections3.jpg', 'Collection 3', 'collections', 1, 3, '2026-08-11 06:21:38', '2026-08-11 06:21:38'),
(10, 'Collection 4', NULL, 'images/collections4.jpg', 'Collection 4', 'collections', 1, 4, '2026-08-11 06:21:38', '2026-08-11 06:21:38'),
(11, 'Collection 5', NULL, 'images/collections5.jpg', 'Collection 5', 'collections', 1, 5, '2026-08-11 06:21:38', '2026-08-11 06:21:38'),
(12, 'Collection 6', NULL, 'images/collections6.jpg', 'Collection 6', 'collections', 1, 6, '2026-08-11 06:21:38', '2026-08-11 06:21:38'),
(13, 'Collection 7', NULL, 'images/collections7.jpg', 'Collection 7', 'collections', 1, 7, '2026-08-11 06:21:38', '2026-08-11 06:21:38'),
(14, 'Collection 8', NULL, 'images/collections8.jpg', 'Collection 8', 'collections', 1, 8, '2026-08-11 06:21:38', '2026-08-11 06:21:38');

-- --------------------------------------------------------

--
-- Table structure for table `message_replies`
--

CREATE TABLE `message_replies` (
  `id` bigint(20) NOT NULL,
  `contact_message_id` bigint(20) NOT NULL,
  `sender_type` enum('customer','admin') NOT NULL,
  `sender_id` bigint(20) DEFAULT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `message_replies`
--

INSERT INTO `message_replies` (`id`, `contact_message_id`, `sender_type`, `sender_id`, `message`, `created_at`) VALUES
(6, 3, 'admin', 1, 'yeah, we have all sorts of traditional products', '2026-08-16 10:27:42');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` bigint(20) NOT NULL,
  `order_number` varchar(30) NOT NULL,
  `user_id` bigint(20) DEFAULT NULL,
  `customer_name` varchar(150) NOT NULL,
  `customer_email` varchar(255) DEFAULT NULL,
  `customer_phone` varchar(30) DEFAULT NULL,
  `status` enum('Pending','Packed','Shipped','Delivered','Cancelled') NOT NULL DEFAULT 'Pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_status` enum('Pending','Paid','Failed','Refunded') DEFAULT 'Pending',
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `shipping_fee` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `currency` char(3) DEFAULT 'UGX',
  `shipping_address` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `order_number`, `user_id`, `customer_name`, `customer_email`, `customer_phone`, `status`, `payment_method`, `payment_status`, `subtotal`, `shipping_fee`, `total`, `currency`, `shipping_address`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'ENO-20260816-692336', 2, 'Tony Brian AGWENG', '2101600069@sun.ac.ug', '0784995070', 'Shipped', 'mtn', 'Pending', 155000.00, 0.00, 155000.00, 'UGX', 'Tony Brian AGWENG\nKyambogo village mall, street 4\nKampala\nUganda', NULL, '2026-08-16 16:08:38', '2026-08-17 05:56:48'),
(2, 'ENO-20260817-763394', 2, 'Tony Brian AGWENG', '2101600069@sun.ac.ug', '0758678678', 'Pending', 'mtn', 'Paid', 515000.00, 0.00, 515000.00, 'UGX', 'Tony Brian AGWENG\nMasaka city center\nMasaka\nUganda', ' | TX: SIM-B0B3C0C2537A', '2026-08-17 07:47:03', '2026-08-17 07:47:04'),
(3, 'ENO-20260817-00C6DA', 2, 'Tony Brian AGWENG', '2101600069@sun.ac.ug', '0758678678', 'Pending', 'mtn', 'Paid', 150000.00, 0.00, 150000.00, 'UGX', 'Tony Brian AGWENG\nMasaka city center\nMasaka\nUganda', ' | TX: SIM-7E0F5D6C5361', '2026-08-17 07:58:08', '2026-08-17 07:58:08');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` bigint(20) NOT NULL,
  `order_id` bigint(20) NOT NULL,
  `product_id` bigint(20) DEFAULT NULL,
  `variant_id` bigint(20) DEFAULT NULL,
  `product_name` varchar(200) NOT NULL,
  `sku` varchar(50) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(12,2) NOT NULL,
  `total_price` decimal(12,2) NOT NULL,
  `status` enum('Pending','Packed','Shipped','Delivered') NOT NULL DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `variant_id`, `product_name`, `sku`, `quantity`, `unit_price`, `total_price`, `status`) VALUES
(1, 1, 2, 20, 'Barkcloth (Embugo)', 'BARK-NAL-DRK', 1, 155000.00, 155000.00, 'Shipped'),
(2, 2, 2, 19, 'Barkcloth (Embugo)', 'BARK-NAL-AMB', 3, 150000.00, 450000.00, 'Pending'),
(3, 2, 3, 3, 'Beaded Brass Necklace (Embira)', 'JEW-MUK-NAT', 1, 65000.00, 65000.00, 'Pending'),
(4, 3, 2, 19, 'Barkcloth (Embugo)', 'BARK-NAL-AMB', 1, 150000.00, 150000.00, 'Pending');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` bigint(20) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `spirit_id` int(11) DEFAULT NULL,
  `name` varchar(200) NOT NULL,
  `short_description` text DEFAULT NULL,
  `long_description` text DEFAULT NULL,
  `heritage_title` varchar(200) DEFAULT 'Heritage & History',
  `heritage_body` text DEFAULT NULL,
  `base_price` decimal(12,2) NOT NULL,
  `currency` char(3) DEFAULT 'UGX',
  `is_active` tinyint(1) DEFAULT 1,
  `is_featured` tinyint(1) DEFAULT 0,
  `meta_title` varchar(200) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `category_id`, `spirit_id`, `name`, `short_description`, `long_description`, `heritage_title`, `heritage_body`, `base_price`, `currency`, `is_active`, `is_featured`, `meta_title`, `meta_description`, `created_at`, `updated_at`) VALUES
(1, 4, NULL, 'Ancestral Smoking Pipe', 'Hand-carved hardwood smoking pipe dedicated to ancestral spirits.', 'More than a pipe — a sacred vessel of spirit and memory.', 'Heritage & History', NULL, 130000.00, 'UGX', 1, 1, NULL, NULL, '2026-08-11 06:21:37', '2026-08-11 06:21:37'),
(2, 2, NULL, 'Barkcloth (Embugo)', 'Traditional Buganda barkcloth with rich terracotta tones.', 'Handmade from the bark of the mutuba tree using ancient techniques.', 'Heritage & History', NULL, 150000.00, 'UGX', 1, 1, NULL, NULL, '2026-08-11 06:21:37', '2026-08-11 06:21:37'),
(3, 3, NULL, 'Beaded Brass Necklace (Embira)', 'Handmade statement necklace of recycled paper beads and brass.', 'Crafted by skilled Ugandan beadworkers.', 'Heritage & History', NULL, 65000.00, 'UGX', 1, 1, NULL, NULL, '2026-08-11 06:21:37', '2026-08-11 06:21:37'),
(4, 1, NULL, 'Traditional Clay Pot (Ensumbi)', 'Hand-coiled terracotta water pot burnished with natural ochre.', 'Made using ancestral coiling techniques.', 'Heritage & History', NULL, 85000.00, 'UGX', 1, 1, NULL, NULL, '2026-08-11 06:21:37', '2026-08-11 06:21:37'),
(5, 2, NULL, 'Handwoven Basket', 'Beautifully woven basket made from natural fibres.', 'Functional and decorative basket crafted by Ugandan weavers.', 'Heritage & History', NULL, 45000.00, 'UGX', 1, 0, NULL, NULL, '2026-08-11 06:21:37', '2026-08-11 06:21:37'),
(6, 4, NULL, 'Traditional Drum', 'Hand-carved wooden drum with animal-skin head.', 'Used in cultural ceremonies and celebrations.', 'Heritage & History', NULL, 180000.00, 'UGX', 1, 0, NULL, NULL, '2026-08-11 06:21:37', '2026-08-11 06:21:37'),
(7, 2, NULL, 'Traditional Kanzu', 'Elegant white Kanzu worn during important cultural occasions.', 'Hand-tailored traditional attire symbolising respect and heritage.', 'Heritage & History', NULL, 120000.00, 'UGX', 1, 0, NULL, NULL, '2026-08-11 06:21:37', '2026-08-11 06:21:37'),
(8, 4, NULL, 'Carved Wooden Mask', 'Intricately carved ceremonial mask.', 'Each mask carries cultural meaning and is carved from locally sourced wood.', 'Heritage & History', NULL, 95000.00, 'UGX', 1, 0, NULL, NULL, '2026-08-11 06:21:37', '2026-08-11 06:21:37'),
(9, 4, NULL, 'Wooden Sculpture', 'Hand-carved wooden sculpture.', 'A unique piece of African art carved by skilled artisans.', 'Heritage & History', NULL, 110000.00, 'UGX', 1, 0, NULL, NULL, '2026-08-11 06:21:37', '2026-08-11 06:21:37');

-- --------------------------------------------------------

--
-- Table structure for table `product_attribute_types`
--

CREATE TABLE `product_attribute_types` (
  `product_id` bigint(20) NOT NULL,
  `attribute_type_id` int(11) NOT NULL,
  `is_required` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_attribute_types`
--

INSERT INTO `product_attribute_types` (`product_id`, `attribute_type_id`, `is_required`, `sort_order`) VALUES
(1, 1, 1, 1),
(1, 2, 0, 2),
(2, 1, 1, 1),
(2, 2, 0, 2),
(3, 1, 1, 1),
(3, 2, 0, 2),
(4, 1, 1, 1),
(4, 2, 0, 2),
(5, 1, 1, 1),
(5, 2, 0, 2),
(6, 1, 1, 1),
(6, 2, 0, 2),
(7, 1, 1, 1),
(7, 2, 0, 2),
(8, 1, 1, 1),
(8, 2, 0, 2),
(9, 1, 1, 1),
(9, 2, 0, 2);

-- --------------------------------------------------------

--
-- Table structure for table `product_content`
--

CREATE TABLE `product_content` (
  `id` bigint(20) NOT NULL,
  `product_id` bigint(20) NOT NULL,
  `content_type` varchar(50) NOT NULL,
  `title` varchar(200) DEFAULT NULL,
  `body` text NOT NULL,
  `attribute_value_id` int(11) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_content`
--

INSERT INTO `product_content` (`id`, `product_id`, `content_type`, `title`, `body`, `attribute_value_id`, `sort_order`) VALUES
(1, 1, 'heritage', 'Heritage & History', 'In Buganda tradition, carefully carved smoking pipes were more than tools — they were sacred objects used in ritual, counsel, and communion with the ancestors. Each pipe was dedicated to a particular spirit and treated with deep respect.', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `id` bigint(20) NOT NULL,
  `product_id` bigint(20) DEFAULT NULL,
  `variant_id` bigint(20) DEFAULT NULL,
  `url` varchar(500) NOT NULL,
  `alt_text` varchar(255) DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_variants`
--

CREATE TABLE `product_variants` (
  `id` bigint(20) NOT NULL,
  `product_id` bigint(20) NOT NULL,
  `spirit_id` int(11) DEFAULT NULL,
  `sku` varchar(50) NOT NULL,
  `name` varchar(250) NOT NULL,
  `title` varchar(250) NOT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `price` decimal(12,2) NOT NULL,
  `compare_at_price` decimal(12,2) DEFAULT NULL,
  `cost_price` decimal(12,2) DEFAULT NULL,
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `low_stock_threshold` int(11) DEFAULT 5,
  `weight_grams` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_variants`
--

INSERT INTO `product_variants` (`id`, `product_id`, `spirit_id`, `sku`, `name`, `title`, `image_url`, `price`, `compare_at_price`, `cost_price`, `stock_quantity`, `low_stock_threshold`, `weight_grams`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'PIPE-MUK-NAT', '', 'Mukasa’s Pipe – Natural Wood', 'images/pipe5.jpg', 130000.00, NULL, NULL, 12, 5, NULL, 1, '2026-08-11 06:21:37', '2026-08-13 14:30:29'),
(2, 1, 1, 'PIPE-MUK-DRK', '', 'Mukasa’s Pipe – Dark Ebony', 'images/pipe3.jpg', 135000.00, NULL, NULL, 10, 5, NULL, 1, '2026-08-11 06:21:37', '2026-08-13 14:30:29'),
(3, 3, 1, 'JEW-MUK-NAT', '', 'Beaded Necklace – Natural', 'images/jewelery10.jpg', 65000.00, NULL, NULL, 19, 5, NULL, 1, '2026-08-11 06:21:37', '2026-08-17 07:47:03'),
(4, 3, 1, 'JEW-MUK-RED', '', 'Beaded Necklace – Deep Red', 'images/jewelery2.jpg', 68000.00, NULL, NULL, 18, 5, NULL, 1, '2026-08-11 06:21:37', '2026-08-13 14:30:29'),
(5, 3, 1, 'JEW-MUK-BLK', '', 'Beaded Necklace – Charcoal', 'images/jewelery4.jpg', 70000.00, NULL, NULL, 15, 5, NULL, 1, '2026-08-11 06:21:37', '2026-08-13 14:30:29'),
(6, 6, 1, 'DRUM-MUK-NAT', '', 'Traditional Drum – Natural', 'images/drum1.jpg', 180000.00, NULL, NULL, 6, 5, NULL, 1, '2026-08-11 06:21:37', '2026-08-13 14:30:29'),
(7, 6, 1, 'DRUM-MUK-DRK', '', 'Traditional Drum – Dark', 'images/drum3.jpg', 185000.00, NULL, NULL, 5, 5, NULL, 1, '2026-08-11 06:21:37', '2026-08-13 14:30:29'),
(8, 8, 1, 'MASK-MUK-BLK', '', 'Carved Mask – Matte Black', 'images/mask5.jpg', 105000.00, NULL, NULL, 6, 5, NULL, 1, '2026-08-11 06:21:37', '2026-08-13 14:30:29'),
(9, 9, 1, 'SCUL-MUK-NAT', '', 'Wooden Sculpture – Natural', 'images/sculpture1.jpg', 110000.00, NULL, NULL, 5, 5, NULL, 1, '2026-08-11 06:21:37', '2026-08-13 14:30:29'),
(10, 9, 1, 'SCUL-MUK-DRK', '', 'Wooden Sculpture – Dark', NULL, 115000.00, NULL, NULL, 4, 5, NULL, 1, '2026-08-11 06:21:37', '2026-08-11 06:21:37'),
(11, 1, 2, 'PIPE-MUT-AMB', '', 'Mutebi’s Pipe – Amber Stain', 'images/pipe2.jpg', 130000.00, NULL, NULL, 8, 5, NULL, 1, '2026-08-11 06:21:37', '2026-08-13 14:30:29'),
(12, 1, 2, 'PIPE-MUT-BLK', '', 'Mutebi’s Pipe – Matte Black', 'images/pipe4.jpg', 140000.00, NULL, NULL, 9, 5, NULL, 1, '2026-08-11 06:21:37', '2026-08-13 14:30:29'),
(13, 4, 2, 'POT-MUT-NAT', '', 'Clay Pot – Natural', 'images/pot8.jpg', 85000.00, NULL, NULL, 10, 5, NULL, 1, '2026-08-11 06:21:37', '2026-08-13 14:30:29'),
(14, 5, 2, 'BASK-MUT-NAT', '', 'Handwoven Basket – Natural', 'images/basket1.jpg', 45000.00, NULL, NULL, 25, 5, NULL, 1, '2026-08-11 06:21:37', '2026-08-13 14:30:29'),
(15, 5, 2, 'BASK-MUT-AMB', '', 'Handwoven Basket – Amber', 'images/basket3.jpg', 48000.00, NULL, NULL, 20, 5, NULL, 1, '2026-08-11 06:21:37', '2026-08-13 14:30:29'),
(16, 5, 2, 'BASK-MUT-DRK', '', 'Handwoven Basket – Dark', 'images/basket4.jpg', 47000.00, NULL, NULL, 18, 5, NULL, 1, '2026-08-11 06:21:37', '2026-08-13 14:30:29'),
(17, 8, 2, 'MASK-MUT-NAT', '', 'Carved Mask – Natural Wood', 'images/mask1.jpg', 95000.00, NULL, NULL, 8, 5, NULL, 1, '2026-08-11 06:21:37', '2026-08-13 14:30:29'),
(18, 8, 2, 'MASK-MUT-DRK', '', 'Carved Mask – Dark Ebony', 'images/mask3.jpg', 100000.00, NULL, NULL, 7, 5, NULL, 1, '2026-08-11 06:21:37', '2026-08-13 14:30:29'),
(19, 2, NULL, 'BARK-NAL-AMB', '', 'Barkcloth – Amber / Terracotta', 'images/barkcloth1.jpg', 150000.00, NULL, NULL, 11, 5, NULL, 1, '2026-08-11 06:21:37', '2026-08-17 07:58:08'),
(20, 2, 3, 'BARK-NAL-DRK', '', 'Barkcloth – Deep Brown', 'images/barkcloth3.jpg', 155000.00, NULL, NULL, 11, 5, NULL, 1, '2026-08-11 06:21:37', '2026-08-16 16:08:39'),
(21, 2, 3, 'BARK-NAL-NAT', '', 'Barkcloth – Natural', 'images/barkcloth4.jpg', 150000.00, NULL, NULL, 10, 5, NULL, 1, '2026-08-11 06:21:37', '2026-08-13 14:30:29'),
(22, 4, 3, 'POT-NAL-AMB', '', 'Clay Pot – Amber / Ochre', 'images/claywork1.jpg', 85000.00, NULL, NULL, 14, 5, NULL, 1, '2026-08-11 06:21:37', '2026-08-13 14:30:29'),
(23, 4, 3, 'POT-NAL-DRK', '', 'Clay Pot – Dark Burnished', 'images/pot6.jpg', 90000.00, NULL, NULL, 11, 5, NULL, 1, '2026-08-11 06:21:37', '2026-08-13 14:30:29'),
(24, 6, 3, 'DRUM-NAL-AMB', '', 'Traditional Drum – Amber', 'images/drum4.jpg', 180000.00, NULL, NULL, 7, 5, NULL, 1, '2026-08-11 06:21:37', '2026-08-13 14:30:29'),
(25, 7, 3, 'KANZ-NAL-CRM', '', 'Kanzu – Cream / Off-white', 'images/kanzu1.jpg', 120000.00, NULL, NULL, 12, 5, NULL, 1, '2026-08-11 06:21:37', '2026-08-13 14:30:29'),
(26, 7, 3, 'KANZ-NAL-WHT', '', 'Kanzu – Pure White', 'images/kanzu3.jpg', 125000.00, NULL, NULL, 10, 5, NULL, 1, '2026-08-11 06:21:37', '2026-08-13 14:30:29');

-- --------------------------------------------------------

--
-- Table structure for table `product_variant_attributes`
--

CREATE TABLE `product_variant_attributes` (
  `variant_id` bigint(20) NOT NULL,
  `attribute_value_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_variant_attributes`
--

INSERT INTO `product_variant_attributes` (`variant_id`, `attribute_value_id`) VALUES
(1, 1),
(1, 10),
(2, 2),
(2, 10),
(3, 1),
(3, 10),
(4, 5),
(4, 10),
(5, 4),
(5, 10),
(6, 1),
(6, 10),
(7, 2),
(7, 10),
(8, 4),
(8, 10),
(9, 1),
(9, 10),
(10, 2),
(10, 10),
(11, 5),
(11, 10),
(12, 4),
(12, 10),
(13, 1),
(13, 10),
(14, 1),
(14, 10),
(15, 3),
(15, 10),
(16, 2),
(16, 10),
(17, 1),
(17, 10),
(18, 2),
(18, 10),
(19, 3),
(19, 10),
(20, 2),
(20, 10),
(21, 1),
(21, 10),
(22, 3),
(22, 10),
(23, 2),
(23, 10),
(24, 3),
(24, 10),
(25, 6),
(25, 10),
(26, 6),
(26, 10);

-- --------------------------------------------------------

--
-- Table structure for table `spirits`
--

CREATE TABLE `spirits` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `short_description` text DEFAULT NULL,
  `full_description` text NOT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `spirits`
--

INSERT INTO `spirits` (`id`, `name`, `short_description`, `full_description`, `image_url`, `is_active`, `sort_order`, `created_at`, `updated_at`) VALUES
(1, 'Mukasa', 'Guardian of the waters and bringer of prosperity', 'This spirit watches over the lakes and rivers of Buganda. Pieces dedicated to Mukasa are offered for protection of the home, fertility and safe journeys across water.', 'images/pipe5.jpg', 1, 1, '2026-08-11 06:21:37', '2026-08-11 06:21:37'),
(2, 'Mutebi', 'Protector of the land and the people', 'Mutebi guards the soil, the forests and the boundaries of the kingdom. These pieces call upon the quiet strength of the land itself.', 'images/pipe2.jpg', 1, 2, '2026-08-11 06:21:37', '2026-08-11 06:21:37'),
(3, 'Nalubaale', 'Mother of the lake and source of life-giving waters', 'Nalubaale is the great mother spirit of Lake Victoria — source of abundance, cleansing and maternal protection.', 'images/pipe7.jpg', 1, 3, '2026-08-11 06:21:37', '2026-08-11 06:21:37'),
(4, 'Walumbe', 'Spirit of transition and the ancestral realm', 'Walumbe is associated with death, transition, and the passage into the world of the ancestors. Smoking a pipe dedicated to Walumbe is an act of remembrance and humility.', 'images/pipe4.jpg', 1, 4, '2026-08-11 06:21:37', '2026-08-11 06:21:37'),
(5, 'Kibuka', 'Warrior spirit of protection and courage', 'Kibuka is the warrior spirit who defends the kingdom and its people. He is invoked for courage, protection in conflict, and the strength to stand firm.', 'images/pipe3.jpg', 1, 5, '2026-08-11 06:21:37', '2026-08-11 06:21:37');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `role` varchar(20) DEFAULT 'customer',
  `email_verified` tinyint(1) DEFAULT 0,
  `verification_token` varchar(64) DEFAULT NULL,
  `token_expires_at` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password_hash`, `first_name`, `last_name`, `phone`, `role`, `email_verified`, `verification_token`, `token_expires_at`, `is_active`, `created_at`, `updated_at`, `last_login_at`) VALUES
(1, 'agwengtonnybrian@gmail.com', '$2y$10$Q8aAKYCKxgaKsQp44I.gIuLRYKk6RCRuPQ8VjurEVz6unJ16shNrO', 'TONNY', 'BRIAN', NULL, 'admin', 1, NULL, NULL, 1, '2026-08-11 06:21:39', '2026-08-16 16:09:12', '2026-08-16 16:09:12'),
(2, '2101600069@sun.ac.ug', '$2y$10$lptsCPBGazsOAhQCRWGik.UuqcH/58641zV388wsCbJsJMpbsjA9.', 'Tony', 'Brian AGWENG', '', 'staff', 1, NULL, NULL, 1, '2026-08-16 15:39:38', '2026-08-17 07:45:58', '2026-08-17 07:45:58');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `addresses`
--
ALTER TABLE `addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `attribute_types`
--
ALTER TABLE `attribute_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `attribute_values`
--
ALTER TABLE `attribute_values`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `attribute_type_id` (`attribute_type_id`,`value`);

--
-- Indexes for table `blog_posts`
--
ALTER TABLE `blog_posts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `carts`
--
ALTER TABLE `carts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cart_variant` (`cart_id`,`variant_id`),
  ADD KEY `cart_id` (`cart_id`),
  ADD KEY `variant_id` (`variant_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `parent_id` (`parent_id`);

--
-- Indexes for table `company_profile`
--
ALTER TABLE `company_profile`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `status` (`status`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `faqs`
--
ALTER TABLE `faqs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `faq_categories`
--
ALTER TABLE `faq_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `general_images`
--
ALTER TABLE `general_images`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `message_replies`
--
ALTER TABLE `message_replies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contact_message_id` (`contact_message_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `spirit_id` (`spirit_id`);

--
-- Indexes for table `product_attribute_types`
--
ALTER TABLE `product_attribute_types`
  ADD PRIMARY KEY (`product_id`,`attribute_type_id`),
  ADD KEY `attribute_type_id` (`attribute_type_id`);

--
-- Indexes for table `product_content`
--
ALTER TABLE `product_content`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `attribute_value_id` (`attribute_value_id`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `variant_id` (`variant_id`);

--
-- Indexes for table `product_variants`
--
ALTER TABLE `product_variants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `spirit_id` (`spirit_id`);

--
-- Indexes for table `product_variant_attributes`
--
ALTER TABLE `product_variant_attributes`
  ADD PRIMARY KEY (`variant_id`,`attribute_value_id`),
  ADD KEY `attribute_value_id` (`attribute_value_id`);

--
-- Indexes for table `spirits`
--
ALTER TABLE `spirits`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `addresses`
--
ALTER TABLE `addresses`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attribute_types`
--
ALTER TABLE `attribute_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `attribute_values`
--
ALTER TABLE `attribute_values`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `blog_posts`
--
ALTER TABLE `blog_posts`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `carts`
--
ALTER TABLE `carts`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `cart_items`
--
ALTER TABLE `cart_items`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `faqs`
--
ALTER TABLE `faqs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `faq_categories`
--
ALTER TABLE `faq_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `general_images`
--
ALTER TABLE `general_images`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `message_replies`
--
ALTER TABLE `message_replies`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `product_content`
--
ALTER TABLE `product_content`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `product_variants`
--
ALTER TABLE `product_variants`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `spirits`
--
ALTER TABLE `spirits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `addresses`
--
ALTER TABLE `addresses`
  ADD CONSTRAINT `addresses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `attribute_values`
--
ALTER TABLE `attribute_values`
  ADD CONSTRAINT `attribute_values_ibfk_1` FOREIGN KEY (`attribute_type_id`) REFERENCES `attribute_types` (`id`);

--
-- Constraints for table `carts`
--
ALTER TABLE `carts`
  ADD CONSTRAINT `carts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cart_items`
--
ALTER TABLE `cart_items`
  ADD CONSTRAINT `cart_items_ibfk_1` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cart_items_ibfk_2` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `faqs`
--
ALTER TABLE `faqs`
  ADD CONSTRAINT `faqs_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `faq_categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `message_replies`
--
ALTER TABLE `message_replies`
  ADD CONSTRAINT `message_replies_ibfk_1` FOREIGN KEY (`contact_message_id`) REFERENCES `contact_messages` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `products_ibfk_spirit` FOREIGN KEY (`spirit_id`) REFERENCES `spirits` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_attribute_types`
--
ALTER TABLE `product_attribute_types`
  ADD CONSTRAINT `product_attribute_types_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_attribute_types_ibfk_2` FOREIGN KEY (`attribute_type_id`) REFERENCES `attribute_types` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_content`
--
ALTER TABLE `product_content`
  ADD CONSTRAINT `product_content_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_content_ibfk_2` FOREIGN KEY (`attribute_value_id`) REFERENCES `attribute_values` (`id`);

--
-- Constraints for table `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_images_ibfk_2` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_variants`
--
ALTER TABLE `product_variants`
  ADD CONSTRAINT `product_variants_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_variants_ibfk_2` FOREIGN KEY (`spirit_id`) REFERENCES `spirits` (`id`);

--
-- Constraints for table `product_variant_attributes`
--
ALTER TABLE `product_variant_attributes`
  ADD CONSTRAINT `product_variant_attributes_ibfk_1` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_variant_attributes_ibfk_2` FOREIGN KEY (`attribute_value_id`) REFERENCES `attribute_values` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
